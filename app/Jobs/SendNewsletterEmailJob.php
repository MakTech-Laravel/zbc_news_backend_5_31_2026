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

        if (!$campaign || !$subscriber || $subscriber->status !== 'verified') {
            return;
        }

        try {
            $newsletterService->sendCampaignEmail($campaign, $subscriber);
        } catch (\Throwable) {
            $newsletterService->incrementFailed($campaign);
            throw;
        }
    }
}
