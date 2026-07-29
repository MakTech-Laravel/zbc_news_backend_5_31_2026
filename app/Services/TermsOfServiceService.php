<?php

namespace App\Services;

use App\Models\TermsOfServiceSetting;
use Database\Seeders\TermsOfServiceSeeder;

class TermsOfServiceService
{
    public function getOrCreate(): TermsOfServiceSetting
    {
        $settings = TermsOfServiceSetting::query()->first();

        if ($settings) {
            return $settings;
        }

        return TermsOfServiceSetting::query()->create(TermsOfServiceSeeder::defaultContent());
    }

    public function update(array $data): TermsOfServiceSetting
    {
        $settings = $this->getOrCreate();
        $settings->update($data);

        return $settings->fresh();
    }
}
