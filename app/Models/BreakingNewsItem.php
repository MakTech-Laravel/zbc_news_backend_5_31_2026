<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\BreakingNewsStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakingNewsItem extends Model
{
    protected $fillable = [
        'article_id',
        'headline_override',
        'priority',
        'status',
        'starts_at',
        'expires_at',
        'notified_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => BreakingNewsStatus::class,
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function displayHeadline(): string
    {
        $override = trim((string) $this->headline_override);

        return $override !== ''
            ? $override
            : (string) ($this->article?->title ?? '');
    }

    public function isLive(?\DateTimeInterface $now = null): bool
    {
        $now = $now ? \Carbon\Carbon::instance($now) : now();

        if ($this->status !== BreakingNewsStatus::ACTIVE) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->greaterThan($now)) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->lessThanOrEqualTo($now)) {
            return false;
        }

        $article = $this->article;
        if (! $article || $article->status !== ArticleStatus::PUBLISHED) {
            return false;
        }

        return true;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeEligibleForTicker(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('status', BreakingNewsStatus::ACTIVE->value)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->whereHas('article', function (Builder $q) {
                $q->where('status', ArticleStatus::PUBLISHED->value)
                    ->whereNull('deleted_at');
            })
            ->orderBy('priority')
            ->orderByDesc('starts_at')
            ->orderByDesc('id');
    }
}
