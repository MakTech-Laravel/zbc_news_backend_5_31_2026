<?php

namespace App\Jobs;

use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\NewsletterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendNewsletterWelcomeEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $subscriberId,
    ) {}

    public function handle(NewsletterService $newsletterService): void
    {
        $subscriber = NewsletterSubscriber::query()->find($this->subscriberId);

        if (! $subscriber || $subscriber->status !== 'verified') {
            return;
        }

        try {
            $newsletterService->sendWelcomeEmail($subscriber);
        } catch (\Throwable $exception) {
            Log::warning('Newsletter welcome email failed.', [
                'subscriber_id' => $this->subscriberId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
