<?php

namespace App\Jobs;

use App\Enums\ArticleStatus;
use App\Jobs\DispatchArticlePublishedNotifications;
use App\Models\Article;
use App\Services\ArticleService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishScheduledArticles
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ArticleService $articleService): void
    {
        Article::query()
            ->where('status', ArticleStatus::SCHEDULED->value)
            ->where('scheduled_publishing', '<=', now())
            ->each(function (Article $article) use ($articleService) {
                $article->update([
                    'status' => ArticleStatus::PUBLISHED->value,
                    'published_at' => $article->scheduled_publishing,
                ]);

                $article = $article->fresh();

                DispatchArticlePublishedNotifications::dispatch($article->id, 'published');
                $articleService->broadcastPublishedArticle($article);
            });
    }
}
