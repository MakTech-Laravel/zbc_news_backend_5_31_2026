<?php

namespace Database\Seeders;

use App\Enums\MenuRenderStyle;
use App\Models\MenuLocation;
use Illuminate\Database\Seeder;

class MenuLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            [
                'key' => 'header_primary',
                'name' => 'Primary Navigation',
                'description' => 'Main desktop header navigation',
                'render_style' => MenuRenderStyle::DROPDOWN->value,
                'sort_order' => 1,
            ],
            [
                'key' => 'header_mobile',
                'name' => 'Mobile Menu',
                'description' => 'Mobile drawer / hamburger menu',
                'render_style' => MenuRenderStyle::MOBILE->value,
                'sort_order' => 2,
            ],
            [
                'key' => 'header_top_bar',
                'name' => 'Top Bar',
                'description' => 'Utility links above the main header',
                'render_style' => MenuRenderStyle::STANDARD->value,
                'sort_order' => 3,
            ],
            [
                'key' => 'header_dropdown',
                'name' => 'Header Dropdown',
                'description' => 'Secondary header dropdown menu',
                'render_style' => MenuRenderStyle::DROPDOWN->value,
                'sort_order' => 4,
            ],
            [
                'key' => 'mega_menu',
                'name' => 'Mega Menu',
                'description' => 'Wide multi-column mega menu',
                'render_style' => MenuRenderStyle::MEGA->value,
                'sort_order' => 5,
            ],
            [
                'key' => 'sidebar',
                'name' => 'Sidebar Menu',
                'description' => 'Sidebar navigation block',
                'render_style' => MenuRenderStyle::STANDARD->value,
                'sort_order' => 6,
            ],
            [
                'key' => 'footer',
                'name' => 'Footer Menu',
                'description' => 'Primary footer link columns',
                'render_style' => MenuRenderStyle::FOOTER->value,
                'sort_order' => 7,
            ],
        ];

        foreach ($locations as $row) {
            MenuLocation::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'render_style' => $row['render_style'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
