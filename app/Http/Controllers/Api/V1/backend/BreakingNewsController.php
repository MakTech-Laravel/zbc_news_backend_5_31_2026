<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Services\ArticleService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class BreakingNewsController extends Controller
{
    public function __construct(
        private readonly ArticleService $articleService,
    ) {}

    public function index(): JsonResponse
    {
        $articles = $this->articleService->getAdminBreakingNewsArticles();

        return sendResponse(
            true,
            'Breaking news articles retrieved successfully.',
            ArticleResource::collection($articles),
            HttpStatus::HTTP_OK,
        );
    }

    public function destroy(string $slug): JsonResponse
    {
        $article = $this->articleService->clearBreakingNews($slug);

        return sendResponse(
            true,
            'Article removed from breaking news.',
            new ArticleResource($article),
            HttpStatus::HTTP_OK,
        );
    }
}
