<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermsOfServiceSetting extends Model
{
    protected $fillable = [
        'hero_meta',
        'quick_summary',
        'account_terms',
        'content_ip',
        'subscriptions',
        'prohibited',
        'disputes',
        'contact',
    ];
}
