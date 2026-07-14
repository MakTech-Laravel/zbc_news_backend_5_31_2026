<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Http\Resources\Api\V1\Category as CategoryResource;
use App\Services\ArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function breakingNews(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $articles = $this->articleService->getBreakingNewsArticles($limit);

        return sendResponse(
            true,
            'Breaking news articles retrieved successfully',
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
        $unique = filter_var($request->query('unique', false), FILTER_VALIDATE_BOOLEAN);
        $articles = $this->articleService->getMostRead(unique: $unique);

        return sendResponse(
            true,
            'Most read articles retrieved successfully',
            ArticleResource::collection($articles),
            HttpStatus::HTTP_OK,
        );
    }

    public function byCategory(string $slug, Request $request)
    {
        $perPage = $request->query('per_page') ? (int) $request->query('per_page') : null;
        $page = (int) $request->query('page', 1);
        $result = $this->articleService->getByCategory($slug, $perPage, $page);

        return sendResponse(
            true,
            'Articles retrieved successfully',
            [
                'category' => new CategoryResource($result['category']),
                'articles' => ArticleResource::collection($result['items']),
                'meta' => $result['meta'],
            ],
            HttpStatus::HTTP_OK,
        );
    }

    public function related(string $slug)
    {
        $articles = $this->articleService->getRelatedArticles($slug);

        return sendResponse(
            true,
            'Related articles retrieved successfully',
            ArticleResource::collection($articles),
            HttpStatus::HTTP_OK,
        );
    }

    public function articlesByTag(Request $request, string $tagSlug): JsonResponse
    {
        $type = (string) $request->query('type', 'latest');
        $articles = $this->articleService->getLatestArticleByTag($tagSlug, $type);

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

    public function archive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:1970|max:'.(now()->year + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'category' => 'nullable|string',
            'author' => 'nullable|integer|exists:users,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = [
            'year' => isset($validated['year']) ? (int) $validated['year'] : null,
            'month' => isset($validated['month']) ? (int) $validated['month'] : null,
            'category' => $validated['category'] ?? null,
            'author' => isset($validated['author']) ? (int) $validated['author'] : null,
        ];

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : null;
        $page = (int) ($validated['page'] ?? 1);
        $result = $this->articleService->getArchiveArticles($filters, $perPage, $page);

        return sendResponse(
            true,
            'Archive articles retrieved successfully',
            [
                'articles' => ArticleResource::collection($result['items']),
                'meta' => $result['meta'],
                'filters' => $result['filters'],
            ],
            HttpStatus::HTTP_OK,
        );
    }

    public function archiveFilters(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:1970|max:'.(now()->year + 1),
        ]);

        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $options = $this->articleService->getArchiveFilterOptions($year);

        return sendResponse(
            true,
            'Archive filter options retrieved successfully',
            $options,
            HttpStatus::HTTP_OK,
        );
    }
}