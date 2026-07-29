<?php

namespace App\Services;

use App\Models\AboutUsSetting;
use Database\Seeders\AboutUsSeeder;

class AboutUsService
{
    public function getOrCreate(): AboutUsSetting
    {
        $settings = AboutUsSetting::query()->first();

        if ($settings) {
            return $settings;
        }

        return AboutUsSetting::query()->create(AboutUsSeeder::defaultContent());
    }

    public function update(array $data): AboutUsSetting
    {
        $settings = $this->getOrCreate();
        $settings->update($data);

        return $settings->fresh();
    }
}
