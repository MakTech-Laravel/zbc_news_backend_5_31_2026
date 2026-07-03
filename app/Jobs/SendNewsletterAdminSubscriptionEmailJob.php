<?php

namespace App\Jobs;

use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\NewsletterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendNewsletterAdminSubscriptionEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $subscriberId,
        public readonly bool $verified = false,
    ) {}

    public function handle(NewsletterService $newsletterService): void
    {
        $subscriber = NewsletterSubscriber::query()->find($this->subscriberId);

        if (!$subscriber) {
            return;
        }

        try {
            $newsletterService->sendAdminSubscriptionNotificationEmail($subscriber, $this->verified);
        } catch (\Throwable $exception) {
            Log::warning('Newsletter admin subscription email failed.', [
                'subscriber_id' => $this->subscriberId,
                'verified' => $this->verified,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
