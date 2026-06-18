<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArticleCommentResource;
use App\Models\ArticleComment;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminCommentController extends Controller
{
    public function __construct(
        private readonly CommentService $commentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->commentService->adminList(
            $request->query('status'),
            $request->query('search'),
            max(1, min((int) $request->query('per_page', 15), 50)),
        );

        return sendResponse(
            true,
            'Comments retrieved successfully.',
            ArticleCommentResource::collection($paginator),
            HttpStatus::HTTP_OK,
            [
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        );
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $comment = ArticleComment::query()->findOrFail($id);
        $comment = $this->commentService->approve($comment, $request->user());

        return sendResponse(
            true,
            'Comment approved successfully.',
            new ArticleCommentResource($comment),
            HttpStatus::HTTP_OK,
        );
    }

    public function reject(int $id): JsonResponse
    {
        $comment = ArticleComment::query()->findOrFail($id);
        $comment = $this->commentService->reject($comment);

        return sendResponse(
            true,
            'Comment rejected successfully.',
            new ArticleCommentResource($comment),
            HttpStatus::HTTP_OK,
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $comment = ArticleComment::query()->findOrFail($id);
        $this->commentService->delete($comment);

        return sendResponse(
            true,
            'Comment deleted successfully.',
            null,
            HttpStatus::HTTP_OK,
        );
    }
}
