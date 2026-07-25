<?php

namespace App\Enums;

enum BreakingNewsStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case EXPIRED = 'expired';
    case REMOVED = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::EXPIRED => 'Expired',
            self::REMOVED => 'Removed',
        };
    }
}
