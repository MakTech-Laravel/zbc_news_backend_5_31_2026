<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BreakingTag
{
    /**
     * @var array<int, string>
     */
    public const VALUES = [
        'breaking-news',
        'breaking_news',
        'breaking',
        'Breaking',
        'Breaking-News',
        'Breaking_News',
        'BreakingNews',
    ];

    public static function isBreaking(string $tag): bool
    {
        return in_array($tag, self::VALUES, true);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function constrainTagQuery(Builder $query, string $column = 'tag'): Builder
    {
        return $query->whereIn($column, self::VALUES);
    }
}
