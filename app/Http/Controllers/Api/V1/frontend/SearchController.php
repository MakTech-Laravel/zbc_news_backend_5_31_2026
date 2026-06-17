<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $searchService,
    ) {}

    /**
     * GET /api/v1/articles/search?q=...
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $articles = $this->searchService->searchArticles(
            $validated['q'],
            (int) ($validated['limit'] ?? 10),
        );

        return sendResponse(
            true,
            'Search results fetched successfully',
            ArticleResource::collection($articles),
            HttpStatus::HTTP_OK,
        );
    }

    /**
     * GET /api/v1/search/history
     */
    public function history(Request $request): JsonResponse
    {
        $history = $this->searchService->getHistory($request);

        return sendResponse(
            true,
            'Search history fetched successfully',
            $history,
            HttpStatus::HTTP_OK,
        );
    }

    /**
     * POST /api/v1/search/history
     */
    public function storeHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $this->searchService->recordHistory($request, $validated['query']);

        return sendResponse(
            true,
            'Search history saved successfully',
            null,
            HttpStatus::HTTP_OK,
        );
    }

    /**
     * DELETE /api/v1/search/history
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $this->searchService->clearHistory($request);

        return sendResponse(
            true,
            'Search history cleared successfully',
            null,
            HttpStatus::HTTP_OK,
        );
    }
}
