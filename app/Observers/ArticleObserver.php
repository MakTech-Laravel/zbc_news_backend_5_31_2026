<?php

namespace App\Observers;

use App\Models\Article;

/**
 * Article lifecycle side-effects are handled in ArticleService and PublishScheduledArticles
 * after tags are synced, so notification jobs receive complete article data.
 */
class ArticleObserver
{
    public function created(Article $article): void
    {
        //
    }

    public function updated(Article $article): void
    {
        //
    }
}
