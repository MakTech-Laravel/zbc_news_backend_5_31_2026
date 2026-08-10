<?php

namespace App\Console\Commands;

use App\Models\ArticleRevision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Enforces article revision retention on a rolling basis: each version is
 * removed on its own anniversary, so history always keeps the most recent
 * `retention_months` rather than wiping everything at once.
 */
class PurgeArticleRevisions extends Command
{
    protected $signature = 'article-revisions:purge
                            {--months= : Override the retention window, in months}
                            {--chunk=500 : Rows deleted per statement}
                            {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Delete article revision records older than the retention window';

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?? config('article_revisions.retention_months', 12));

        if ($months < 1) {
            $this->error('The retention window must be at least 1 month.');

            return self::FAILURE;
        }

        $cutoff = now()->subMonths($months);
        $chunk = max(1, (int) $this->option('chunk'));

        if ($this->option('dry-run')) {
            $count = ArticleRevision::query()->where('created_at', '<', $cutoff)->count();

            $this->info("Dry run: {$count} revision(s) older than {$cutoff->toDateTimeString()} would be deleted.");

            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            // Chunk deletes keep large revision tables from locking too long.
            $removed = ArticleRevision::query()
                ->where('created_at', '<', $cutoff)
                ->limit($chunk)
                ->delete();

            $deleted += $removed;
        } while ($removed > 0);

        if ($deleted > 0) {
            Log::info('Purged article revisions past the retention window.', [
                'deleted' => $deleted,
                'cutoff' => $cutoff->toDateTimeString(),
                'retention_months' => $months,
            ]);
        }

        $this->info("Deleted {$deleted} revision(s) older than {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
