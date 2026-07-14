<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

class RefreshSitemapCache extends Command
{
    protected $signature = 'sitemap:refresh';

    protected $description = 'Rebuild and warm the general and news sitemap caches';

    public function handle(SitemapService $sitemap): int
    {
        $sitemap->refresh();

        $this->info('Sitemap caches refreshed.');

        return self::SUCCESS;
    }
}
