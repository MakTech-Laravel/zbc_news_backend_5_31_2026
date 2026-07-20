<?php

namespace App\Enums;

/**
 * Built-in menu item types. New types can be registered via MenuItemTypeRegistry
 * without changing this enum — the DB stores free-form type strings. The enum
 * documents first-party types and powers validation for known cases.
 */
enum MenuItemType: string
{
    case CUSTOM = 'custom';
    case CATEGORY = 'category';
    case PAGE = 'page';
    case POST = 'post';
    case PRODUCT = 'product';
    case TAG = 'tag';
    case BRAND = 'brand';
    case COLLECTION = 'collection';
    case MODULE = 'module';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOM => 'Custom Link',
            self::CATEGORY => 'Category',
            self::PAGE => 'Page',
            self::POST => 'Post',
            self::PRODUCT => 'Product',
            self::TAG => 'Tag',
            self::BRAND => 'Brand',
            self::COLLECTION => 'Collection',
            self::MODULE => 'Module',
        };
    }

    /** Types currently supported end-to-end in admin + resolvers. */
    public static function implemented(): array
    {
        return [
            self::CUSTOM,
            self::CATEGORY,
        ];
    }

    public static function implementedValues(): array
    {
        return array_map(fn (self $case) => $case->value, self::implemented());
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
