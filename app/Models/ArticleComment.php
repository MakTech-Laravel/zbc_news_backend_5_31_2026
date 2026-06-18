<?php

namespace App\Models;

use App\Enums\CommentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleComment extends Model
{
    protected $fillable = [
        'article_id',
        'user_id',
        'parent_id',
        'body',
        'status',
        'guest_name',
        'guest_email',
        'ip_address',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'status' => CommentStatus::class,
        'approved_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function authorName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return (string) ($this->guest_name ?: 'Guest');
    }
}
