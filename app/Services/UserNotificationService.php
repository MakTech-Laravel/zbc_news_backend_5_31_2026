<?php

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationIcon;
use App\Events\UserNotificationCreated;
use App\Jobs\NotifyBreakingNewsBatch;
use App\Models\Announcement;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\ArticleHistroy;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\NotificationPreference;
use App\Models\SaveArticle;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\BreakingTag;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class UserNotificationService
{
    private const BREAKING_BATCH_SIZE = 200;

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

        $topicCount = $this->notifyTopicFollowers($article);

        Log::info('Article published notifications dispatched', [
            'article_id' => $article->id,
            'breaking' => $this->isBreakingArticle($article),
            'topic_notifications' => $topicCount,
        ]);
    }

    public function dispatchArticleUpdatedNotifications(Article $article): void
    {
        $article->loadMissing(['category']);

        $savedCount = $this->notifySavedArticleWatchers($article);

        Log::info('Article updated notifications dispatched', [
            'article_id' => $article->id,
            'saved_notifications' => $savedCount,
        ]);
    }

    public function dispatchNewsletterCampaignNotifications(NewsletterCampaign $campaign): void
    {
        $userIds = NotificationPreference::query()
            ->where('daily_newsletter', true)
            ->pluck('user_id');

        $sent = 0;
        $preview = $campaign->preview_text ?: strip_tags((string) $campaign->content_html);
        $body = mb_strlen($preview) > 160 ? mb_substr($preview, 0, 157).'...' : $preview;

        foreach ($userIds->chunk(self::BREAKING_BATCH_SIZE) as $chunk) {
            foreach ($chunk as $userId) {
                $user = User::query()->find($userId);

                if (! $user) {
                    continue;
                }

                $created = $this->notifyUser(
                    $user,
                    NotificationCategory::SYSTEM,
                    NotificationIcon::RECOMMENDED,
                    $campaign->subject ?: $campaign->title,
                    $body ?: 'A new newsletter edition is available.',
                    null,
                    "newsletter:campaign:{$campaign->id}:user:{$userId}",
                );

                if ($created) {
                    $sent++;
                }
            }
        }

        Log::info('Newsletter campaign in-app notifications dispatched', [
            'campaign_id' => $campaign->id,
            'sent' => $sent,
        ]);
    }

    public function dispatchAnnouncementNotifications(Announcement $announcement): int
    {
        $query = User::query()->orderBy('id');

        if ($announcement->audience === 'authenticated_only') {
            $query->whereNotNull('email_verified_at');
        }

        $sent = 0;

        $query->pluck('id')
            ->chunk(self::BREAKING_BATCH_SIZE)
            ->each(function ($userIds) use ($announcement, &$sent) {
                $eligibleIds = NotificationPreference::filterUserIds(
                    $userIds,
                    'platform_announcements',
                );

                foreach ($eligibleIds as $userId) {
                    $user = User::query()->find($userId);

                    if (! $user) {
                        continue;
                    }

                    $created = $this->notifyUser(
                        $user,
                        NotificationCategory::SYSTEM,
                        NotificationIcon::ANNOUNCEMENT,
                        $announcement->title,
                        $announcement->body,
                        null,
                        "announcement:{$announcement->id}:user:{$userId}",
                    );

                    if ($created) {
                        $sent++;
                    }
                }
            });

        Log::info('Announcement notifications dispatched', [
            'announcement_id' => $announcement->id,
            'sent' => $sent,
        ]);

        return $sent;
    }

    public function dispatchNewsletterSubscriptionAdminNotifications(
        NewsletterSubscriber $subscriber,
        bool $verified = false,
    ): int {
        $adminIds = User::query()
            ->role(['admin', 'super_admin'])
            ->pluck('id');

        if ($adminIds->isEmpty()) {
            return 0;
        }

        $label = $subscriber->name ?: $subscriber->email;
        $source = $subscriber->source ?? 'website';

        if ($verified) {
            $title = 'Newsletter subscription verified';
            $body = "{$label} ({$subscriber->email}) verified their newsletter subscription via {$source}.";
            $dedupeKey = "newsletter:verified:admin:{$subscriber->id}";
        } else {
            $title = 'New newsletter subscription';
            $body = "{$label} ({$subscriber->email}) subscribed to the newsletter via {$source}. Verification is pending.";
            $dedupeKey = "newsletter:subscribe:admin:{$subscriber->id}";
        }

        $sent = 0;

        foreach ($adminIds as $adminId) {
            $admin = User::query()->find($adminId);

            if (! $admin) {
                continue;
            }

            $created = $this->notifyUser(
                $admin,
                NotificationCategory::SYSTEM,
                NotificationIcon::RECOMMENDED,
                $title,
                $body,
                null,
                "{$dedupeKey}:user:{$adminId}",
            );

            if ($created) {
                $sent++;
            }
        }

        Log::info('Newsletter subscription admin notifications dispatched', [
            'subscriber_id' => $subscriber->id,
            'verified' => $verified,
            'sent' => $sent,
        ]);

        return $sent;
    }

    private function isBreakingArticle(Article $article): bool
    {
        return $article->tags->contains(fn ($tag) => BreakingTag::isBreaking($tag->tag));
    }

    private function notifyBreakingNews(Article $article): void
    {
        $userIds = NotificationPreference::query()
            ->where('breaking_news', true)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($userIds === []) {
            return;
        }

        foreach (array_chunk($userIds, self::BREAKING_BATCH_SIZE) as $batch) {
            NotifyBreakingNewsBatch::dispatch($article->id, $batch);
        }

        Log::info('Breaking news notification batches queued', [
            'article_id' => $article->id,
            'recipient_batches' => (int) ceil(count($userIds) / self::BREAKING_BATCH_SIZE),
            'recipient_count' => count($userIds),
        ]);
    }

    private function notifyTopicFollowers(Article $article): int
    {
        if (! $article->article_category_id) {
            return 0;
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
            return 0;
        }

        $eligibleIds = NotificationPreference::filterUserIds(
            $readerIds,
            'personalized_recommendations',
        );

        $sent = 0;

        foreach ($eligibleIds as $userId) {
            if ((int) $userId === (int) $article->user_id) {
                continue;
            }

            $user = User::query()->find($userId);

            if (! $user) {
                continue;
            }

            $created = $this->notifyUser(
                $user,
                NotificationCategory::TOPIC,
                $icon,
                $categoryTitle,
                "New article in {$categoryTitle}: {$article->title}",
                $article->slug,
                "topic:article:{$article->id}:user:{$userId}",
            );

            if ($created) {
                $sent++;
            }
        }

        return $sent;
    }

    private function notifySavedArticleWatchers(Article $article): int
    {
        $saverIds = SaveArticle::query()
            ->where('article_id', $article->id)
            ->pluck('user_id');

        if ($saverIds->isEmpty()) {
            return 0;
        }

        $eligibleIds = NotificationPreference::filterUserIds(
            $saverIds,
            'saved_article_updates',
        );

        $sent = 0;

        foreach ($eligibleIds as $userId) {
            $user = User::query()->find($userId);

            if (! $user) {
                continue;
            }

            $created = $this->notifyUser(
                $user,
                NotificationCategory::SAVED,
                NotificationIcon::SAVED,
                'Saved Article',
                "An article you saved has been updated: {$article->title}",
                $article->slug,
                "saved:article:{$article->id}:user:{$userId}",
            );

            if ($created) {
                $sent++;
            }
        }

        return $sent;
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

        if (! NotificationPreference::wants($parentUser->id, 'comment_replies')) {
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

        Log::info('Comment reply notification dispatched', [
            'reply_id' => $reply->id,
            'recipient_id' => $parentUser->id,
        ]);
    }
}
