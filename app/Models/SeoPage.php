<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoPage extends Model
{
    protected $fillable = [
        'page_key',
        'name',
        'url_path',
        'is_template',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'noindex',
    ];

    protected $casts = [
        'is_template' => 'boolean',
        'noindex' => 'boolean',
    ];
}
