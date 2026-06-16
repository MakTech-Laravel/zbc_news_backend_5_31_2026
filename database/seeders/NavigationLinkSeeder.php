<?php

namespace Database\Seeders;

use App\Models\NavigationLink;
use Illuminate\Database\Seeder;

class NavigationLinkSeeder extends Seeder
{
    public function run(): void
    {
        $links = [
            ['location' => 'home_quick_links', 'label' => 'Trending', 'url' => '/', 'icon' => 'TrendingUp', 'sort_order' => 1, 'is_active' => true],
            ['location' => 'home_quick_links', 'label' => 'Most Read', 'url' => '/', 'icon' => 'BarChart3', 'sort_order' => 2, 'is_active' => true],
            ['location' => 'home_quick_links', 'label' => 'Live Updates', 'url' => '/', 'icon' => 'Radio', 'sort_order' => 3, 'is_active' => true],
            ['location' => 'home_quick_links', 'label' => 'Editorial Picks', 'url' => '/', 'icon' => 'Star', 'sort_order' => 4, 'is_active' => true],
        ];

        foreach ($links as $link) {
            NavigationLink::query()->updateOrCreate(
                ['location' => $link['location'], 'label' => $link['label']],
                $link,
            );
        }
    }
}

