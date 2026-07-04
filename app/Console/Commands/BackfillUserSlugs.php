<?php

namespace App\Console\Commands;

use App\Services\UserService;
use Illuminate\Console\Command;

class BackfillUserSlugs extends Command
{
    protected $signature = 'users:backfill-slugs';

    protected $description = 'Generate unique public slugs for users missing them';

    public function handle(UserService $userService): int
    {
        $updated = $userService->backfillMissingUserSlugs();

        $this->info("Updated {$updated} user slug(s).");

        return self::SUCCESS;
    }
}
