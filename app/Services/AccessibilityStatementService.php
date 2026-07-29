<?php

namespace App\Services;

use App\Models\AccessibilityStatementSetting;
use Database\Seeders\AccessibilityStatementSeeder;

class AccessibilityStatementService
{
    public function getOrCreate(): AccessibilityStatementSetting
    {
        $settings = AccessibilityStatementSetting::query()->first();

        if ($settings) {
            return $settings;
        }

        return AccessibilityStatementSetting::query()->create(AccessibilityStatementSeeder::defaultContent());
    }

    public function update(array $data): AccessibilityStatementSetting
    {
        $settings = $this->getOrCreate();
        $settings->update($data);

        return $settings->fresh();
    }
}
