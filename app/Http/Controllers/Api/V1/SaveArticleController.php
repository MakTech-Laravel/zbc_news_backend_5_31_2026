<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveArticleRequest;
use App\Http\Resources\Api\V1\SaveArticleResource;
use App\Services\SaveArticleService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SaveArticleController extends Controller
{
    public function __construct(
        private readonly SaveArticleService $saveArticleService
    ) {}

    public function index()
    {
        $saveArticles = $this->saveArticleService->getAll();
        return sendResponse(
            true,
            'Save articles retrieved successfully',
            SaveArticleResource::collection($saveArticles),
            HttpStatus::HTTP_OK,
        );
    }

    public function store(SaveArticleRequest $request)
    {
        $saveArticle = $this->saveArticleService->create($request->article_id);
        return sendResponse(
            true,
            'Save article created successfully',
            new SaveArticleResource($saveArticle),
            HttpStatus::HTTP_CREATED,
        );
    }

    public function destroy(string $id)
    {
        $result = $this->saveArticleService->delete($id);

        return sendResponse(
            $result['success'],
            $result['message'],
            null,
            $result['success']
                ? HttpStatus::HTTP_OK
                : HttpStatus::HTTP_NOT_FOUND
        );
    }
}
