<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArticleCategory extends Model
{

    use SoftDeletes;
     
    protected $fillable = [
        'id',
        'title',
        'slug',
        'parent_id',
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
}
