<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutUsSetting extends Model
{
    protected $fillable = [
        'hero_title',
        'hero_subtitle',
        'intro_html',
        'values',
        'leadership_subtitle',
        'leaders',
        'journey',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'leaders' => 'array',
            'journey' => 'array',
        ];
    }
}
