<?php

namespace App\Services\Menus;

use App\Enums\MenuItemType;
use App\Models\ArticleCategory;
use App\Models\MenuItem;
use InvalidArgumentException;

class CategoryMenuItemHandler implements MenuItemTypeHandler
{
    public function type(): string
    {
        return MenuItemType::CATEGORY->value;
    }

    public function prepare(array $data): array
    {
        $categoryId = (int) ($data['reference_id'] ?? $data['category_id'] ?? 0);
        if ($categoryId < 1) {
            throw new InvalidArgumentException('Category is required for category menu items.');
        }

        $category = ArticleCategory::query()->find($categoryId);
        if (! $category) {
            throw new InvalidArgumentException('Selected category was not found.');
        }

        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            $label = $category->title;
        }

        return [
            'type' => $this->type(),
            'label' => $label,
            'url' => '/'.$category->slug,
            'reference_type' => ArticleCategory::class,
            'reference_id' => $category->id,
        ];
    }

    public function resolveUrl(MenuItem $item): ?string
    {
        if ($item->relationLoaded('category') && $item->category) {
            return '/'.$item->category->slug;
        }

        $category = ArticleCategory::query()->find($item->reference_id);
        if ($category) {
            return '/'.$category->slug;
        }

        return $item->url;
    }

    public function resolveLabel(MenuItem $item): string
    {
        if (filled($item->label)) {
            return $item->label;
        }

        if ($item->relationLoaded('category') && $item->category) {
            return $item->category->title;
        }

        $category = ArticleCategory::query()->find($item->reference_id);

        return $category?->title ?? $item->label;
    }
}
