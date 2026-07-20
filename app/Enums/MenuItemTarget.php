<?php

namespace App\Enums;

enum MenuItemTarget: string
{
    case SELF = '_self';
    case BLANK = '_blank';

    public function label(): string
    {
        return match ($this) {
            self::SELF => 'Same tab',
            self::BLANK => 'New tab',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
