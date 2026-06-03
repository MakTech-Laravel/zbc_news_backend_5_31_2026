<?php

namespace App\Services;

use App\Models\SiteSettings;
use Illuminate\Support\Facades\Storage;

class SiteSettingsService
{
    public function __construct(
        private SiteSettings $siteSettings
    ) {}

    public function getAll(): ?SiteSettings
    {
        return $this->siteSettings->first();
    }

    public function createOrUpdate(array $data): SiteSettings
    {
        $settings = $this->siteSettings->first();

        if ($settings && isset($data['site_logo']) && $settings->site_logo) {
            Storage::disk('public')->delete($settings->site_logo);
        }

        if ($settings) {
            $settings->update($data);
            return $settings->fresh();
        }

        return $this->siteSettings->create($data);
    }
}