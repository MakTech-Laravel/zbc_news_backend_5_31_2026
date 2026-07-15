<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class NavigationLink extends Model
{
    /** The only location exposed publicly, by NavigationController::quickLinks. */
    public const LOCATION_QUICK_LINKS = 'home_quick_links';

    /** Cache key for the public (active-only) quick-links payload. */
    public const CACHE_QUICK_LINKS = 'navigation:quick-links:public';

    /** 1 hour — links are admin-edited and change rarely; every write path flushes below. */
    public const TTL_QUICK_LINKS = 3600;

    protected $fillable = [
        'location',
        'label',
        'url',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Invalidate the public quick-links payload. Called from every NavigationLink write
     * path (store, update, destroy).
     *
     * Flushes regardless of the row's location: an update can move a link into or out of
     * `home_quick_links`, so a location-conditional flush would miss exactly those edits.
     */
    public static function flushPublicCache(): void
    {
        Cache::forget(self::CACHE_QUICK_LINKS);
    }
}
