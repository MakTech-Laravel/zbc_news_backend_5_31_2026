<?php

namespace App\Services\Menus;

use App\Enums\MenuItemType;
use App\Models\MenuItem;
use InvalidArgumentException;

class CustomLinkMenuItemHandler implements MenuItemTypeHandler
{
    public function type(): string
    {
        return MenuItemType::CUSTOM->value;
    }

    public function prepare(array $data): array
    {
        $url = trim((string) ($data['url'] ?? ''));
        $label = trim((string) ($data['label'] ?? ''));

        if ($label === '') {
            throw new InvalidArgumentException('Custom link label is required.');
        }
        if ($url === '') {
            throw new InvalidArgumentException('Custom link URL is required.');
        }

        return [
            'type' => $this->type(),
            'label' => $label,
            'url' => $url,
            'reference_type' => null,
            'reference_id' => null,
        ];
    }

    public function resolveUrl(MenuItem $item): ?string
    {
        return $item->url;
    }

    public function resolveLabel(MenuItem $item): string
    {
        return $item->label;
    }
}
