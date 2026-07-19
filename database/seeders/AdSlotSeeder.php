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
            ['slot_key' => 'home_banner_top', 'name' => 'Home Banner Top', 'placement' => 'home', 'provider' => 'manual', 'is_active' => false],
            ['slot_key' => 'home_banner_middle', 'name' => 'Home Banner Middle', 'placement' => 'home', 'provider' => 'manual', 'is_active' => false],
            ['slot_key' => 'home_banner_bottom', 'name' => 'Home Banner Bottom', 'placement' => 'home', 'provider' => 'manual', 'is_active' => false],
            ['slot_key' => 'article_details_inline', 'name' => 'Article Details Inline', 'placement' => 'article_details', 'provider' => 'manual', 'is_active' => false],
            ['slot_key' => 'article_details_bottom', 'name' => 'Article Details Bottom', 'placement' => 'article_details', 'provider' => 'manual', 'is_active' => false],
            ['slot_key' => 'content_banner_primary', 'name' => 'Content Banner Primary', 'placement' => 'content', 'provider' => 'manual', 'is_active' => false],
        ];

        foreach ($slots as $slot) {
            AdSlot::query()->updateOrCreate(
                ['slot_key' => $slot['slot_key']],
                $slot,
            );
        }
    }
}
