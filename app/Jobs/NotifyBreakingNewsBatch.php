<?php

namespace App\Jobs;

use App\Enums\NotificationCategory;
use App\Enums\NotificationIcon;
use App\Models\Article;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyBreakingNewsBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, int>  $userIds
     */
    public function __construct(
        public readonly int $articleId,
        public readonly array $userIds,
    ) {}

    public function handle(UserNotificationService $service): void
    {
        $article = Article::query()->find($this->articleId);

        if (! $article) {
            return;
        }

        foreach ($this->userIds as $userId) {
            $user = User::query()->find($userId);

            if (! $user) {
                continue;
            }

            $service->notifyUser(
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
}
