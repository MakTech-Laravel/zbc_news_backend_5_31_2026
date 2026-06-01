<?php

namespace App\Jobs;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// class PublishScheduledArticles implements ShouldQueue
class PublishScheduledArticles
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Article::query()
            ->where('status', ArticleStatus::SCHEDULED->value)
            ->where('scheduled_publishing', '<=', now())
            ->each(function (Article $article) {
                $article->update([
                    'status'       => ArticleStatus::PUBLISHED->value,
                    'published_at' => $article->scheduled_publishing,
                ]);
            });
    }
}
