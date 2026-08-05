<?php

namespace App\Jobs;

use App\Models\NewsletterCampaign;
use App\Services\Newsletter\NewsletterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendNewsletterCampaignSentAdminEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $campaignId,
    ) {}

    public function handle(NewsletterService $newsletterService): void
    {
        $campaign = NewsletterCampaign::query()->find($this->campaignId);

        if (! $campaign || $campaign->status !== 'sent') {
            return;
        }

        try {
            $newsletterService->sendCampaignSentAdminEmail($campaign);
        } catch (\Throwable $exception) {
            Log::warning('Newsletter campaign sent admin email failed.', [
                'campaign_id' => $this->campaignId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
