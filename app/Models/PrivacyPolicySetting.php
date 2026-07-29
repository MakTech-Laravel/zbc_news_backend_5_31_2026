<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyPolicySetting extends Model
{
    protected $fillable = [
        'hero_meta',
        'plain_summary',
        'overview',
        'data_we_collect',
        'how_we_use',
        'your_rights',
        'data_security',
        'third_parties',
        'contact',
    ];
}
