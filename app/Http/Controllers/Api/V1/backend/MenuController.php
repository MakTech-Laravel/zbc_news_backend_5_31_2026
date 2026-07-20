<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Enums\MenuItemTarget;
use App\Enums\MenuRenderStyle;
use App\Enums\MenuStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MenuItemResource;
use App\Http\Resources\Api\V1\MenuLocationResource;
use App\Http\Resources\Api\V1\MenuResource;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuLocation;
use App\Services\MenuService;
use App\Services\Menus\MenuItemTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class MenuController extends Controller
{
    public function __construct(
        private readonly MenuService $menuService,
        private readonly MenuItemTypeRegistry $typeRegistry,
    ) {}

    public function index(Request $request)
    {
        $menus = $this->menuService->listMenus($request->boolean('with_trashed'));

        return sendResponse(true, 'Menus retrieved successfully', MenuResource::collection($menus), HttpStatus::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', Rule::in(MenuStatus::values())],
            'location_keys' => ['nullable', 'array'],
            'location_keys.*' => ['string', 'exists:menu_locations,key'],
        ]);

        try {
            $menu = $this->menuService->createMenu($validated);
        } catch (InvalidArgumentException $e) {
            return sendResponse(false, $e->getMessage(), null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        return sendResponse(true, 'Menu created successfully', new MenuResource($menu), HttpStatus::HTTP_CREATED);
    }

    public function show(int $id)
    {
        $menu = $this->menuService->getById($id);

        return sendResponse(true, 'Menu retrieved successfully', new MenuResource($menu), HttpStatus::HTTP_OK);
    }

    public function showBySlug(string $slug)
    {
        $menu = $this->menuService->getBySlug($slug);

        return sendResponse(true, 'Menu retrieved successfully', new MenuResource($menu), HttpStatus::HTTP_OK);
    }

    public function tree(int $id)
    {
        $menu = $this->menuService->getById($id);
        $tree = $this->menuService->buildTree($menu);

        return sendResponse(true, 'Menu tree retrieved successfully', [
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'slug' => $menu->slug,
            ],
            'items' => $tree,
        ], HttpStatus::HTTP_OK);
    }

    public function update(Request $request, int $id)
    {
        $menu = $this->menuService->getById($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', Rule::in(MenuStatus::values())],
            'location_keys' => ['nullable', 'array'],
            'location_keys.*' => ['string', 'exists:menu_locations,key'],
        ]);

        try {
            $updated = $this->menuService->updateMenu($menu, $validated);
        } catch (InvalidArgumentException $e) {
            return sendResponse(false, $e->getMessage(), null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        return sendResponse(true, 'Menu updated successfully', new MenuResource($updated), HttpStatus::HTTP_OK);
    }

    public function destroy(int $id)
    {
        $menu = $this->menuService->getById($id);
        $this->menuService->deleteMenu($menu);

        return sendResponse(true, 'Menu deleted successfully', null, HttpStatus::HTTP_OK);
    }

    public function restore(int $id)
    {
        $menu = Menu::withTrashed()->findOrFail($id);
        $restored = $this->menuService->restoreMenu($menu);

        return sendResponse(true, 'Menu restored successfully', new MenuResource($restored), HttpStatus::HTTP_OK);
    }

    public function forceDelete(int $id)
    {
        $menu = Menu::withTrashed()->findOrFail($id);
        $this->menuService->forceDeleteMenu($menu);

        return sendResponse(true, 'Menu permanently deleted', null, HttpStatus::HTTP_OK);
    }

    public function itemTypes()
    {
        return sendResponse(true, 'Menu item types retrieved successfully', [
            'types' => $this->typeRegistry->options(),
            'render_styles' => MenuRenderStyle::options(),
            'targets' => array_map(
                fn ($v) => ['value' => $v, 'label' => $v === '_blank' ? 'New tab' : 'Same tab'],
                MenuItemTarget::values()
            ),
        ], HttpStatus::HTTP_OK);
    }

    // ── Items ──────────────────────────────────────────────────────────────

    public function storeItem(Request $request, int $menuId)
    {
        $menu = $this->menuService->getById($menuId);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in($this->typeRegistry->types())],
            'label' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2000'],
            'target' => ['nullable', 'string', Rule::in(MenuItemTarget::values())],
            'icon' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer', 'exists:article_categories,id'],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'include_children' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        try {
            $item = $this->menuService->createItem($menu, $validated);
        } catch (InvalidArgumentException $e) {
            return sendResponse(false, $e->getMessage(), null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        return sendResponse(true, 'Menu item created successfully', new MenuItemResource($item), HttpStatus::HTTP_CREATED);
    }

    public function updateItem(Request $request, int $itemId)
    {
        $item = MenuItem::query()->findOrFail($itemId);

        $validated = $request->validate([
            'type' => ['sometimes', 'string', Rule::in($this->typeRegistry->types())],
            'label' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2000'],
            'target' => ['nullable', 'string', Rule::in(MenuItemTarget::values())],
            'icon' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer', 'exists:article_categories,id'],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'include_children' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        try {
            $updated = $this->menuService->updateItem($item, $validated);
        } catch (InvalidArgumentException $e) {
            return sendResponse(false, $e->getMessage(), null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        return sendResponse(true, 'Menu item updated successfully', new MenuItemResource($updated), HttpStatus::HTTP_OK);
    }

    public function destroyItem(int $itemId)
    {
        $item = MenuItem::query()->findOrFail($itemId);
        $this->menuService->deleteItem($item);

        return sendResponse(true, 'Menu item deleted successfully', null, HttpStatus::HTTP_OK);
    }

    public function restoreItem(int $itemId)
    {
        $item = MenuItem::withTrashed()->findOrFail($itemId);
        $restored = $this->menuService->restoreItem($item);

        return sendResponse(true, 'Menu item restored successfully', new MenuItemResource($restored), HttpStatus::HTTP_OK);
    }

    public function forceDeleteItem(int $itemId)
    {
        $item = MenuItem::withTrashed()->findOrFail($itemId);
        $this->menuService->forceDeleteItem($item);

        return sendResponse(true, 'Menu item permanently deleted', null, HttpStatus::HTTP_OK);
    }

    public function reorderItems(Request $request, int $menuId)
    {
        $menu = $this->menuService->getById($menuId);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:menu_items,id'],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
        ]);

        try {
            $items = $this->menuService->reorderItems(
                $menu,
                $validated['ids'],
                $validated['parent_id'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            return sendResponse(false, $e->getMessage(), null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        return sendResponse(true, 'Menu items reordered successfully', MenuItemResource::collection($items), HttpStatus::HTTP_OK);
    }

    public function syncTree(Request $request, int $menuId)
    {
        $menu = $this->menuService->getById($menuId);

        $validated = $request->validate([
            'nodes' => ['required', 'array'],
            'nodes.*.id' => ['required', 'integer', 'exists:menu_items,id'],
            'nodes.*.parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'nodes.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $updated = $this->menuService->syncTree($menu, $validated['nodes']);
        } catch (InvalidArgumentException $e) {
            return sendResponse(false, $e->getMessage(), null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        return sendResponse(true, 'Menu tree synced successfully', new MenuResource($updated), HttpStatus::HTTP_OK);
    }

    // ── Locations ──────────────────────────────────────────────────────────

    public function locationsIndex()
    {
        $locations = $this->menuService->listLocations();

        return sendResponse(true, 'Menu locations retrieved successfully', MenuLocationResource::collection($locations), HttpStatus::HTTP_OK);
    }

    public function locationsStore(Request $request)
    {
        $validated = $request->validate([
            'key' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'render_style' => ['nullable', 'string', Rule::in(MenuRenderStyle::values())],
            'menu_id' => ['nullable', 'integer', 'exists:menus,id'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $location = $this->menuService->createLocation($validated);
        } catch (InvalidArgumentException $e) {
            return sendResponse(false, $e->getMessage(), null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        return sendResponse(true, 'Menu location created successfully', new MenuLocationResource($location), HttpStatus::HTTP_CREATED);
    }

    public function locationsUpdate(Request $request, int $id)
    {
        $location = MenuLocation::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'render_style' => ['sometimes', 'string', Rule::in(MenuRenderStyle::values())],
            'menu_id' => ['nullable', 'integer', 'exists:menus,id'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $updated = $this->menuService->updateLocation($location, $validated);

        return sendResponse(true, 'Menu location updated successfully', new MenuLocationResource($updated), HttpStatus::HTTP_OK);
    }

    public function locationsDestroy(int $id)
    {
        $location = MenuLocation::query()->findOrFail($id);
        $this->menuService->deleteLocation($location);

        return sendResponse(true, 'Menu location deleted successfully', null, HttpStatus::HTTP_OK);
    }
}
