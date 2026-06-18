<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreArticleCommentRequest;
use App\Http\Resources\Api\V1\ArticleCommentResource;
use App\Services\ArticleService;
use App\Services\CommentService;
use App\Services\SiteSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class CommentController extends Controller
{
    public function __construct(
        private readonly CommentService $commentService,
        private readonly ArticleService $articleService,
        private readonly SiteSettingsService $siteSettingsService,
    ) {}

    public function index(string $slug): JsonResponse
    {
        if (! $this->siteSettingsService->commentsAllowed()) {
            return sendResponse(true, 'Comments are disabled.', [
                'comments' => [],
                'count' => 0,
            ], HttpStatus::HTTP_OK);
        }

        $article = $this->articleService->getPublishedBySlug($slug);
        $comments = $this->commentService->listApprovedForArticle($article);

        return sendResponse(
            true,
            'Comments retrieved successfully.',
            [
                'comments' => ArticleCommentResource::collection($comments),
                'count' => $this->commentService->countApprovedForArticle($article->id),
            ],
            HttpStatus::HTTP_OK,
        );
    }

    public function store(StoreArticleCommentRequest $request, string $slug): JsonResponse
    {
        $article = $this->articleService->getPublishedBySlug($slug);
        $comment = $this->commentService->store(
            $article,
            $request->validated(),
            $request->user('api'),
            $request,
        );

        $message = $comment->status->value === 'approved'
            ? 'Comment posted successfully.'
            : 'Comment submitted and is awaiting moderation.';

        return sendResponse(
            true,
            $message,
            new ArticleCommentResource($comment),
            HttpStatus::HTTP_CREATED,
            [
                'pending_moderation' => $comment->status->value === 'pending',
            ],
        );
    }
}
