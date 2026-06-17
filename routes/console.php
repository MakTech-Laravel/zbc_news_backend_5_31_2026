<?php

use App\Jobs\PublishScheduledArticles;
use App\Jobs\ProcessScheduledNewsletterCampaigns;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Publish scheduled articles every minute
Schedule::job(new PublishScheduledArticles)->everyMinute();
Schedule::job(new ProcessScheduledNewsletterCampaigns)->everyMinute();