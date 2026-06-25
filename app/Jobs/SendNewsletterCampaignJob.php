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

class SendNewsletterCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $campaignId,
    ) {}

    public function handle(NewsletterService $newsletterService, UserNotificationService $notificationService): void
    {
        $campaign = NewsletterCampaign::query()->find($this->campaignId);

        if (!$campaign || $campaign->status !== 'sending') {
            return;
        }

        $newsletterService->recipientsQuery($campaign)
            ->orderBy('id')
            ->chunkById(config('newsletter.batch_size', 50), function ($subscribers) use ($campaign): void {
                foreach ($subscribers as $subscriber) {
                    SendNewsletterEmailJob::dispatch($campaign->id, $subscriber->id);
                }
            });

        $campaign->update(['status' => 'sent', 'sent_at' => now()]);

        $notificationService->dispatchNewsletterCampaignNotifications($campaign->fresh());
    }
}
