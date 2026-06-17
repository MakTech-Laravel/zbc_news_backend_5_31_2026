<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'name',
        'source',
        'status',
        'preferences',
        'is_premium',
        'audience_tags',
        'provider_contact_id',
        'verification_token',
        'verified_at',
        'unsubscribe_token',
        'unsubscribed_at',
    ];

    protected $casts = [
        'preferences' => 'array',
        'audience_tags' => 'array',
        'is_premium' => 'boolean',
        'verified_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(NewsletterEvent::class);
    }
}
