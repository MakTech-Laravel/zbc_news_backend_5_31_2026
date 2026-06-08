<?php

namespace App\Enums;

enum ArticleVisibility: string
{
    case PUBLIC = 'public';
    case MEMBERS = 'members';
    case PREMIUM = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'Public',
            self::MEMBERS => 'Members',
            self::PREMIUM => 'Premium',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
