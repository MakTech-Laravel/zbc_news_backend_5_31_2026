<?php

namespace App\Jobs;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\NewsletterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNewsletterEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $campaignId,
        public int $subscriberId,
    ) {}

    public function handle(NewsletterService $newsletterService): void
    {
        $campaign = NewsletterCampaign::query()->find($this->campaignId);
        $subscriber = NewsletterSubscriber::query()->find($this->subscriberId);

        if (!$campaign || !$subscriber || $subscriber->status === 'unsubscribed') {
            return;
        }

        if ($campaign->premium_only && $subscriber->status !== 'verified') {
            return;
        }

        try {
            $newsletterService->sendCampaignEmail($campaign, $subscriber);
        } catch (\Throwable $exception) {
            $newsletterService->incrementFailed($campaign);
            throw $exception;
        }

        $delayMs = (int) config('newsletter.send_delay_ms', 500);

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }
}
