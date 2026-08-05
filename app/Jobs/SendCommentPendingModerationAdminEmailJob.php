<?php

namespace App\Jobs;

use App\Enums\CommentStatus;
use App\Models\ArticleComment;
use App\Services\CommentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendCommentPendingModerationAdminEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $commentId,
    ) {}

    public function handle(CommentService $commentService): void
    {
        $comment = ArticleComment::query()
            ->with(['user', 'article'])
            ->find($this->commentId);

        if (! $comment || $comment->status !== CommentStatus::PENDING) {
            return;
        }

        try {
            $commentService->sendPendingModerationAdminEmail($comment);
        } catch (\Throwable $exception) {
            Log::warning('Comment pending moderation admin email failed.', [
                'comment_id' => $this->commentId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
