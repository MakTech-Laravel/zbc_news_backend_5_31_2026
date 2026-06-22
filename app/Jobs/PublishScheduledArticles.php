<?php

namespace App\Jobs;

use App\Enums\ArticleStatus;
use App\Jobs\DispatchArticlePublishedNotifications;
use App\Models\Article;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishScheduledArticles
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Article::query()
            ->where('status', ArticleStatus::SCHEDULED->value)
            ->where('scheduled_publishing', '<=', now())
            ->each(function (Article $article) {
                $article->update([
                    'status' => ArticleStatus::PUBLISHED->value,
                    'published_at' => $article->scheduled_publishing,
                ]);

                DispatchArticlePublishedNotifications::dispatch($article->id, 'published');
            });
    }
}
