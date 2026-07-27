<?php

namespace App\Models;

use App\Enums\SubMenuKey;
use Illuminate\Database\Eloquent\Model;

class SubMenuSetting extends Model
{
    protected $table = 'sub_menu_settings';

    public const CACHE_PREFIX = 'sub_menu:sections:';

    /** Short TTL for public section payloads (5 minutes). */
    public const TTL_PUBLIC = 300;

    protected $fillable = [
        'section_key',
        'limit',
        'trending_window_hours',
        'most_read_default_period',
        'pinned_slots',
        'is_enabled',
        'config',
    ];

    protected $casts = [
        'section_key' => SubMenuKey::class,
        'limit' => 'integer',
        'trending_window_hours' => 'integer',
        'pinned_slots' => 'integer',
        'is_enabled' => 'boolean',
        'config' => 'array',
    ];

    public static function cacheKey(string $section, ?string $suffix = null): string
    {
        return self::CACHE_PREFIX.$section.($suffix ? ':'.$suffix : '');
    }
}
