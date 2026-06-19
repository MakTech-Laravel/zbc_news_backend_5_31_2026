<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function mediaInCollection(string $collection): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')
            ->where('collection', $collection)
            ->where('status', 'ready');
    }

    public function firstMedia(string $collection = 'default'): ?Media
    {
        return $this->mediaInCollection($collection)->latest()->first();
    }

    public function avatarMedia(): ?Media
    {
        return $this->firstMedia('avatar');
    }
}
