<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareersPageSettings extends Model
{
    protected $fillable = [
        'hero',
        'stats',
        'perks_section',
        'perks',
        'positions_section',
        'hiring_section',
        'hiring_steps',
        'testimonials_section',
        'testimonials',
        'faq_section',
        'faqs',
        'cta',
    ];

    protected function casts(): array
    {
        return [
            'hero' => 'array',
            'stats' => 'array',
            'perks_section' => 'array',
            'perks' => 'array',
            'positions_section' => 'array',
            'hiring_section' => 'array',
            'hiring_steps' => 'array',
            'testimonials_section' => 'array',
            'testimonials' => 'array',
            'faq_section' => 'array',
            'faqs' => 'array',
            'cta' => 'array',
        ];
    }
}
