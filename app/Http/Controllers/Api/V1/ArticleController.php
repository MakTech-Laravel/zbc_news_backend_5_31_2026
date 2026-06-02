<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ArticleRequest;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Services\ArticleService;
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
}
