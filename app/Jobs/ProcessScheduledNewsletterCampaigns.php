<?php

namespace App\Jobs;

use App\Services\Newsletter\NewsletterService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessScheduledNewsletterCampaigns
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NewsletterService $newsletterService): void
    {
        $newsletterService->processDueScheduledCampaigns();
    }
}
