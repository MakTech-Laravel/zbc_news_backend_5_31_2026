<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Article;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'id',
        'tag',
    ];


    public function articles(): BelongsToMany
{
    return $this->belongsToMany(
        Article::class,
        'article_tags',
        'tag_id',
        'article_id'
    )->withTimestamps();
}
}
