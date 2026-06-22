<?php

namespace App\Console\Commands;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillNotificationPreferences extends Command
{
    protected $signature = 'notifications:backfill-preferences';

    protected $description = 'Create default notification preference rows for users missing them';

    public function handle(): int
    {
        $created = 0;

        User::query()
            ->whereDoesntHave('notificationPreferences')
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$created) {
                foreach ($users as $user) {
                    NotificationPreference::ensureForUser($user);
                    $created++;
                }
            });

        $this->info("Created {$created} notification preference row(s).");

        return self::SUCCESS;
    }
}
