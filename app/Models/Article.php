<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use App\Support\ReadTime;
use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'meta_keywords',
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

    public function comments()
    {
        return $this->hasMany(ArticleComment::class, 'article_id');
    }

    // public function readLogs()
    // {
    //     return $this->hasMany(ArticleReadLog::class, 'article_id');
    // }

    public function histroy()
    {
        return $this->hasMany(ArticleHistroy::class, 'article_id');
    }

    public function scopeWithReadingTime(Builder $query): Builder
    {
        return $query->withSum('histroy', 'time_spent');
    }

    public function formattedReadTime(): string
    {
        $seconds = (int) ($this->histroy_sum_time_spent ?? 0);

        if ($seconds > 0) {
            return ReadTime::fromSeconds($seconds);
        }

        return ReadTime::fromHtml($this->article_description);
    }

    public function featuredMediaItems(): MorphMany
    {
        return $this->mediaInCollection('featured');
    }

    public function posterMediaItems(): MorphMany
    {
        return $this->mediaInCollection('poster');
    }

    public function featuredMedia(): ?Media
    {
        if ($this->relationLoaded('media')) {
            return $this->media
                ->where('collection', 'featured')
                ->where('status', 'ready')
                ->sortByDesc('id')
                ->first();
        }

        return $this->firstMedia('featured');
    }

    public function posterMedia(): ?Media
    {
        if ($this->relationLoaded('media')) {
            return $this->media
                ->where('collection', 'poster')
                ->where('status', 'ready')
                ->sortByDesc('id')
                ->first();
        }

        return $this->firstMedia('poster');
    }
}
