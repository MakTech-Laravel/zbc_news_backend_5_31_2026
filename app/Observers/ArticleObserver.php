<?php

namespace App\Observers;

use App\Enums\ArticleStatus;
use App\Jobs\DispatchArticlePublishedNotifications;
use App\Models\Article;

class ArticleObserver
{
    public function created(Article $article): void
    {
        if ($article->status === ArticleStatus::PUBLISHED) {
            DispatchArticlePublishedNotifications::dispatch($article->id, 'published');
        }
    }

    public function updated(Article $article): void
    {
        if ($article->wasChanged('status') && $article->status === ArticleStatus::PUBLISHED) {
            DispatchArticlePublishedNotifications::dispatch($article->id, 'published');

            return;
        }

        if (
            $article->status === ArticleStatus::PUBLISHED
            && $article->wasChanged(['title', 'article_description', 'excerpt', 'sub_title'])
        ) {
            DispatchArticlePublishedNotifications::dispatch($article->id, 'updated');
        }
    }
}
