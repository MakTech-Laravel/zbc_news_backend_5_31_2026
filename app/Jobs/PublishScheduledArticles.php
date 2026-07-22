<?php

namespace App\Jobs;

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
        $articleService->publishDueScheduledArticles();
    }
}
