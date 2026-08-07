<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Build a Cloudinary (or passthrough) URL that forces download with the given filename.
     * Cross-origin <a download> alone often saves CDN assets as .tmp — fl_attachment fixes that.
     */
    public static function forceDownloadUrl(?string $url, ?string $filename): ?string
    {
        $resolved = self::resolvePublic($url);
        if (! $resolved) {
            return null;
        }

        $safeName = self::sanitizeDownloadFilename($filename);
        if ($safeName === '') {
            return $resolved;
        }

        if (! self::isRemote($resolved) || ! str_contains($resolved, '/upload/')) {
            return $resolved;
        }

        if (str_contains($resolved, 'fl_attachment')) {
            return $resolved;
        }

        $encoded = rawurlencode($safeName);

        return preg_replace('#/upload/#', '/upload/fl_attachment:'.$encoded.'/', $resolved, 1) ?: $resolved;
    }

    public static function sanitizeDownloadFilename(?string $filename): string
    {
        $name = trim((string) $filename);
        if ($name === '') {
            return '';
        }

        $name = basename(str_replace(['\\', '/'], '-', $name));
        $name = preg_replace('/[^\w.\- ()\[\]]+/u', '_', $name) ?: 'download';
        $name = trim($name, '._ ');

        return $name !== '' ? $name : 'download';
    }

    public static function downloadFilename(?string $originalFilename, ?string $extension, ?string $fallbackLabel = null): string
    {
        $base = self::sanitizeDownloadFilename($originalFilename);
        if ($base === '') {
            $base = self::sanitizeDownloadFilename($fallbackLabel) ?: 'document';
        }

        $ext = strtolower(ltrim((string) $extension, '.'));
        if ($ext === '') {
            return $base;
        }

        if (Str::endsWith(strtolower($base), '.'.$ext)) {
            return $base;
        }

        // Strip a trailing .tmp (or similar) before appending the real extension.
        $base = preg_replace('/\.(tmp|temp|download)$/i', '', $base) ?: $base;

        return $base.'.'.$ext;
    }

    public static function extensionFromMime(?string $mime): ?string
    {
        $mime = strtolower(trim((string) $mime));

        return match ($mime) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/csv', 'application/csv' => 'csv',
            'text/plain' => 'txt',
            default => null,
        };
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
