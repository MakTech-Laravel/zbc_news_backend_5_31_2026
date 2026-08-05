<?php

namespace App\Jobs;

use App\Models\ArticleComment;
use App\Services\CommentReplyMailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendCommentReplyEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $commentId,
    ) {}

    public function handle(CommentReplyMailService $mailService): void
    {
        $reply = ArticleComment::query()
            ->with(['user', 'article', 'parent.user'])
            ->find($this->commentId);

        if (! $reply || ! $reply->parent_id) {
            return;
        }

        try {
            $mailService->send($reply);
        } catch (\Throwable $exception) {
            Log::warning('Comment reply email failed.', [
                'comment_id' => $this->commentId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
