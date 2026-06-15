<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ArticleService;
use App\Http\Resources\Api\V1\ArticleResource;
use Illuminate\Http\JsonResponse;
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

    public function latestStories()
    {
        $articles = $this->articleService->getLatestStories();
        return sendResponse(
            true,
            'Latest stories retrieved successfully',
            ArticleResource::collection($articles),
            HttpStatus::HTTP_OK,
        );
    }

    public function show(string $slug)
    {
        $article = $this->articleService->getPublishedBySlug($slug);

        return sendResponse(
            true,
            'Article retrieved successfully',
            new ArticleResource($article),
            HttpStatus::HTTP_OK,
        );
    }

    public function mostRead(Request $request)
    {
        $unique   = filter_var($request->query('unique', false), FILTER_VALIDATE_BOOLEAN);
        $articles = $this->articleService->getMostRead(unique: $unique);

        return sendResponse(
            true,
            'Most read articles retrieved successfully',
            ArticleResource::collection($articles),
            HttpStatus::HTTP_OK,
        );
    }

    public function byCategory(string $slug)
    {
        $articles = $this->articleService->getByCategory($slug);

        return sendResponse(
            true,
            'Articles retrieved successfully',
            ArticleResource::collection($articles),
            HttpStatus::HTTP_OK,
        );
    }


    public function gridArticles(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $limit = min(max($limit, 1), 100); 
        $latestStoryIds = $this->articleService
            ->getLatestStories()
            ->pluck('id')
            ->toArray();

        $articles = $this->articleService->getGridArticles(
            limit: $limit,
            excludeIds: $latestStoryIds
        );

        return sendResponse(
            true,
            'Grid articles retrieved successfully',
            ArticleResource::collection($articles),
            HttpStatus::HTTP_OK,
        );
    }
}
