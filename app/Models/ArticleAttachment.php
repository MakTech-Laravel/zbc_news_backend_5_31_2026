<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleAttachment extends Model
{
    protected $fillable = [
        'article_id',
        'media_id',
        'label',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
