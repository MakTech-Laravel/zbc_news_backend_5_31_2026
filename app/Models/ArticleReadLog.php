<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleReadLog extends Model
{
    public $timestamps = false;
 
    protected $fillable = [
        'article_id',
        'user_id',
        'ip_address',
        'read_at',
    ];
 
    protected $casts = [
        'read_at' => 'datetime',
    ];
 
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }
 
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
