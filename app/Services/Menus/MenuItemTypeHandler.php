<?php

namespace App\Services\Menus;

use App\Models\MenuItem;

/**
 * Contract for resolving/validating a menu item type.
 * Register new handlers in MenuItemTypeRegistry to extend without touching CRUD.
 */
interface MenuItemTypeHandler
{
    public function type(): string;

    /**
     * Normalize + validate payload fields for this type.
     * Returns the fields that should be persisted (may include reference_*, url, label).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepare(array $data): array;

    /** Resolved href for public API / frontend rendering. */
    public function resolveUrl(MenuItem $item): ?string;

    /** Display label (may fall back to referenced entity title). */
    public function resolveLabel(MenuItem $item): string;
}
