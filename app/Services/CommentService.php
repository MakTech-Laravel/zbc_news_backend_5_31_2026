<?php

namespace App\Services;

use App\Enums\CommentStatus;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CommentService
{
    public function __construct(
        private readonly SiteSettingsService $siteSettingsService,
        private readonly UserNotificationService $notificationService,
    ) {}

    public function listApprovedForArticle(Article $article): Collection
    {
        $comments = ArticleComment::query()
            ->with(['user'])
            ->where('article_id', $article->id)
            ->where('status', CommentStatus::APPROVED)
            ->orderBy('created_at')
            ->get();

        return $this->buildTree($comments);
    }

    public function countApprovedForArticle(int $articleId): int
    {
        return ArticleComment::query()
            ->where('article_id', $articleId)
            ->where('status', CommentStatus::APPROVED)
            ->count();
    }

    public function adminList(?string $status = null, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = ArticleComment::query()
            ->with(['user', 'article'])
            ->latest();

        if ($status && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        if ($search) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($search)).'%';
            $query->where(function ($q) use ($like) {
                $q->where('body', 'like', $like)
                    ->orWhere('guest_name', 'like', $like)
                    ->orWhere('guest_email', 'like', $like)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', $like))
                    ->orWhereHas('article', fn ($articleQuery) => $articleQuery->where('title', 'like', $like));
            });
        }

        return $query->paginate($perPage);
    }

    public function store(Article $article, array $data, ?User $user, Request $request): ArticleComment
    {
        if (! $this->siteSettingsService->commentsAllowed()) {
            throw ValidationException::withMessages([
                'body' => ['Comments are disabled on this site.'],
            ]);
        }

        $settings = $this->siteSettingsService->getOrDefault();

        if ($settings->authenticate_comment_only && ! $user) {
            throw ValidationException::withMessages([
                'body' => ['You must be signed in to comment.'],
            ]);
        }

        if (! $user) {
            if (empty($data['guest_name']) || empty($data['guest_email'])) {
                throw ValidationException::withMessages([
                    'guest_name' => ['Name is required for guest comments.'],
                    'guest_email' => ['Email is required for guest comments.'],
                ]);
            }
        }

        $parentId = $data['parent_id'] ?? null;
        if ($parentId) {
            $parent = ArticleComment::query()
                ->where('article_id', $article->id)
                ->whereKey($parentId)
                ->where('status', CommentStatus::APPROVED)
                ->first();

            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => ['The comment you are replying to is not available.'],
                ]);
            }
        }

        $status = $this->resolveInitialStatus($user);

        $comment = ArticleComment::create([
            'article_id' => $article->id,
            'user_id' => $user?->id,
            'parent_id' => $parentId,
            'body' => trim($data['body']),
            'status' => $status,
            'guest_name' => $user ? null : trim((string) ($data['guest_name'] ?? '')),
            'guest_email' => $user ? null : strtolower(trim((string) ($data['guest_email'] ?? ''))),
            'ip_address' => $request->ip(),
            'approved_at' => $status === CommentStatus::APPROVED ? now() : null,
            'approved_by' => $status === CommentStatus::APPROVED ? $user?->id : null,
        ]);

        if ($status === CommentStatus::APPROVED) {
            $this->notificationService->dispatchCommentReplyNotification($comment->load(['user', 'article', 'parent.user']));
        }

        return $comment->load(['user', 'article']);
    }

    public function approve(ArticleComment $comment, User $moderator): ArticleComment
    {
        $comment->update([
            'status' => CommentStatus::APPROVED,
            'approved_at' => now(),
            'approved_by' => $moderator->id,
        ]);

        $fresh = $comment->fresh(['user', 'article', 'parent.user']);
        $this->notificationService->dispatchCommentReplyNotification($fresh);

        return $fresh;
    }

    public function reject(ArticleComment $comment): ArticleComment
    {
        $comment->update([
            'status' => CommentStatus::REJECTED,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        return $comment->fresh(['user', 'article']);
    }

    public function delete(ArticleComment $comment): void
    {
        $comment->delete();
    }

    private function resolveInitialStatus(?User $user): CommentStatus
    {
        if (! $user) {
            return CommentStatus::PENDING;
        }

        if ($user->hasAnyRole(['super_admin', 'admin', 'moderator', 'editor'])) {
            return CommentStatus::APPROVED;
        }

        $settings = $this->siteSettingsService->getOrDefault();
        if ($settings->auto_approve_known_users) {
            $hasApproved = ArticleComment::query()
                ->where('user_id', $user->id)
                ->where('status', CommentStatus::APPROVED)
                ->exists();

            if ($hasApproved) {
                return CommentStatus::APPROVED;
            }
        }

        return CommentStatus::PENDING;
    }

    private function buildTree(Collection $comments, ?int $parentId = null): Collection
    {
        return $comments
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (ArticleComment $comment) use ($comments) {
                $comment->setRelation(
                    'nested_replies',
                    $this->buildTree($comments, $comment->id),
                );

                return $comment;
            });
    }
}
