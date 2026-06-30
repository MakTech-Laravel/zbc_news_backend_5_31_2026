<?php

namespace App\Services;

use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoredImageService
{
    public function __construct(
        private readonly CloudinaryService $cloudinary,
    ) {}

    public function upload(UploadedFile $file, string $folder): string
    {
        $result = $this->cloudinary->upload($file, ['folder' => $folder]);

        return $result['secure_url'];
    }

    public function resolveValue(mixed $value): ?string
    {
        if ($value instanceof UploadedFile) {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    public function resolve(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return MediaUrl::resolvePublic($value);
    }

    public function isDifferent(?string $current, ?string $incoming): bool
    {
        return $this->normalizeReference($current) !== $this->normalizeReference($incoming);
    }

    public function delete(?string $value): void
    {
        if (! $value) {
            return;
        }

        if (MediaUrl::isRemote($value) && str_contains($value, 'res.cloudinary.com')) {
            $publicId = $this->cloudinary->publicIdFromUrl($value);

            if ($publicId) {
                $this->cloudinary->delete($publicId, 'image');
            }

            return;
        }

        MediaUrl::deleteLocalIfStored($value);
    }

    public function localDiskPath(?string $value): ?string
    {
        if (! $value || MediaUrl::isRemote($value)) {
            return null;
        }

        $path = ltrim($value, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        return null;
    }

    public function isLocalPath(?string $value): bool
    {
        return $this->localDiskPath($value) !== null;
    }

    private function normalizeReference(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return MediaUrl::resolvePublic($value) ?? $value;
    }
}
