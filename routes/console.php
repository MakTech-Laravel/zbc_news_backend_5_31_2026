<?php

use App\Jobs\ProcessScheduledNewsletterCampaigns;
use App\Jobs\PublishScheduledArticles;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Publish scheduled articles every minute
Schedule::job(new PublishScheduledArticles)->everyMinute();
Schedule::job(new ProcessScheduledNewsletterCampaigns)->everyMinute();

// Keep the sitemap caches warm (lazy TTLs still rebuild on demand: general 1h,
// news 10m). Hourly warm avoids a slow first request after expiry.
Schedule::command('sitemap:refresh')->hourly();
