<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\NotificationIcon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'icon',
        'title',
        'body',
        'article_slug',
        'dedupe_key',
        'read_at',
    ];

    protected $casts = [
        'category' => NotificationCategory::class,
        'icon' => NotificationIcon::class,
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
