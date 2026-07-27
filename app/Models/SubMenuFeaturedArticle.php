<?php

namespace App\Models;

use App\Enums\SubMenuKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubMenuFeaturedArticle extends Model
{
    protected $table = 'sub_menu_featured_articles';

    protected $fillable = [
        'section_key',
        'article_id',
        'sort_order',
        'is_pinned',
        'is_active',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $casts = [
        'section_key' => SubMenuKey::class,
        'sort_order' => 'integer',
        'is_pinned' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCurrentlyActive(?\DateTimeInterface $now = null): bool
    {
        $now = $now ? \Carbon\Carbon::instance($now) : now();

        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->greaterThan($now)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->lessThanOrEqualTo($now)) {
            return false;
        }

        return true;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrentlyActive(Builder $query, ?\DateTimeInterface $now = null): Builder
    {
        $now = $now ? \Carbon\Carbon::instance($now) : now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForSection(Builder $query, string|SubMenuKey $section): Builder
    {
        $key = $section instanceof SubMenuKey ? $section->value : $section;

        return $query->where('section_key', $key);
    }
}
