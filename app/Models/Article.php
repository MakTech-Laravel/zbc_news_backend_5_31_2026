<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'id',
        'title',
        'slug',
        'seo_title',
        'sub_title',
        'article_description',
        'status',
        'featured_image',
        'article_category_id',
        'excerpt',
        'scheduled_publishing',
        'published_at',
        'user_id',
    ];
    
    protected $casts = [
        'status' => ArticleStatus::class,
        'scheduled_publishing' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'article_tags', 'article_id', 'tag_id');
    }

    public function saveArticles()
    {
        return $this->hasMany(SaveArticle::class, 'article_id');
    }
}

