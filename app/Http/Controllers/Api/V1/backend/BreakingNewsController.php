<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BreakingNewsItemRequest;
use App\Http\Resources\Api\V1\BreakingNewsItemResource;
use App\Models\Article;
use App\Services\BreakingNewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class BreakingNewsController extends Controller
{
    public function __construct(
        private readonly BreakingNewsService $breakingNewsService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->breakingNewsService->listForAdmin([
            'status' => $request->query('status', 'all'),
            'search' => $request->query('search'),
        ]);

        return sendResponse(
            true,
            'Breaking news items retrieved successfully.',
            BreakingNewsItemResource::collection($items),
            HttpStatus::HTTP_OK,
        );
    }

    public function store(BreakingNewsItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $article = Article::query()->findOrFail($data['article_id']);

        $item = $this->breakingNewsService->syncForArticle(
            $article,
            [
                'enabled' => $data['enabled'] ?? true,
                'priority' => $data['priority'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'headline_override' => $data['headline_override'] ?? null,
                'status' => $data['status'] ?? 'active',
            ],
            $request->user()?->id,
        );

        return sendResponse(
            true,
            'Breaking news item saved successfully.',
            $item ? new BreakingNewsItemResource($item) : null,
            HttpStatus::HTTP_CREATED,
        );
    }

    public function update(BreakingNewsItemRequest $request, int $id): JsonResponse
    {
        $item = $this->breakingNewsService->findOrFail($id);
        $updated = $this->breakingNewsService->update($item, $request->validated());

        return sendResponse(
            true,
            'Breaking news item updated successfully.',
            new BreakingNewsItemResource($updated),
            HttpStatus::HTTP_OK,
        );
    }

    public function activate(int $id): JsonResponse
    {
        $item = $this->breakingNewsService->activate(
            $this->breakingNewsService->findOrFail($id),
        );

        return sendResponse(
            true,
            'Breaking news item activated.',
            new BreakingNewsItemResource($item),
            HttpStatus::HTTP_OK,
        );
    }

    public function pause(int $id): JsonResponse
    {
        $item = $this->breakingNewsService->pause(
            $this->breakingNewsService->findOrFail($id),
        );

        return sendResponse(
            true,
            'Breaking news item paused.',
            new BreakingNewsItemResource($item),
            HttpStatus::HTTP_OK,
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->breakingNewsService->remove(
            $this->breakingNewsService->findOrFail($id),
        );

        return sendResponse(
            true,
            'Breaking news item removed.',
            null,
            HttpStatus::HTTP_OK,
        );
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:breaking_news_items,id'],
        ]);

        $items = $this->breakingNewsService->reorder($validated['ids']);

        return sendResponse(
            true,
            'Breaking news order updated.',
            BreakingNewsItemResource::collection($items),
            HttpStatus::HTTP_OK,
        );
    }
}
