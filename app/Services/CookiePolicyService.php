<?php

namespace App\Services;

use App\Models\CookiePolicySetting;
use Database\Seeders\CookiePolicySeeder;

class CookiePolicyService
{
    public function getOrCreate(): CookiePolicySetting
    {
        $settings = CookiePolicySetting::query()->first();

        if ($settings) {
            return $settings;
        }

        return CookiePolicySetting::query()->create(CookiePolicySeeder::defaultContent());
    }

    public function update(array $data): CookiePolicySetting
    {
        $settings = $this->getOrCreate();
        $settings->update($data);

        return $settings->fresh();
    }
}
