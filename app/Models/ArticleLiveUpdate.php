<?php

namespace App\Models;

use App\Enums\LiveUpdateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArticleLiveUpdate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'article_id',
        'body',
        'posted_at',
        'status',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'status' => LiveUpdateStatus::class,
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', LiveUpdateStatus::PUBLISHED->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('posted_at')->orderByDesc('id');
    }
}
