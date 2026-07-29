<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CookiePolicySetting extends Model
{
    protected $fillable = [
        'hero_meta',
        'hero_description',
        'preferences_intro',
        'categories',
        'browser_intro',
        'browser_controls',
        'faqs',
        'contact_heading',
        'contact_description',
        'contact_email',
        'banner_title',
        'banner_description',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'browser_controls' => 'array',
            'faqs' => 'array',
        ];
    }
}
