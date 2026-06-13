<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Article extends Model
{
    use HasFactory, HasMedia, SoftDeletes;
    protected $fillable = [
        'id',
        'title',
        'slug',
        'sub_title',
        'article_description',
        'meta_title',
        'meta_description',
        'status',
        'featured_image',
        'open_graph_image',
        'article_category_id',
        'visibility',
        'excerpt',
        'scheduled_publishing',
        'published_at',
        'user_id',
        'views',
    ];
    
    protected $casts = [
        'status' => ArticleStatus::class,
        'visibility' => ArticleVisibility::class,
        'scheduled_publishing' => 'datetime',
        'published_at' => 'datetime',
        'views' => 'integer',
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

    public function readLogs()
    {
        return $this->hasMany(ArticleReadLog::class, 'article_id');
    }
    
    public function histroy()
    {
        return $this->hasMany(ArticleHistroy::class, 'article_id');
    }
}

