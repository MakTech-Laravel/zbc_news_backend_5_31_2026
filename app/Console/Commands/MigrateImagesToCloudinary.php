<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\SiteSettings;
use App\Models\UserInformation;
use App\Services\StoredImageService;
use App\Support\MediaUrl;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class MigrateImagesToCloudinary extends Command
{
    protected $signature = 'images:migrate-to-cloudinary {--dry-run : List rows that would be migrated without making changes}';

    protected $description = 'Upload locally stored profile, article, and site logo images to Cloudinary';

    public function __construct(
        private readonly StoredImageService $storedImageService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        $tasks = [
            ['label' => 'user profile images', 'rows' => $this->profileImageRows()],
            ['label' => 'article featured images', 'rows' => $this->articleImageRows('featured_image')],
            ['label' => 'article open graph images', 'rows' => $this->articleImageRows('open_graph_image')],
            ['label' => 'site logos', 'rows' => $this->siteLogoRows()],
        ];

        foreach ($tasks as $task) {
            $this->info("Scanning {$task['label']}...");

            foreach ($task['rows'] as $row) {
                $result = $this->migrateRow($row, $dryRun);

                match ($result) {
                    'migrated' => $migrated++,
                    'skipped' => $skipped++,
                    'failed' => $failed++,
                };
            }
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete.' : 'Migration complete.');
        $this->line("Migrated: {$migrated}");
        $this->line("Skipped: {$skipped}");
        $this->line("Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return iterable<int, array{table: string, id: int|string, column: string, value: string, folder: string}>
     */
    private function profileImageRows(): iterable
    {
        return UserInformation::query()
            ->whereNotNull('profile_image')
            ->where('profile_image', '!=', '')
            ->get(['id', 'profile_image'])
            ->map(fn (UserInformation $row) => [
                'table' => 'user_information',
                'id' => $row->id,
                'column' => 'profile_image',
                'value' => $row->profile_image,
                'folder' => 'user_profiles',
            ]);
    }

    /**
     * @return iterable<int, array{table: string, id: int|string, column: string, value: string, folder: string}>
     */
    private function articleImageRows(string $column): iterable
    {
        $folder = $column === 'featured_image'
            ? 'articles/featured-images'
            : 'articles/og-images';

        return Article::withTrashed()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->get(['id', $column])
            ->map(fn (Article $row) => [
                'table' => 'articles',
                'id' => $row->id,
                'column' => $column,
                'value' => $row->{$column},
                'folder' => $folder,
            ]);
    }

    /**
     * @return iterable<int, array{table: string, id: int|string, column: string, value: string, folder: string}>
     */
    private function siteLogoRows(): iterable
    {
        return SiteSettings::query()
            ->whereNotNull('site_logo')
            ->where('site_logo', '!=', '')
            ->get(['id', 'site_logo'])
            ->map(fn (SiteSettings $row) => [
                'table' => 'site_settings',
                'id' => $row->id,
                'column' => 'site_logo',
                'value' => $row->site_logo,
                'folder' => 'site-logos',
            ]);
    }

    /**
     * @param  array{table: string, id: int|string, column: string, value: string, folder: string}  $row
     */
    private function migrateRow(array $row, bool $dryRun): string
    {
        $value = $row['value'];

        if (MediaUrl::isRemote($value)) {
            return 'skipped';
        }

        $localPath = $this->storedImageService->localDiskPath($value);

        if ($localPath === null) {
            $this->warn("Skipping {$row['table']}#{$row['id']} ({$row['column']}): local file not found for [{$value}]");

            return 'skipped';
        }

        if ($dryRun) {
            $this->line("[dry-run] Would migrate {$row['table']}#{$row['id']} ({$row['column']}) from {$localPath}");

            return 'migrated';
        }

        try {
            $file = new UploadedFile(
                $localPath,
                basename($localPath),
                mime_content_type($localPath) ?: null,
                null,
                true,
            );

            $cloudinaryUrl = $this->storedImageService->upload($file, $row['folder']);

            DB::table($row['table'])
                ->where('id', $row['id'])
                ->update([$row['column'] => $cloudinaryUrl]);

            $this->storedImageService->delete($value);

            $this->info("Migrated {$row['table']}#{$row['id']} ({$row['column']})");

            return 'migrated';
        } catch (\Throwable $e) {
            $this->error("Failed {$row['table']}#{$row['id']} ({$row['column']}): {$e->getMessage()}");

            return 'failed';
        }
    }
}
