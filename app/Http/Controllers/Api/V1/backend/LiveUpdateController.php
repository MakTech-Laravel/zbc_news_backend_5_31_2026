<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ArticleRequest;
use App\Http\Requests\Api\V1\LiveUpdateEntryRequest;
use App\Http\Resources\Api\V1\ArticleLiveUpdateResource;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Services\ArticleService;
use App\Services\LiveUpdateService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class LiveUpdateController extends Controller
{
    public function __construct(
        private readonly ArticleService $articleService,
        private readonly LiveUpdateService $liveUpdateService,
    ) {}

    public function index(): JsonResponse
    {
        $articles = $this->articleService->getLiveBlogArticles();

        return sendResponse(
            true,
            'Live updates retrieved successfully',
            ArticleResource::collection($articles),
            HttpStatus::HTTP_OK,
        );
    }

    public function store(ArticleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_live_blog'] = true;
        $data['article_description'] = $data['article_description'] ?? '';

        $article = $this->articleService->create($data);

        return sendResponse(
            true,
            'Live update created successfully',
            new ArticleResource($article->load('liveUpdates.user')),
            HttpStatus::HTTP_CREATED,
        );
    }

    public function show(string $slug): JsonResponse
    {
        $article = $this->liveUpdateService->findLiveBlogBySlug($slug);

        return sendResponse(
            true,
            'Live update retrieved successfully',
            new ArticleResource($article),
            HttpStatus::HTTP_OK,
        );
    }

    public function update(ArticleRequest $request, string $slug): JsonResponse
    {
        $this->liveUpdateService->findLiveBlogBySlug($slug);

        $data = $request->validated();
        $data['is_live_blog'] = true;
        $data['article_description'] = $data['article_description'] ?? '';

        $article = $this->articleService->update($slug, $data);

        return sendResponse(
            true,
            'Live update updated successfully',
            new ArticleResource($article->load(['liveUpdates.user'])),
            HttpStatus::HTTP_OK,
        );
    }

    public function destroy(string $slug): JsonResponse
    {
        $this->liveUpdateService->findLiveBlogBySlug($slug);
        $this->articleService->delete($slug);

        return sendResponse(
            true,
            'Live update deleted successfully',
            null,
            HttpStatus::HTTP_OK,
        );
    }

    public function storeEntry(LiveUpdateEntryRequest $request, string $slug): JsonResponse
    {
        $article = $this->liveUpdateService->findLiveBlogBySlug($slug);
        $entry = $this->liveUpdateService->createEntry(
            $article,
            $request->validated(),
            $request->user()?->id,
        );

        return sendResponse(
            true,
            'Live update entry created successfully',
            new ArticleLiveUpdateResource($entry),
            HttpStatus::HTTP_CREATED,
        );
    }

    public function updateEntry(LiveUpdateEntryRequest $request, string $slug, int $id): JsonResponse
    {
        $article = $this->liveUpdateService->findLiveBlogBySlug($slug);
        $entry = $this->liveUpdateService->updateEntry($article, $id, $request->validated());

        return sendResponse(
            true,
            'Live update entry updated successfully',
            new ArticleLiveUpdateResource($entry),
            HttpStatus::HTTP_OK,
        );
    }

    public function destroyEntry(string $slug, int $id): JsonResponse
    {
        $article = $this->liveUpdateService->findLiveBlogBySlug($slug);
        $this->liveUpdateService->deleteEntry($article, $id);

        return sendResponse(
            true,
            'Live update entry deleted successfully',
            null,
            HttpStatus::HTTP_OK,
        );
    }

    public function startLive(string $slug): JsonResponse
    {
        $article = $this->liveUpdateService->findLiveBlogBySlug($slug);
        $updated = $this->liveUpdateService->startLiveCoverage($article);

        return sendResponse(
            true,
            'Live coverage started.',
            new ArticleResource($updated->load(['liveUpdates.user'])),
            HttpStatus::HTTP_OK,
        );
    }

    public function endLive(string $slug): JsonResponse
    {
        $article = $this->liveUpdateService->findLiveBlogBySlug($slug);
        $updated = $this->liveUpdateService->endLiveCoverage($article);

        return sendResponse(
            true,
            'Live coverage ended.',
            new ArticleResource($updated->load(['liveUpdates.user'])),
            HttpStatus::HTTP_OK,
        );
    }
}
