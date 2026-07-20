<?php

namespace App\Enums;

enum MenuRenderStyle: string
{
    case STANDARD = 'standard';
    case DROPDOWN = 'dropdown';
    case MEGA = 'mega';
    case MOBILE = 'mobile';
    case FOOTER = 'footer';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard Menu',
            self::DROPDOWN => 'Dropdown Menu',
            self::MEGA => 'Mega Menu',
            self::MOBILE => 'Mobile Menu',
            self::FOOTER => 'Footer Menu',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
