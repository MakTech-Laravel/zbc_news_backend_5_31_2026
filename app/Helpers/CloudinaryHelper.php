<?php

namespace App\Helpers;

use App\Models\Media;

class CloudinaryHelper
{
    public static function url(Media $media, string $transformation = ''): string
    {
        if (! $transformation) {
            return $media->url;
        }

        return $media->getTransformationUrl($transformation) ?? $media->url;
    }

    public static function avatar(?Media $media, int $size = 80): string
    {
        if (! $media) {
            return "https://ui-avatars.com/api/?size={$size}&background=random";
        }

        return $media->thumbnail_url ?? $media->url;
    }
}
