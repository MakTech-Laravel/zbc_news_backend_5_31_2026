<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ArticleService;
use App\Http\Resources\Api\V1\ArticleResource;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
class ArticleController extends Controller
{
    public function __construct(
        private readonly ArticleService $articleService
    ) {}

    public function index()
    {
        $articles = $this->articleService->getAllArticles();
        return sendResponse(
            true,
            'Articles retrieved successfully',
            ArticleResource::collection($articles),
            HttpStatus::HTTP_OK,
        );
    }

    public function latest()
    {
        $article = $this->articleService->getLatestArticle();

        return sendResponse(
            true,
            'Latest article retrieved successfully',
            new ArticleResource($article),
            HttpStatus::HTTP_OK,
        );
    }

    public function show(string $slug)
    {
        $article = $this->articleService->getBySlug($slug);
        return sendResponse(
            true,
            'Article retrieved successfully',
            new ArticleResource($article),
            HttpStatus::HTTP_OK,
        );
    }
}
