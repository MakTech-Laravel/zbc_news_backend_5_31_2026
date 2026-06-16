<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email',
        'name',
        'status',
        'preferences',
        'verification_token',
        'verified_at',
        'unsubscribe_token',
        'unsubscribed_at',
    ];

    protected $casts = [
        'preferences' => 'array',
        'verified_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];
}

