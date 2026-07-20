<?php

namespace App\Services\Menus;

use InvalidArgumentException;

/**
 * Extensible registry of menu item type handlers.
 * Add a new type later by implementing MenuItemTypeHandler and registering it
 * (e.g. from a service provider or AppServiceProvider boot).
 */
class MenuItemTypeRegistry
{
    /** @var array<string, MenuItemTypeHandler> */
    private array $handlers = [];

    public function __construct(
        CustomLinkMenuItemHandler $custom,
        CategoryMenuItemHandler $category,
    ) {
        $this->register($custom);
        $this->register($category);
    }

    public function register(MenuItemTypeHandler $handler): void
    {
        $this->handlers[$handler->type()] = $handler;
    }

    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    public function get(string $type): MenuItemTypeHandler
    {
        if (! isset($this->handlers[$type])) {
            throw new InvalidArgumentException("Unsupported menu item type [{$type}].");
        }

        return $this->handlers[$type];
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->handlers);
    }

    /** @return list<array{value: string, label: string}> */
    public function options(): array
    {
        $labels = [
            'custom' => 'Custom Link',
            'category' => 'Category',
            'page' => 'Page',
            'post' => 'Post',
            'product' => 'Product',
            'tag' => 'Tag',
            'brand' => 'Brand',
            'collection' => 'Collection',
            'module' => 'Module',
        ];

        return array_map(
            fn (string $type) => [
                'value' => $type,
                'label' => $labels[$type] ?? ucfirst($type),
            ],
            $this->types()
        );
    }
}
