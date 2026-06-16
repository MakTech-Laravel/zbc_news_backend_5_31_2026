<?php

namespace Database\Seeders;

use App\Models\AdSlot;
use Illuminate\Database\Seeder;

class AdSlotSeeder extends Seeder
{
    public function run(): void
    {
        $slots = [
            ['slot_key' => 'left_sidebar_primary', 'name' => 'Left Sidebar Primary', 'placement' => 'left_sidebar', 'provider' => 'manual', 'is_active' => false],
            ['slot_key' => 'right_sidebar_primary', 'name' => 'Right Sidebar Primary', 'placement' => 'right_sidebar', 'provider' => 'manual', 'is_active' => false],
            ['slot_key' => 'article_details_inline', 'name' => 'Article Details Inline', 'placement' => 'article_details', 'provider' => 'manual', 'is_active' => false],
        ];

        foreach ($slots as $slot) {
            AdSlot::query()->updateOrCreate(
                ['slot_key' => $slot['slot_key']],
                $slot,
            );
        }
    }
}

