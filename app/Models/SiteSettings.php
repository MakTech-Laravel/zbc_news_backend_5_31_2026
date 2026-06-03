<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSettings extends Model
{
    protected $fillable = [
        'site_name',
        'site_tag',
        'site_logo',
        'timezone',
        'default_category_id',
        'posts_per_page',
        'allow_comments',
        'authenticate_comment_only',
        'related_article',
        'pixeld_id',
        'g_messurment_id',
        'g_api_secrete',
        'enable_comments',
    ];

    protected $casts = [
        'timezone'                  => 'integer',
        'default_category_id'       => 'integer',
        'posts_per_page'            => 'integer',
        'allow_comments'            => 'boolean',
        'authenticate_comment_only' => 'boolean',
        'related_article'           => 'integer',
        'pixeld_id'                 => 'integer',
        'g_messurment_id'           => 'integer',
        'enable_comments'           => 'boolean',
    ];
    
    public function defaultCategory()
    {
        return $this->belongsTo(ArticleCategory::class, 'default_category_id');
    }
}
