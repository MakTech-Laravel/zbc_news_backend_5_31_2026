<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    protected $fillable = [
        'title',
        'subject',
        'content_html',
        'status',
        'scheduled_at',
        'sent_at',
        'subscriber_count',
        'open_count',
        'click_count',
        'segments',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'segments' => 'array',
    ];
}

