<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ArticleRequest;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Services\ArticleService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Illuminate\Http\JsonResponse;

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

    public function trashed()
    {
        $articles = $this->articleService->getTrashedArticles();

        return sendResponse(
            true,
            'Trashed articles retrieved successfully',
            ArticleResource::collection($articles),
            HttpStatus::HTTP_OK,
        );
    }

    public function store(ArticleRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image');
        }

        $article = $this->articleService->create($data);

        return sendResponse(
            true,
            'Article created successfully',
            new ArticleResource($article),
            HttpStatus::HTTP_CREATED,
        );
    }

    public function update(ArticleRequest $request, string $slug)
    {
        $data = $request->validated();

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image');
        }

        $updated = $this->articleService->update($slug, $data);

        return sendResponse(
            true,
            'Article updated successfully',
            new ArticleResource($updated),
            HttpStatus::HTTP_OK,
        );
    }
    
    public function destroy(string $slug)
    {
        $this->articleService->delete($slug);

        return sendResponse(
            true,
            'Article deleted successfully',
            null,
            HttpStatus::HTTP_OK,
        );
    }

    public function restore(string $slug)
    {
        $article = $this->articleService->restore($slug);

        return sendResponse(
            true,
            'Article restored successfully',
            new ArticleResource($article),
            HttpStatus::HTTP_OK,
        );
    }

    public function forceDelete(string $slug)
    {
        $this->articleService->forceDelete($slug);

        return sendResponse(
            true,
            'Article permanently deleted',
            null,
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

    public function activities(string $slug): JsonResponse
    {
        $activities = $this->articleService->getActivities($slug);

        return sendResponse(
            true,
            'Article activities retrieved successfully',
            $activities
        );
    }

    public function articlesByTag(Request $request, string $tagSlug)
    {
        $type = $request->query('type', 'latest');

        $articles = $this->articleService->getLatestArticleByTag($tagSlug, $type);

        return sendResponse(true, 'Articles retrieved successfully',
            ArticleResource::collection($articles), HttpStatus::HTTP_OK);
    }

}
