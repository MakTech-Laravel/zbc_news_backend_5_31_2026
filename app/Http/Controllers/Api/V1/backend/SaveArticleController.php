<?php

namespace App\Http\Controllers\Api\V1\backend;

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
            HttpStatus::HTTP_OK
        );
    }

    public function toggle(SaveArticleRequest $request)
    {
        $result = $this->saveArticleService->toggle(
            $request->article_id
        );

        return sendResponse(
            true,
            $result['message'],
            [
                'saved' => $result['saved'],
            ],
            HttpStatus::HTTP_OK
        );
    }

    public function checkSaved(int $articleId)
    {
        return sendResponse(
            true,
            'Status retrieved successfully',
            [
                'saved' => $this->saveArticleService->isSaved($articleId),
            ],
            HttpStatus::HTTP_OK
        );
    }
}
