<?php

namespace App\Services;

use App\Models\SiteSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SiteSettingsService
{
    private const CACHE_KEY = 'site_settings_singleton';

    public function __construct(
        private SiteSettings $siteSettings
    ) {}

    public function getAll(): ?SiteSettings
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached instanceof SiteSettings) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget(self::CACHE_KEY);
        }

        $settings = $this->siteSettings->first();
        if ($settings) {
            Cache::put(self::CACHE_KEY, $settings, 3600);
        }

        return $settings;
    }

    public function getOrDefault(): SiteSettings
    {
        return $this->getAll() ?? new SiteSettings([
            'site_name'                 => 'ZBC News',
            'site_tag'                  => 'Breaking news and analysis from around the world',
            'timezone'                  => 'America/New_York',
            'language'                  => 'en',
            'posts_per_page'            => 10,
            'allow_comments'            => true,
            'authenticate_comment_only' => false,
            'auto_approve_known_users'  => false,
            'related_article'           => 3,
            'enable_comments'           => true,
            'default_post_format'       => 'Standard',
            'enable_auto_save'          => true,
            'require_featured_image'    => false,
            'enable_ai_writing'         => false,
        ]);
    }

    public function getPostsPerPage(): int
    {
        return max(1, (int) ($this->getOrDefault()->posts_per_page ?? 10));
    }

    public function getRelatedArticlesCount(): int
    {
        return max(0, (int) ($this->getOrDefault()->related_article ?? 3));
    }

    public function commentsAllowed(): bool
    {
        $settings = $this->getOrDefault();

        return (bool) ($settings->allow_comments && $settings->enable_comments);
    }

    public function createOrUpdate(array $data): SiteSettings
    {
        $settings = $this->siteSettings->first();

        if ($settings && isset($data['site_logo']) && $settings->site_logo) {
            Storage::disk('public')->delete($settings->site_logo);
        }

        if ($settings) {
            $settings->update($data);
            Cache::forget(self::CACHE_KEY);

            return $settings->fresh();
        }

        $created = $this->siteSettings->create($data);
        Cache::forget(self::CACHE_KEY);

        return $created;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
