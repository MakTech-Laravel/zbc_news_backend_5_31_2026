<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\UserNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchArticlePublishedNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $articleId,
        public readonly string $eventType = 'published',
    ) {}

    public function handle(UserNotificationService $service): void
    {
        $article = Article::query()->find($this->articleId);

        if (! $article) {
            return;
        }

        if ($this->eventType === 'updated') {
            $service->dispatchArticleUpdatedNotifications($article);

            return;
        }

        $service->dispatchArticlePublishedNotifications($article);
    }
}
