<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterCampaign extends Model
{
    protected $fillable = [
        'title',
        'subject',
        'preview_text',
        'content_html',
        'status',
        'scheduled_at',
        'sent_at',
        'subscriber_count',
        'open_count',
        'click_count',
        'failed_count',
        'segments',
        'audience_type',
        'premium_only',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'segments' => 'array',
        'premium_only' => 'boolean',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(NewsletterEvent::class);
    }
}
