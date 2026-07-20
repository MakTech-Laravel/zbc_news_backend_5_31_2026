<?php

namespace App\Models;

use App\Enums\MenuItemTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'type',
        'label',
        'url',
        'target',
        'icon',
        'reference_type',
        'reference_id',
        'sort_order',
        'is_active',
        'meta',
    ];

    protected $casts = [
        // `type` stays a string so new handlers can be registered without enum changes.
        'target' => MenuItemTarget::class,
        'is_active' => 'boolean',
        'meta' => 'array',
        'sort_order' => 'integer',
        'reference_id' => 'integer',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'reference_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (MenuItem $item) {
            $children = $item->children()->withTrashed()->get();
            foreach ($children as $child) {
                if ($item->isForceDeleting()) {
                    $child->forceDelete();
                } else {
                    $child->delete();
                }
            }
        });
    }
}
