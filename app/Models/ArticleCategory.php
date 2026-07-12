<?php

namespace App\Models;

use App\Enums\ArticleCategoryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArticleCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'id',
        'title',
        'slug',
        'status',
        'parent_id',
        'sort_order',
        'is_featured',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'status' => ArticleCategoryStatus::class,
        'is_featured' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(ArticleCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ArticleCategory::class, 'parent_id');
    }

    public function articles()
    {
        return $this->hasMany(Article::class, 'article_category_id');
    }

    public function siteSettings()
    {
        return $this->hasOne(SiteSettings::class, 'default_category_id');
    }
}
