<?php

namespace App\Jobs;

use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\NewsletterDeliveryStatusAdminNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendNewsletterDeliveryStatusAdminEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $subscriberId,
        public readonly string $status,
        public readonly ?int $eventId = null,
        public readonly ?string $rawEvent = null,
    ) {}

    public function handle(NewsletterDeliveryStatusAdminNotifier $notifier): void
    {
        $subscriber = NewsletterSubscriber::query()->find($this->subscriberId);

        if (! $subscriber) {
            return;
        }

        try {
            $notifier->sendAdminEmail(
                $subscriber,
                $this->status,
                $this->eventId,
                $this->rawEvent,
            );
        } catch (\Throwable $exception) {
            Log::warning('Newsletter delivery status admin email failed.', [
                'subscriber_id' => $this->subscriberId,
                'status' => $this->status,
                'event_id' => $this->eventId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
