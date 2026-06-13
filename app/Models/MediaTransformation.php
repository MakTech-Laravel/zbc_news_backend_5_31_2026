<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaTransformation extends Model
{
    protected $fillable = ['media_id', 'key', 'url', 'transformation'];

    protected $casts = ['transformation' => 'array'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
