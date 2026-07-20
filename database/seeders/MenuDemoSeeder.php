<?php

namespace Database\Seeders;

use App\Enums\MenuItemTarget;
use App\Enums\MenuItemType;
use App\Enums\MenuStatus;
use App\Models\ArticleCategory;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuLocation;
use App\Services\MenuService;
use Illuminate\Database\Seeder;

class MenuDemoSeeder extends Seeder
{
    public function run(): void
    {
        /** @var MenuService $menus */
        $menus = app(MenuService::class);

        // Clear previous default menus (leave custom admin-created menus intact).
        $demoSlugs = [
            'primary-menu',
            'primary-navigation',
            'mobile-navigation',
            'sidebarmenu',
            'footer-links',
        ];
        foreach ($demoSlugs as $slug) {
            $existing = Menu::withTrashed()->where('slug', $slug)->first();
            if ($existing) {
                MenuLocation::query()->where('menu_id', $existing->id)->update(['menu_id' => null]);
                MenuItem::withTrashed()->where('menu_id', $existing->id)->forceDelete();
                $existing->forceDelete();
            }
        }

        // 1) Primary Menu (unassigned, empty) — keep as an initial starter menu.
        $primaryMenu = $menus->createMenu([
            'name' => 'Primary Menu',
            'slug' => 'primary-menu',
            'description' => 'Starter primary menu (unassigned by default)',
            'status' => MenuStatus::ACTIVE->value,
            'location_keys' => [],
        ]);

        $roots = ArticleCategory::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(8)
            ->get();

        // 2) Primary Navigation (assigned to header_primary + mega_menu)
        // Keep this consistent with screenshot defaults.
        $primary = $menus->createMenu([
            'name' => 'Primary Navigation',
            'slug' => 'primary-navigation',
            'description' => 'Main desktop header menu',
            'status' => MenuStatus::ACTIVE->value,
            'location_keys' => ['header_primary', 'mega_menu'],
        ]);

        $primaryDefaults = [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Breaking News', 'url' => '/breaking-news'],
            ['label' => 'World', 'url' => '/world'],
            ['label' => 'Politics', 'url' => '/politics'],
            ['label' => 'Business', 'url' => '/business'],
            ['label' => 'Technology', 'url' => '/technology'],
            ['label' => 'Health', 'url' => '/health'],
            ['label' => 'Sports', 'url' => '/sports'],
            ['label' => 'Entertainment', 'url' => '/entertainment'],
            ['label' => 'Opinion', 'url' => '/opinion'],
            ['label' => 'Video', 'url' => '/video'],
        ];
        foreach ($primaryDefaults as $index => $item) {
            $menus->createItem($primary, [
                'type' => MenuItemType::CUSTOM->value,
                'label' => $item['label'],
                'url' => $item['url'],
                'target' => MenuItemTarget::SELF->value,
                'sort_order' => $index + 1,
            ]);
        }

        // 3) Mobile Navigation (assigned to header_mobile, with dropdown-capable items).
        $mobile = $menus->createMenu([
            'name' => 'Mobile Navigation',
            'slug' => 'mobile-navigation',
            'description' => 'Hamburger / drawer menu',
            'status' => MenuStatus::ACTIVE->value,
            'location_keys' => ['header_mobile'],
        ]);

        foreach ($roots->take(5) as $index => $category) {
            $menus->createItem($mobile, [
                'type' => MenuItemType::CATEGORY->value,
                'category_id' => $category->id,
                'include_children' => true,
                'sort_order' => $index + 1,
            ]);
        }

        $menus->createItem($mobile, [
            'type' => MenuItemType::CUSTOM->value,
            'label' => 'Home',
            'url' => '/',
            'target' => MenuItemTarget::SELF->value,
            'icon' => 'Home',
            'sort_order' => 0,
        ]);

        // 4) SidebarMenu (assigned to sidebar).
        // Standard location, defaulted without dropdown children.
        $sidebarMenu = $menus->createMenu([
            'name' => 'SidebarMenu',
            'slug' => 'sidebarmenu',
            'description' => 'Default sidebar menu',
            'status' => MenuStatus::ACTIVE->value,
            'location_keys' => ['sidebar'],
        ]);

        // Keep one simple starter entry.
        $menus->createItem($sidebarMenu, [
            'type' => MenuItemType::CUSTOM->value,
            'label' => 'Latest News',
            'url' => '/',
            'target' => MenuItemTarget::SELF->value,
            'sort_order' => 1,
        ]);

        // 5) Footer Links (assigned to footer).
        $footer = $menus->createMenu([
            'name' => 'Footer Links',
            'slug' => 'footer-links',
            'description' => 'Footer column links',
            'status' => MenuStatus::ACTIVE->value,
            'location_keys' => ['footer'],
        ]);

        foreach (
            [
                ['label' => 'Privacy Policy', 'url' => '/privacy'],
                ['label' => 'Terms of Use', 'url' => '/terms'],
                ['label' => 'Advertise', 'url' => '/advertise'],
                ['label' => 'Careers', 'url' => '/careers', 'target' => MenuItemTarget::BLANK->value],
                ['label' => 'Contact', 'url' => '/contact'],
            ] as $index => $link
        ) {
            $menus->createItem($footer, [
                'type' => MenuItemType::CUSTOM->value,
                'label' => $link['label'],
                'url' => $link['url'],
                'target' => $link['target'] ?? MenuItemTarget::SELF->value,
                'sort_order' => $index + 1,
            ]);
        }

        Menu::flushPublicCache();

        $this->command?->info('Default menus created and assigned to default locations.');
        $rows = [];
        foreach ([$primaryMenu, $primary, $mobile, $sidebarMenu, $footer] as $menu) {
            $fresh = Menu::with(['locations', 'items'])->find($menu->id);
            if (! $fresh) {
                $rows[] = [
                    $menu->name,
                    $menu->slug,
                    '',
                    0,
                ];
                continue;
            }

            $rows[] = [
                $fresh->name,
                $fresh->slug,
                $fresh->locations->pluck('key')->join(', '),
                $fresh->items()->count(),
            ];
        }

        $this->command?->table(
            ['Menu', 'Slug', 'Locations', 'Items'],
            $rows
        );
    }
}
