<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleHistroy extends Model
{
    protected $fillable = [
        'article_id',
        'user_id',
        'session_id',
        'ip_address',
        'time_spent',
        'scroll_depth',
        'is_guest',
        'read_at',
        'read_end_at',
    ];

    protected $casts = [
        'is_guest'    => 'boolean',
        'read_at'     => 'datetime',
        'read_end_at' => 'datetime',
        'time_spent'  => 'integer',
        'scroll_depth' => 'integer',
    ];

    // ───── Relationships ─────

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ───── Scopes ─────

    public function scopeForArticle($query, int $articleId)
    {
        return $query->where('article_id', $articleId);
    }

    public function scopeGuests($query)
    {
        return $query->where('is_guest', true);
    }

    public function scopeAuthUsers($query)
    {
        return $query->where('is_guest', false);
    }
}
