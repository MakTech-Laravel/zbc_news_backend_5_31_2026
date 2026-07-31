<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\LiveUpdateStatus;
use App\Jobs\DispatchArticlePublishedNotifications;
use App\Models\Article;
use App\Models\ArticleLiveUpdate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiveUpdateService
{
    public function __construct(
        private readonly ArticleLiveUpdate $liveUpdate,
        private readonly SubMenuService $subMenuService,
    ) {}

    public function findLiveBlogBySlug(string $slug): Article
    {
        $article = Article::query()
            ->with([
                'tags',
                'category',
                'user',
                'breakingNewsItem',
                'liveUpdates.user',
                'media' => fn ($q) => $q->where('status', 'ready')
                    ->whereIn('collection', ['featured', 'poster']),
            ])
            ->where('slug', $slug)
            ->where('is_live_blog', true)
            ->firstOrFail();

        return $article;
    }

    /**
     * @return Collection<int, ArticleLiveUpdate>
     */
    public function listEntries(Article $article): Collection
    {
        $this->assertLiveBlog($article);

        return $article->liveUpdates()->with('user')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEntry(Article $article, array $data, ?int $userId = null): ArticleLiveUpdate
    {
        $this->assertLiveBlog($article);

        return DB::transaction(function () use ($article, $data, $userId) {
            $status = $this->resolveStatus($data['status'] ?? LiveUpdateStatus::PUBLISHED->value);
            $postedAt = $data['posted_at'] ?? now();

            $entry = $this->liveUpdate->newQuery()->create([
                'article_id' => $article->id,
                'body' => $data['body'],
                'posted_at' => $postedAt,
                'status' => $status->value,
                'user_id' => $userId ?? auth()->id(),
            ]);

            $this->touchParentAndNotify($article, $status);

            activity()
                ->performedOn($article)
                ->causedBy(auth()->user())
                ->withProperties([
                    'live_update_id' => $entry->id,
                    'status' => $status->value,
                    'posted_at' => $entry->posted_at?->toIso8601String(),
                ])
                ->log('Live update entry created');

            return $entry->fresh(['user']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEntry(Article $article, int $entryId, array $data): ArticleLiveUpdate
    {
        $this->assertLiveBlog($article);

        $entry = $this->liveUpdate->newQuery()
            ->where('article_id', $article->id)
            ->whereKey($entryId)
            ->firstOrFail();

        return DB::transaction(function () use ($article, $entry, $data) {
            $previousStatus = $entry->status;
            $status = array_key_exists('status', $data)
                ? $this->resolveStatus($data['status'])
                : $previousStatus;

            $payload = [
                'body' => $data['body'] ?? $entry->body,
                'status' => $status->value,
            ];

            if (array_key_exists('posted_at', $data) && $data['posted_at'] !== null) {
                $payload['posted_at'] = $data['posted_at'];
            }

            $entry->update($payload);

            $becamePublished = $previousStatus !== LiveUpdateStatus::PUBLISHED
                && $status === LiveUpdateStatus::PUBLISHED;
            $publishedContentChanged = $status === LiveUpdateStatus::PUBLISHED
                && $entry->wasChanged(['body', 'posted_at']);

            if ($becamePublished || $publishedContentChanged) {
                $this->touchParentAndNotify($article, $status);
            }

            activity()
                ->performedOn($article)
                ->causedBy(auth()->user())
                ->withProperties([
                    'live_update_id' => $entry->id,
                    'status' => $status->value,
                    'posted_at' => $entry->posted_at?->toIso8601String(),
                ])
                ->log('Live update entry updated');

            return $entry->fresh(['user']);
        });
    }

    public function deleteEntry(Article $article, int $entryId): void
    {
        $this->assertLiveBlog($article);

        $entry = $this->liveUpdate->newQuery()
            ->where('article_id', $article->id)
            ->whereKey($entryId)
            ->firstOrFail();

        $wasPublished = $entry->status === LiveUpdateStatus::PUBLISHED;
        $entry->delete();

        if ($wasPublished && $article->status === ArticleStatus::PUBLISHED) {
            $article->touch();
            $this->subMenuService->flushPublicCache();
        }

        activity()
            ->performedOn($article)
            ->causedBy(auth()->user())
            ->withProperties([
                'live_update_id' => $entryId,
            ])
            ->log('Live update entry deleted');
    }

    public function startLiveCoverage(Article $article): Article
    {
        $this->assertLiveBlog($article);

        if ($article->status !== ArticleStatus::PUBLISHED) {
            throw ValidationException::withMessages([
                'article' => ['Only published live updates can go live.'],
            ]);
        }

        return $this->subMenuService->startLiveCoverage($article);
    }

    public function endLiveCoverage(Article $article): Article
    {
        $this->assertLiveBlog($article);

        return $this->subMenuService->endLiveCoverage($article);
    }

    private function assertLiveBlog(Article $article): void
    {
        if (! $article->is_live_blog) {
            throw ValidationException::withMessages([
                'article' => ['This article is not a live update shell.'],
            ]);
        }
    }

    private function resolveStatus(mixed $value): LiveUpdateStatus
    {
        if ($value instanceof LiveUpdateStatus) {
            return $value;
        }

        return LiveUpdateStatus::from((string) $value);
    }

    private function touchParentAndNotify(Article $article, LiveUpdateStatus $status): void
    {
        if ($status !== LiveUpdateStatus::PUBLISHED) {
            return;
        }

        if ($article->status !== ArticleStatus::PUBLISHED) {
            return;
        }

        $article->touch();
        $this->subMenuService->flushPublicCache();
        DispatchArticlePublishedNotifications::dispatch($article->id, 'updated');
    }
}
