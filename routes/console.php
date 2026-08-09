<?php

use App\Jobs\PublishScheduledArticles;
use App\Jobs\ProcessScheduledNewsletterCampaigns;
use App\Jobs\ExpireBreakingNewsItems;
use App\Jobs\ProcessSubMenus;
use App\Jobs\PurgeDeletedAccounts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new PublishScheduledArticles)
    ->everyMinute()
    ->name('publish-scheduled-articles')
    ->withoutOverlapping();

Schedule::job(new ProcessScheduledNewsletterCampaigns)
    ->everyMinute()
    ->name('process-scheduled-newsletter-campaigns')
    ->withoutOverlapping();

Schedule::job(new ExpireBreakingNewsItems)
    ->everyMinute()
    ->name('expire-breaking-news-items')
    ->withoutOverlapping();

Schedule::job(new ProcessSubMenus)
    ->everyMinute()
    ->name('process-sub-menus')
    ->withoutOverlapping();

Schedule::job(new PurgeDeletedAccounts)
    ->daily()
    ->name('purge-deleted-accounts')
    ->withoutOverlapping();

Schedule::command('sitemap:refresh')
    ->hourly()
    ->name('sitemap-refresh')
    ->withoutOverlapping();

// Offset from the midnight account purge so two batch deletes never overlap.
Schedule::command('activitylog:purge')
    ->dailyAt('00:15')
    ->name('purge-activity-log')
    ->withoutOverlapping();
