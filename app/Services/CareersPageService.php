<?php

namespace App\Services;

use App\Models\CareersPageSettings;
use Database\Seeders\CareersSeeder;

class CareersPageService
{
    public function getOrCreate(): CareersPageSettings
    {
        $settings = CareersPageSettings::query()->first();

        if ($settings) {
            return $settings;
        }

        return CareersPageSettings::query()->create(CareersSeeder::defaultPageContent());
    }

    public function update(array $data): CareersPageSettings
    {
        $settings = $this->getOrCreate();
        $settings->update($data);

        return $settings->fresh();
    }
}
