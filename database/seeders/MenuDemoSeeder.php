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

        // Clear previous demo menus (keep custom ones the admin may have created
        // if they are not these known demo slugs).
        $demoSlugs = ['primary-navigation', 'mobile-navigation', 'footer-links'];
        foreach ($demoSlugs as $slug) {
            $existing = Menu::withTrashed()->where('slug', $slug)->first();
            if ($existing) {
                MenuLocation::query()->where('menu_id', $existing->id)->update(['menu_id' => null]);
                MenuItem::withTrashed()->where('menu_id', $existing->id)->forceDelete();
                $existing->forceDelete();
            }
        }

        $roots = ArticleCategory::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(6)
            ->get();

        // ── Primary Navigation ─────────────────────────────────────────────
        $primary = $menus->createMenu([
            'name' => 'Primary Navigation',
            'slug' => 'primary-navigation',
            'description' => 'Main desktop header menu',
            'status' => MenuStatus::ACTIVE->value,
            'location_keys' => ['header_primary', 'mega_menu'],
        ]);

        foreach ($roots->take(4) as $index => $category) {
            $menus->createItem($primary, [
                'type' => MenuItemType::CATEGORY->value,
                'category_id' => $category->id,
                'include_children' => true,
                'sort_order' => $index + 1,
            ]);
        }

        $menus->createItem($primary, [
            'type' => MenuItemType::CUSTOM->value,
            'label' => 'About ZBC',
            'url' => '/about',
            'target' => MenuItemTarget::SELF->value,
            'sort_order' => 5,
        ]);

        $menus->createItem($primary, [
            'type' => MenuItemType::CUSTOM->value,
            'label' => 'Contact',
            'url' => '/contact',
            'target' => MenuItemTarget::SELF->value,
            'sort_order' => 6,
        ]);

        // ── Mobile Navigation ──────────────────────────────────────────────
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

        // ── Footer Links ───────────────────────────────────────────────────
        $footer = $menus->createMenu([
            'name' => 'Footer Links',
            'slug' => 'footer-links',
            'description' => 'Footer column links',
            'status' => MenuStatus::ACTIVE->value,
            'location_keys' => ['footer', 'header_top_bar'],
        ]);

        foreach (
            [
                ['label' => 'Privacy Policy', 'url' => '/privacy'],
                ['label' => 'Terms of Use', 'url' => '/terms'],
                ['label' => 'Advertise', 'url' => '/advertise'],
                ['label' => 'Careers', 'url' => '/careers', 'target' => MenuItemTarget::BLANK->value],
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

        if ($roots->isNotEmpty()) {
            $menus->createItem($footer, [
                'type' => MenuItemType::CATEGORY->value,
                'category_id' => $roots->first()->id,
                'include_children' => false,
                'sort_order' => 10,
            ]);
        }

        // Sidebar gets primary as a fallback sample assignment via location update
        MenuLocation::query()->where('key', 'sidebar')->update(['menu_id' => $primary->id]);
        MenuLocation::query()->where('key', 'header_dropdown')->update(['menu_id' => $mobile->id]);

        Menu::flushPublicCache();

        $this->command?->info('Demo menus created and assigned to locations.');
        $this->command?->table(
            ['Menu', 'Slug', 'Locations', 'Items'],
            collect([$primary, $mobile, $footer])->map(function (Menu $menu) {
                $fresh = Menu::with(['locations', 'items'])->find($menu->id);

                return [
                    $fresh->name,
                    $fresh->slug,
                    $fresh->locations->pluck('key')->join(', '),
                    $fresh->items->count(),
                ];
            })->all()
        );
    }
}
