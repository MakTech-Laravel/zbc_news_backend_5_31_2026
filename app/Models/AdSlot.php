<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AdSlot extends Model
{
    /** Cache key for the public (active-only) slot payload. */
    public const CACHE_PUBLIC = 'ads:slots:public';

    /** 1 hour — slots are admin-edited and change rarely; every write path flushes below. */
    public const TTL_PUBLIC = 3600;

    protected $fillable = [
        'slot_key',
        'name',
        'placement',
        'provider',
        'is_active',
        'google_ad_client',
        'google_ad_slot',
        'manual_image_url',
        'manual_click_url',
        'manual_html',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function events()
    {
        return $this->hasMany(AdSlotEvent::class);
    }

    /**
     * Invalidate the public slot payload. Called from every AdSlot write path
     * (store, update — there is no delete path; see AdSlotsCacheTest).
     */
    public static function flushPublicCache(): void
    {
        Cache::forget(self::CACHE_PUBLIC);
    }
}
