<?php

namespace App\Services;

use App\Models\PrivacyPolicySetting;
use Database\Seeders\PrivacyPolicySeeder;

class PrivacyPolicyService
{
    public function getOrCreate(): PrivacyPolicySetting
    {
        $settings = PrivacyPolicySetting::query()->first();

        if ($settings) {
            return $settings;
        }

        return PrivacyPolicySetting::query()->create(PrivacyPolicySeeder::defaultContent());
    }

    public function update(array $data): PrivacyPolicySetting
    {
        $settings = $this->getOrCreate();
        $settings->update($data);

        return $settings->fresh();
    }
}
