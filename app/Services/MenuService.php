<?php

namespace App\Services;

use App\Enums\MenuItemTarget;
use App\Enums\MenuItemType;
use App\Enums\MenuStatus;
use App\Models\ArticleCategory;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuLocation;
use App\Services\Menus\MenuItemTypeRegistry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MenuService
{
    public function __construct(
        private readonly MenuItemTypeRegistry $typeRegistry,
    ) {}

    public function listMenus(bool $withTrashed = false): EloquentCollection
    {
        $query = Menu::query()->withCount('items')->with('locations')->orderBy('name');

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->get();
    }

    public function getById(int $id, bool $withTrashed = false): Menu
    {
        $query = Menu::query()->with([
            'locations',
            'items' => fn ($q) => $q->orderBy('sort_order'),
            'items.category',
        ]);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($id);
    }

    public function getBySlug(string $slug, bool $withTrashed = false): Menu
    {
        $query = Menu::query()->with([
            'locations',
            'items' => fn ($q) => $q->orderBy('sort_order'),
            'items.category',
        ]);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->where('slug', $slug)->firstOrFail();
    }

    public function createMenu(array $data): Menu
    {
        $slug = $this->uniqueSlug($data['slug'] ?? $data['name']);

        $menu = Menu::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? MenuStatus::ACTIVE->value,
        ]);

        if (! empty($data['location_keys']) && is_array($data['location_keys'])) {
            $this->assignLocations($menu, $data['location_keys']);
        }

        Menu::flushPublicCache();

        return $this->getById($menu->id);
    }

    public function updateMenu(Menu $menu, array $data): Menu
    {
        if (isset($data['name'])) {
            $menu->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $menu->description = $data['description'];
        }
        if (isset($data['status'])) {
            $menu->status = $data['status'];
        }
        if (isset($data['slug'])) {
            $menu->slug = $this->uniqueSlug($data['slug'], $menu->id);
        }

        $menu->save();

        if (array_key_exists('location_keys', $data) && is_array($data['location_keys'])) {
            $this->assignLocations($menu, $data['location_keys']);
        }

        Menu::flushPublicCache();

        return $this->getById($menu->id);
    }

    public function deleteMenu(Menu $menu): void
    {
        MenuLocation::query()->where('menu_id', $menu->id)->update(['menu_id' => null]);
        MenuItem::query()->where('menu_id', $menu->id)->get()->each->delete();
        $menu->delete();
        Menu::flushPublicCache();
    }

    public function restoreMenu(Menu $menu): Menu
    {
        $menu->restore();
        MenuItem::onlyTrashed()->where('menu_id', $menu->id)->restore();
        Menu::flushPublicCache();

        return $this->getById($menu->id);
    }

    public function forceDeleteMenu(Menu $menu): void
    {
        MenuLocation::query()->where('menu_id', $menu->id)->update(['menu_id' => null]);
        MenuItem::withTrashed()->where('menu_id', $menu->id)->get()->each->forceDelete();
        $menu->forceDelete();
        Menu::flushPublicCache();
    }

    /**
     * @param  list<string>  $locationKeys
     */
    public function assignLocations(Menu $menu, array $locationKeys): void
    {
        // Detach this menu from locations not in the new set.
        MenuLocation::query()
            ->where('menu_id', $menu->id)
            ->whereNotIn('key', $locationKeys)
            ->update(['menu_id' => null]);

        foreach ($locationKeys as $key) {
            $location = MenuLocation::query()->where('key', $key)->first();
            if (! $location) {
                throw new InvalidArgumentException("Unknown menu location [{$key}].");
            }
            // One menu per location (WordPress-style).
            $location->menu_id = $menu->id;
            $location->save();
        }

        Menu::flushPublicCache();
    }

    public function listLocations(): EloquentCollection
    {
        return MenuLocation::query()
            ->with('menu:id,name,slug,status')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function createLocation(array $data): MenuLocation
    {
        $key = Str::slug($data['key'] ?? $data['name'], '_');
        if (MenuLocation::query()->where('key', $key)->exists()) {
            throw new InvalidArgumentException('A location with this key already exists.');
        }

        $maxOrder = (int) MenuLocation::query()->max('sort_order');

        $location = MenuLocation::query()->create([
            'key' => $key,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'render_style' => $data['render_style'] ?? 'standard',
            'menu_id' => $data['menu_id'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
        ]);

        Menu::flushPublicCache();

        return $location->load('menu:id,name,slug,status');
    }

    public function updateLocation(MenuLocation $location, array $data): MenuLocation
    {
        if (isset($data['name'])) {
            $location->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $location->description = $data['description'];
        }
        if (isset($data['render_style'])) {
            $location->render_style = $data['render_style'];
        }
        if (array_key_exists('menu_id', $data)) {
            $location->menu_id = $data['menu_id'];
        }
        if (array_key_exists('is_active', $data)) {
            $location->is_active = (bool) $data['is_active'];
        }
        if (isset($data['sort_order'])) {
            $location->sort_order = (int) $data['sort_order'];
        }

        $location->save();
        Menu::flushPublicCache();

        return $location->load('menu:id,name,slug,status');
    }

    public function deleteLocation(MenuLocation $location): void
    {
        $location->delete();
        Menu::flushPublicCache();
    }

    public function createItem(Menu $menu, array $data): MenuItem
    {
        $type = (string) ($data['type'] ?? '');
        $prepared = $this->typeRegistry->get($type)->prepare($data);

        $parentId = $data['parent_id'] ?? null;
        if ($parentId !== null) {
            $parent = MenuItem::query()->where('menu_id', $menu->id)->findOrFail($parentId);
            $parentId = $parent->id;
        }

        $maxOrder = (int) MenuItem::query()
            ->where('menu_id', $menu->id)
            ->where('parent_id', $parentId)
            ->max('sort_order');

        $item = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => $parentId,
            'type' => $prepared['type'],
            'label' => $prepared['label'],
            'url' => $prepared['url'] ?? null,
            'target' => $data['target'] ?? MenuItemTarget::SELF->value,
            'icon' => $data['icon'] ?? null,
            'reference_type' => $prepared['reference_type'] ?? null,
            'reference_id' => $prepared['reference_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'meta' => $data['meta'] ?? null,
        ]);

        $includeChildren = (bool) ($data['include_children'] ?? false);
        if ($includeChildren && $type === MenuItemType::CATEGORY->value && $item->reference_id) {
            $this->syncCategoryChildren($item, true);
        }

        Menu::flushPublicCache();

        return $item->load('category', 'children');
    }

    public function updateItem(MenuItem $item, array $data): MenuItem
    {
        $type = (string) ($data['type'] ?? $item->type);
        $merged = array_merge([
            'label' => $item->label,
            'url' => $item->url,
            'reference_id' => $item->reference_id,
            'category_id' => $item->reference_id,
        ], $data);

        $prepared = $this->typeRegistry->get($type)->prepare($merged);

        if (array_key_exists('parent_id', $data)) {
            $parentId = $data['parent_id'];
            if ($parentId !== null) {
                if ((int) $parentId === (int) $item->id) {
                    throw new InvalidArgumentException('A menu item cannot be its own parent.');
                }
                $parent = MenuItem::query()->where('menu_id', $item->menu_id)->findOrFail($parentId);
                if ($this->isDescendantOf($parent, $item->id)) {
                    throw new InvalidArgumentException('Cannot move a menu item under its own descendant.');
                }
                $item->parent_id = $parent->id;
            } else {
                $item->parent_id = null;
            }
        }

        $item->type = $prepared['type'];
        $item->label = $prepared['label'];
        $item->url = $prepared['url'] ?? null;
        $item->reference_type = $prepared['reference_type'] ?? null;
        $item->reference_id = $prepared['reference_id'] ?? null;

        if (isset($data['target'])) {
            $item->target = $data['target'];
        }
        if (array_key_exists('icon', $data)) {
            $item->icon = $data['icon'];
        }
        if (array_key_exists('is_active', $data)) {
            $item->is_active = (bool) $data['is_active'];
        }
        if (array_key_exists('meta', $data)) {
            $item->meta = $data['meta'];
        }
        if (isset($data['sort_order'])) {
            $item->sort_order = (int) $data['sort_order'];
        }

        $item->save();

        if (array_key_exists('include_children', $data) && $item->type === MenuItemType::CATEGORY->value) {
            $this->syncCategoryChildren($item, (bool) $data['include_children']);
        }

        Menu::flushPublicCache();

        return $item->fresh(['category', 'children']);
    }

    public function deleteItem(MenuItem $item): void
    {
        $item->delete();
        Menu::flushPublicCache();
    }

    public function restoreItem(MenuItem $item): MenuItem
    {
        $item->restore();
        Menu::flushPublicCache();

        return $item->fresh(['category', 'children']);
    }

    public function forceDeleteItem(MenuItem $item): void
    {
        $item->forceDelete();
        Menu::flushPublicCache();
    }

    /**
     * Reorder siblings under the same parent.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorderItems(Menu $menu, array $orderedIds, ?int $parentId = null): EloquentCollection
    {
        $items = MenuItem::query()
            ->where('menu_id', $menu->id)
            ->where('parent_id', $parentId)
            ->whereIn('id', $orderedIds)
            ->get()
            ->keyBy('id');

        if ($items->count() !== count($orderedIds)) {
            throw new InvalidArgumentException('One or more menu items are invalid for this parent.');
        }

        DB::transaction(function () use ($orderedIds, $items) {
            foreach ($orderedIds as $index => $id) {
                $items[$id]->update(['sort_order' => $index + 1]);
            }
        });

        Menu::flushPublicCache();

        return MenuItem::query()
            ->where('menu_id', $menu->id)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Bulk replace hierarchy: each node { id, parent_id, sort_order }.
     *
     * @param  list<array{id:int,parent_id:?int,sort_order:int}>  $nodes
     */
    public function syncTree(Menu $menu, array $nodes): Menu
    {
        $existingIds = MenuItem::query()->where('menu_id', $menu->id)->pluck('id')->all();
        $payloadIds = array_map(fn ($n) => (int) $n['id'], $nodes);

        if (count(array_diff($payloadIds, $existingIds)) > 0) {
            throw new InvalidArgumentException('Tree sync includes unknown menu item ids.');
        }

        DB::transaction(function () use ($nodes, $menu) {
            foreach ($nodes as $node) {
                MenuItem::query()
                    ->where('menu_id', $menu->id)
                    ->where('id', (int) $node['id'])
                    ->update([
                        'parent_id' => $node['parent_id'] ?? null,
                        'sort_order' => (int) ($node['sort_order'] ?? 0),
                    ]);
            }
        });

        Menu::flushPublicCache();

        return $this->getById($menu->id);
    }

    /**
     * Nested tree for admin or public consumption.
     *
     * @return list<array<string, mixed>>
     */
    public function buildTree(Menu $menu, bool $activeOnly = false): array
    {
        $items = MenuItem::query()
            ->where('menu_id', $menu->id)
            ->with('category')
            ->orderBy('sort_order')
            ->get();

        if ($activeOnly) {
            $items = $items->where('is_active', true)->values();
        }

        return $this->nestItems($items);
    }

    public function getPublicByLocation(string $locationKey): ?array
    {
        $cacheKey = Menu::CACHE_PREFIX.'location:'.$locationKey;
        Menu::rememberPublicKey($cacheKey);

        return Cache::remember($cacheKey, Menu::CACHE_TTL, function () use ($locationKey) {
            $location = MenuLocation::query()
                ->where('key', $locationKey)
                ->where('is_active', true)
                ->with('menu')
                ->first();

            if (! $location || ! $location->menu) {
                return null;
            }

            $menu = $location->menu;
            if ($menu->status !== MenuStatus::ACTIVE) {
                return null;
            }

            return $this->formatPublicMenu($menu, $location);
        });
    }

    public function getPublicBySlug(string $slug): ?array
    {
        $cacheKey = Menu::CACHE_PREFIX.'slug:'.$slug;
        Menu::rememberPublicKey($cacheKey);

        return Cache::remember($cacheKey, Menu::CACHE_TTL, function () use ($slug) {
            $menu = Menu::query()->where('slug', $slug)->where('status', MenuStatus::ACTIVE)->first();
            if (! $menu) {
                return null;
            }

            return $this->formatPublicMenu($menu, null);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPublicMenu(Menu $menu, ?MenuLocation $location): array
    {
        return [
            'id' => $menu->id,
            'name' => $menu->name,
            'slug' => $menu->slug,
            'location' => $location ? [
                'key' => $location->key,
                'name' => $location->name,
                'render_style' => $location->render_style instanceof \BackedEnum
                    ? $location->render_style->value
                    : $location->render_style,
            ] : null,
            'items' => $this->buildTree($menu, true),
        ];
    }

    /**
     * @param  EloquentCollection<int, MenuItem>  $items
     * @return list<array<string, mixed>>
     */
    private function nestItems(EloquentCollection $items, ?int $parentId = null): array
    {
        $branch = [];

        foreach ($items->where('parent_id', $parentId)->sortBy('sort_order') as $item) {
            $handler = $this->typeRegistry->has($item->type)
                ? $this->typeRegistry->get($item->type)
                : null;

            $branch[] = [
                'id' => $item->id,
                'type' => $item->type,
                'label' => $handler ? $handler->resolveLabel($item) : $item->label,
                'url' => $handler ? $handler->resolveUrl($item) : $item->url,
                'target' => $item->target instanceof \BackedEnum ? $item->target->value : $item->target,
                'icon' => $item->icon,
                'reference_type' => $item->reference_type,
                'reference_id' => $item->reference_id,
                'sort_order' => (int) $item->sort_order,
                'is_active' => (bool) $item->is_active,
                'meta' => $item->meta,
                'parent_id' => $item->parent_id,
                'children' => $this->nestItems($items, $item->id),
            ];
        }

        return array_values($branch);
    }

    /**
     * When includeChildren is true, ensure each direct child category exists as a submenu item.
     * When false, remove auto-synced category children that have no custom meta overrides.
     */
    private function syncCategoryChildren(MenuItem $parentItem, bool $includeChildren): void
    {
        $categoryId = $parentItem->reference_id;
        if (! $categoryId) {
            return;
        }

        $children = ArticleCategory::query()
            ->where('parent_id', $categoryId)
            ->orderBy('sort_order')
            ->get();

        if (! $includeChildren) {
            return;
        }

        $existing = MenuItem::query()
            ->where('menu_id', $parentItem->menu_id)
            ->where('parent_id', $parentItem->id)
            ->where('type', MenuItemType::CATEGORY->value)
            ->get()
            ->keyBy('reference_id');

        $order = (int) MenuItem::query()
            ->where('menu_id', $parentItem->menu_id)
            ->where('parent_id', $parentItem->id)
            ->max('sort_order');

        foreach ($children as $child) {
            if ($existing->has($child->id)) {
                continue;
            }
            $order++;
            MenuItem::query()->create([
                'menu_id' => $parentItem->menu_id,
                'parent_id' => $parentItem->id,
                'type' => MenuItemType::CATEGORY->value,
                'label' => $child->title,
                'url' => '/'.$child->slug,
                'target' => MenuItemTarget::SELF->value,
                'reference_type' => ArticleCategory::class,
                'reference_id' => $child->id,
                'sort_order' => $order,
                'is_active' => true,
                'meta' => ['auto_synced' => true],
            ]);
        }
    }

    private function isDescendantOf(MenuItem $candidate, int $ancestorId): bool
    {
        $current = $candidate;
        $guard = 0;
        while ($current->parent_id && $guard < 100) {
            if ((int) $current->parent_id === $ancestorId) {
                return true;
            }
            $current = MenuItem::query()->find($current->parent_id);
            if (! $current) {
                break;
            }
            $guard++;
        }

        return false;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            $base = 'menu';
        }

        $slug = $base;
        $i = 2;
        while (
            Menu::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
