<?php

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationIcon;
use App\Events\UserNotificationCreated;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\ArticleHistroy;
use App\Models\NotificationPreference;
use App\Models\SaveArticle;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class UserNotificationService
{
    public function listForUser(User $user, ?string $category = null, int $limit = 50): Collection
    {
        $query = UserNotification::query()
            ->where('user_id', $user->id)
            ->latest();

        if ($category !== null && $category !== 'all' && $category !== 'unread') {
            $query->where('category', $category);
        }

        if ($category === 'unread') {
            $query->whereNull('read_at');
        }

        return $query->limit($limit)->get();
    }

    public function unreadCount(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(User $user, int $notificationId): ?UserNotification
    {
        $notification = UserNotification::query()
            ->where('user_id', $user->id)
            ->whereKey($notificationId)
            ->first();

        if (! $notification || $notification->read_at !== null) {
            return $notification;
        }

        $notification->update(['read_at' => now()]);

        return $notification->fresh();
    }

    public function markAllAsRead(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function notifyUser(
        User $user,
        NotificationCategory $category,
        NotificationIcon $icon,
        string $title,
        string $body,
        ?string $articleSlug = null,
        ?string $dedupeKey = null,
    ): ?UserNotification {
        try {
            $notification = UserNotification::create([
                'user_id' => $user->id,
                'category' => $category,
                'icon' => $icon,
                'title' => $title,
                'body' => $body,
                'article_slug' => $articleSlug,
                'dedupe_key' => $dedupeKey,
            ]);
        } catch (QueryException $e) {
            if ($dedupeKey !== null && str_contains($e->getMessage(), 'user_notifications_user_id_dedupe_key_unique')) {
                return null;
            }

            throw $e;
        }

        event(new UserNotificationCreated($notification));

        return $notification;
    }

    public function dispatchArticlePublishedNotifications(Article $article): void
    {
        $article->loadMissing(['tags', 'category']);

        if ($this->isBreakingArticle($article)) {
            $this->notifyBreakingNews($article);
        }

        $this->notifyTopicFollowers($article);
    }

    public function dispatchArticleUpdatedNotifications(Article $article): void
    {
        $article->loadMissing(['category']);

        $this->notifySavedArticleWatchers($article);
    }

    private function isBreakingArticle(Article $article): bool
    {
        return $article->tags->contains(function ($tag) {
            $normalized = strtolower($tag->tag);

            return in_array($normalized, ['breaking', 'breaking-news', 'breaking news'], true);
        });
    }

    private function notifyBreakingNews(Article $article): void
    {
        $userIds = NotificationPreference::query()
            ->where('breaking_news', true)
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }

            $this->notifyUser(
                $user,
                NotificationCategory::BREAKING,
                NotificationIcon::BREAKING,
                'Breaking News',
                $article->title,
                $article->slug,
                "breaking:article:{$article->id}",
            );
        }
    }

    private function notifyTopicFollowers(Article $article): void
    {
        if (! $article->article_category_id) {
            return;
        }

        $categoryTitle = $article->category?->title ?? 'News';
        $icon = $this->iconForCategory($article->category?->slug);

        $readerIds = ArticleHistroy::query()
            ->join('articles', 'articles.id', '=', 'article_histroys.article_id')
            ->where('articles.article_category_id', $article->article_category_id)
            ->whereNotNull('article_histroys.user_id')
            ->distinct()
            ->pluck('article_histroys.user_id');

        if ($readerIds->isEmpty()) {
            return;
        }

        $eligibleIds = NotificationPreference::query()
            ->whereIn('user_id', $readerIds)
            ->where('personalized_recommendations', true)
            ->pluck('user_id');

        foreach ($eligibleIds as $userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }

            $this->notifyUser(
                $user,
                NotificationCategory::TOPIC,
                $icon,
                $categoryTitle,
                "New article in {$categoryTitle}: {$article->title}",
                $article->slug,
                "topic:article:{$article->id}:user:{$userId}",
            );
        }
    }

    private function notifySavedArticleWatchers(Article $article): void
    {
        $saverIds = SaveArticle::query()
            ->where('article_id', $article->id)
            ->pluck('user_id');

        if ($saverIds->isEmpty()) {
            return;
        }

        $eligibleIds = NotificationPreference::query()
            ->whereIn('user_id', $saverIds)
            ->where('saved_article_updates', true)
            ->pluck('user_id');

        foreach ($eligibleIds as $userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }

            $this->notifyUser(
                $user,
                NotificationCategory::SAVED,
                NotificationIcon::SAVED,
                'Saved Article',
                "An article you saved has been updated: {$article->title}",
                $article->slug,
                "saved:article:{$article->id}:user:{$userId}",
            );
        }
    }

    private function iconForCategory(?string $slug): NotificationIcon
    {
        $normalized = strtolower((string) $slug);

        return match (true) {
            str_contains($normalized, 'tech') => NotificationIcon::TECHNOLOGY,
            str_contains($normalized, 'business') || str_contains($normalized, 'finance') => NotificationIcon::BUSINESS,
            default => NotificationIcon::RECOMMENDED,
        };
    }

    public function dispatchCommentReplyNotification(ArticleComment $reply): void
    {
        if (! $reply->parent_id) {
            return;
        }

        $reply->loadMissing(['user', 'article', 'parent.user']);
        $parent = $reply->parent;

        if (! $parent?->user_id || (int) $parent->user_id === (int) $reply->user_id) {
            return;
        }

        $parentUser = User::query()->find($parent->user_id);
        if (! $parentUser) {
            return;
        }

        $wantsReplies = NotificationPreference::query()
            ->where('user_id', $parentUser->id)
            ->where('comment_replies', true)
            ->exists();

        if (! $wantsReplies) {
            return;
        }

        $replierName = $reply->authorName();
        $articleTitle = $reply->article?->title ?? 'an article';

        $this->notifyUser(
            $parentUser,
            NotificationCategory::SOCIAL,
            NotificationIcon::REPLY,
            'New Reply',
            "{$replierName} replied to your comment on '{$articleTitle}'",
            $reply->article?->slug,
            "comment-reply:{$reply->id}",
        );
    }
}
