<?php

namespace App\Services;

use App\Models\SaveArticle;

class SaveArticleService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly SaveArticle $saveArticle
    ) {}


    public function getAll()
    {
        return $this->saveArticle
            ->with('article')
            ->where('user_id', auth()->id())
            ->get();
    }

    public function toggle(int $articleId): array
    {
        $savedArticle = $this->saveArticle
            ->where('article_id', $articleId)
            ->where('user_id', auth()->id())
            ->first();

        if ($savedArticle) {
            $savedArticle->delete();

            return [
                'saved' => false,
                'message' => 'Article removed from saved list',
            ];
        }

        $savedArticle = $this->saveArticle->create([
            'article_id' => $articleId,
            'user_id' => auth()->id(),
        ]);

        return [
            'saved' => true,
            'message' => 'Article saved successfully',
            'data' => $savedArticle,
        ];
    }

    public function isSaved(int $articleId): bool
    {
        return $this->saveArticle
            ->where('article_id', $articleId)
            ->where('user_id', auth()->id())
            ->exists();
    }
}
