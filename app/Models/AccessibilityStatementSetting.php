<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessibilityStatementSetting extends Model
{
    protected $fillable = [
        'hero_eyebrow',
        'hero_title',
        'hero_intro',
        'badges',
        'commitment_heading',
        'commitment_paragraphs',
        'commitment_stats',
        'features_heading',
        'features',
        'shortcuts_heading',
        'keyboard_shortcuts',
        'technologies_heading',
        'supported_technologies',
        'known_limitations',
        'report_heading',
        'report_intro',
        'contact_email',
        'contact_phone',
        'contact_address',
        'cta_text',
        'cta_button_label',
    ];

    protected function casts(): array
    {
        return [
            'badges' => 'array',
            'commitment_paragraphs' => 'array',
            'commitment_stats' => 'array',
            'features' => 'array',
            'keyboard_shortcuts' => 'array',
            'supported_technologies' => 'array',
        ];
    }
}
