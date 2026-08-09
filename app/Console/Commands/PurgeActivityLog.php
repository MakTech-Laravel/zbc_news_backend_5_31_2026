<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * Enforces the activity log retention policy on a rolling basis: each record is
 * removed on its own anniversary, so the log always exposes the most recent
 * `retention_months` of history rather than emptying all at once.
 *
 * Spatie's own `activitylog:clean` counts in days, which drifts from calendar
 * months on leap years — hence this command.
 */
class PurgeActivityLog extends Command
{
    protected $signature = 'activitylog:purge
                            {--months= : Override the retention window, in months}
                            {--chunk=1000 : Rows deleted per statement}
                            {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Delete activity log records older than the retention window';

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?? config('activitylog.retention_months', 12));

        if ($months < 1) {
            $this->error('The retention window must be at least 1 month.');

            return self::FAILURE;
        }

        $cutoff = now()->subMonths($months);
        $chunk = max(1, (int) $this->option('chunk'));

        if ($this->option('dry-run')) {
            $count = Activity::query()->where('created_at', '<', $cutoff)->count();

            $this->info("Dry run: {$count} record(s) older than {$cutoff->toDateTimeString()} would be deleted.");

            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            $removed = Activity::query()
                ->where('created_at', '<', $cutoff)
                ->limit($chunk)
                ->delete();

            $deleted += $removed;
        } while ($removed > 0);

        if ($deleted > 0) {
            // The purge itself is auditable: the log rows are gone, this is not.
            Log::info('Purged activity log records past the retention window.', [
                'deleted' => $deleted,
                'cutoff' => $cutoff->toDateTimeString(),
                'retention_months' => $months,
            ]);
        }

        $this->info("Deleted {$deleted} record(s) older than {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
