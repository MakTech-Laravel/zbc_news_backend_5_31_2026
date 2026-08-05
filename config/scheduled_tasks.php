<?php

return [
    'publish-scheduled-articles' => [
        'label' => 'Publish scheduled articles',
        'type' => 'job',
        'job' => App\Jobs\PublishScheduledArticles::class,
    ],
    'process-scheduled-newsletter-campaigns' => [
        'label' => 'Process scheduled newsletter campaigns',
        'type' => 'job',
        'job' => App\Jobs\ProcessScheduledNewsletterCampaigns::class,
    ],
    'expire-breaking-news-items' => [
        'label' => 'Expire breaking news items',
        'type' => 'job',
        'job' => App\Jobs\ExpireBreakingNewsItems::class,
    ],
    'process-sub-menus' => [
        'label' => 'Process sub menus',
        'type' => 'job',
        'job' => App\Jobs\ProcessSubMenus::class,
    ],
    'purge-deleted-accounts' => [
        'label' => 'Purge deleted accounts',
        'type' => 'job',
        'job' => App\Jobs\PurgeDeletedAccounts::class,
    ],
    'sitemap-refresh' => [
        'label' => 'Sitemap refresh',
        'type' => 'command',
        'command' => 'sitemap:refresh',
    ],
];
