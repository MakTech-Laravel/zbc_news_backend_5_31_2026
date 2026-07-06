<?php

namespace App\Jobs;

use App\Mail\ContactInquiryReplyMail;
use App\Models\ContactInquiryReply;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendContactInquiryReplyEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $replyId,
    ) {}

    public function handle(): void
    {
        $reply = ContactInquiryReply::query()
            ->with('inquiry')
            ->find($this->replyId);

        if (! $reply || ! $reply->inquiry) {
            return;
        }

        Mail::to($reply->inquiry->email)->send(new ContactInquiryReplyMail($reply));
    }
}
