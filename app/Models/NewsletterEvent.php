<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterEvent extends Model
{
    protected $fillable = [
        'newsletter_campaign_id',
        'newsletter_subscriber_id',
        'event_type',
        'meta',
    ];
}

