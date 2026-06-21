<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    public static function isRemote(?string $value): bool
    {
        return is_string($value) && (bool) preg_match('/^https?:\/\//i', $value);
    }

    public static function resolvePublic(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (self::isRemote($value)) {
            return $value;
        }

        $normalized = str_starts_with($value, '/') ? ltrim($value, '/') : $value;

        return asset('storage/'.$normalized);
    }

    public static function deleteLocalIfStored(?string $value): void
    {
        if (! $value || self::isRemote($value)) {
            return;
        }

        $path = ltrim($value, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
