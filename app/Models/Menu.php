<?php

namespace App\Models;

use App\Enums\MenuStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Menu extends Model
{
    use SoftDeletes;

    public const CACHE_PREFIX = 'menus:public:';

    public const CACHE_TTL = 3600;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => MenuStatus::class,
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    public function rootItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(MenuLocation::class);
    }

    public static function flushPublicCache(): void
    {
        // Forget known location/slug keys; also bump a generation key if present.
        Cache::forget(self::CACHE_PREFIX.'locations-index');
        $keys = Cache::get(self::CACHE_PREFIX.'known-keys', []);
        if (is_array($keys)) {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
        Cache::forget(self::CACHE_PREFIX.'known-keys');
    }

    public static function rememberPublicKey(string $cacheKey): void
    {
        $keys = Cache::get(self::CACHE_PREFIX.'known-keys', []);
        if (! is_array($keys)) {
            $keys = [];
        }
        if (! in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            Cache::forever(self::CACHE_PREFIX.'known-keys', $keys);
        }
    }
}
