<?php

namespace App\Jobs;

use App\Models\NewsletterCampaign;
use App\Services\Newsletter\NewsletterService;
use App\Services\UserNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeNewsletterCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $campaignId,
    ) {}

    public function handle(
        NewsletterService $newsletterService,
        UserNotificationService $notificationService,
    ): void {
        $campaign = NewsletterCampaign::query()->find($this->campaignId);

        if (!$campaign || $campaign->status !== 'sending') {
            return;
        }

        $newsletterService->markCampaignSent($campaign);

        $notificationService->dispatchNewsletterCampaignNotifications($campaign->fresh());
    }
}
