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
     * Build a Cloudinary URL that forces download.
     *
     * IMPORTANT: Do not put long / hyphenated names into fl_attachment:… —
     * Cloudinary treats hyphens inside that segment as transformation separators
     * and returns HTTP 400 (common with UUID-heavy original filenames).
     * A bare fl_attachment (or a tiny ASCII name) is reliable on local + live.
     */
    public static function forceDownloadUrl(?string $url, ?string $filename = null): ?string
    {
        $resolved = self::resolvePublic($url);
        if (! $resolved) {
            return null;
        }

        if (! self::isRemote($resolved) || ! str_contains($resolved, '/upload/')) {
            return $resolved;
        }

        if (str_contains($resolved, 'fl_attachment')) {
            return $resolved;
        }

        // Prefer a tiny name derived only from extension (no hyphens/UUIDs).
        $ext = self::safeAttachmentExtension($filename, $resolved);
        $flag = $ext !== '' ? 'fl_attachment:file.'.$ext : 'fl_attachment';

        return preg_replace('#/upload/#', '/upload/'.$flag.'/', $resolved, 1) ?: $resolved;
    }

    /**
     * Extension safe for Cloudinary fl_attachment:file.{ext} (no dots/hyphens beyond the ext).
     */
    public static function safeAttachmentExtension(?string $filename, ?string $url = null): string
    {
        // Prefer the CDN asset extension — original upload names often disagree
        // (e.g. ".webp" label on a ".jpg" Cloudinary object) and break fl_attachment.
        if (is_string($url) && $url !== '') {
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $fromUrl = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($fromUrl !== '' && preg_match('/^[a-z0-9]{1,8}$/', $fromUrl)) {
                return $fromUrl;
            }
        }

        if (is_string($filename) && $filename !== '') {
            $fromName = strtolower(pathinfo(self::sanitizeDownloadFilename($filename), PATHINFO_EXTENSION));
            if ($fromName !== '' && preg_match('/^[a-z0-9]{1,8}$/', $fromName)) {
                return $fromName;
            }
        }

        return '';
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

    /**
     * @deprecated Kept for tests/callers; Cloudinary download URLs no longer embed full names.
     */
    public static function sanitizeCloudinaryAttachmentFilename(?string $filename): string
    {
        $ext = self::safeAttachmentExtension($filename);

        return $ext !== '' ? 'file.'.$ext : 'file';
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
