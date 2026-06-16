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
        'language',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'default_category_id',
        'default_post_format',
        'enable_auto_save',
        'require_featured_image',
        'enable_ai_writing',
        'posts_per_page',
        'allow_comments',
        'authenticate_comment_only',
        'auto_approve_known_users',
        'related_article',
        'pixeld_id',
        'g_messurment_id',
        'g_api_secrete',
        'google_analytics_id',
        'facebook_pixel_id',
        'mailchimp_api_key',
        'disqus_shortname',
        'slack_webhook_url',
        'enable_comments',
    ];

    protected $casts = [
        'default_category_id'       => 'integer',
        'posts_per_page'            => 'integer',
        'allow_comments'            => 'boolean',
        'authenticate_comment_only' => 'boolean',
        'auto_approve_known_users'  => 'boolean',
        'related_article'           => 'integer',
        'enable_auto_save'          => 'boolean',
        'require_featured_image'    => 'boolean',
        'enable_ai_writing'         => 'boolean',
        'enable_comments'           => 'boolean',
    ];

    public function defaultCategory()
    {
        return $this->belongsTo(ArticleCategory::class, 'default_category_id');
    }
}
