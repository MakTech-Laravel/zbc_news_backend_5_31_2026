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
        return $this->saveArticle->where('user_id', auth()->user()->id)->get();
    }
    public function create(string $article_id): SaveArticle
    {
        return $this->saveArticle->create([
            'article_id' => $article_id,
            'user_id' => auth()->user()->id,
        ]);
    }


    public function delete(string $id): array
    {
        $saveArticle = $this->saveArticle
            ->where('id', $id)
            ->where('user_id', auth()->user()->id)
            ->first();

        if (!$saveArticle) {
            return [
                'success' => false,
                'message' => 'Save article not found or unauthorized.',
            ];
        }

        $saveArticle->delete();

        return [
            'success' => true,
            'message' => 'Save article removed successfully.',
        ];
    }
}
