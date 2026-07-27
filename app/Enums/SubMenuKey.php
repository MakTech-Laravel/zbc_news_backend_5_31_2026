<?php

namespace App\Enums;

enum SubMenuKey: string
{
    case TRENDING = 'trending';
    case MOST_READ = 'most_read';
    case LIVE_UPDATES = 'live_updates';
    case EDITORIAL_PICKS = 'editorial_picks';

    public function label(): string
    {
        return match ($this) {
            self::TRENDING => 'Trending',
            self::MOST_READ => 'Most Read',
            self::LIVE_UPDATES => 'Live Updates',
            self::EDITORIAL_PICKS => 'Editorial Picks',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
