<?php

namespace App\Services;

use App\Jobs\UploadMediaToCloudinary;
use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MediaService
{
    public function __construct(
        private readonly CloudinaryService $cloudinary
    ) {}

    public function listForUser(int $userId, array $filters = []): LengthAwarePaginator
    {
        return Media::query()
            ->where('uploaded_by', $userId)
            ->when($filters['media_type'] ?? null, fn ($q, $v) => $q->ofType($v))
            ->when($filters['collection'] ?? null, fn ($q, $v) => $q->inCollection($v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->with('transformations')
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function createPlaceholder(
        UploadedFile $file,
        int $userId,
        string $collection = 'default',
        ?string $folder = null,
        ?string $mediableType = null,
        ?int $mediableId = null
    ): Media {
        return DB::transaction(function () use ($file, $userId, $collection, $folder, $mediableType, $mediableId) {
            return Media::create([
                'uuid' => (string) Str::ulid(),
                'cloudinary_public_id' => 'pending_' . Str::random(20),
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'extension' => strtolower($file->getClientOriginalExtension()),
                'resource_type' => 'auto',
                'media_type' => 'other',
                'size' => $file->getSize(),
                'url' => '',
                'collection' => $collection,
                'folder' => $folder,
                'status' => 'pending',
                'uploaded_by' => $userId,
                'mediable_id' => $mediableId,
                'mediable_type' => $this->resolveMediableType($mediableType),
            ]);
        });
    }

    public function uploadSync(Media $media, UploadedFile $file, array $options = []): Media
    {
        $result = $this->cloudinary->upload($file, $options);

        return $this->hydrateMedia($media, $result);
    }

    public function queueUpload(Media $media, UploadedFile $file, array $options = []): void
    {
        $tempPath = $file->store('cloudinary_temp', 'local');

        UploadMediaToCloudinary::dispatch($media->id, $tempPath, $options);
    }

    public function hydrateMedia(Media $media, array $result): Media
    {
        $isImage = str_starts_with($result['_mime'] ?? '', 'image/');
        $isVideo = str_starts_with($result['_mime'] ?? '', 'video/');

        $metadata = [];
        if ($isImage) {
            $metadata = [
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
            ];
        } elseif ($isVideo) {
            $metadata = [
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
                'duration' => $result['duration'] ?? null,
            ];
        } elseif (isset($result['pages'])) {
            $metadata = ['pages' => $result['pages']];
        }

        $media->update([
            'cloudinary_public_id' => $result['public_id'],
            'cloudinary_version' => is_scalar($result['version'] ?? null)
                ? (string) $result['version']
                : null,
            'cloudinary_signature' => $result['signature'] ?? null,
            'resource_type' => $result['resource_type'] ?? 'raw',
            'media_type' => $result['_media_type'],
            'url' => $result['secure_url'],
            'thumbnail_url' => $isImage
                ? $this->cloudinary->thumbnail($result['public_id'])
                : ($isVideo ? $this->cloudinary->videoThumbnail($result['public_id']) : null),
            'preview_url' => ($result['_extension'] ?? '') === 'pdf'
                ? $this->cloudinary->pdfPreview($result['public_id'])
                : null,
            'metadata' => $metadata,
            'size' => $result['bytes'] ?? $result['_size'],
            'status' => 'ready',
            'upload_attempts' => $media->upload_attempts + 1,
        ]);

        return $media->refresh();
    }

    public function deleteFromCloudinary(Media $media): bool
    {
        return $this->cloudinary->delete(
            $media->cloudinary_public_id,
            $media->resource_type
        );
    }

    public function bulkDelete(Collection $mediaItems): int
    {
        $grouped = $mediaItems->groupBy('resource_type');

        foreach ($grouped as $resourceType => $items) {
            $publicIds = $items->pluck('cloudinary_public_id')->toArray();
            $this->cloudinary->deleteMany($publicIds, $resourceType);
        }

        $mediaItems->each(fn (Media $m) => $m->delete());

        return $mediaItems->count();
    }

    public function getOrCreateTransformation(Media $media, string $preset): array
    {
        $cached = $media->transformations()->where('key', $preset)->first();
        if ($cached) {
            return ['url' => $cached->url, 'key' => $preset];
        }

        return Cache::remember(
            "media_transform_{$media->id}_{$preset}",
            86400,
            function () use ($media, $preset) {
                $transformation = $this->getTransformationPreset($preset, $media->media_type);

                $url = match ($media->media_type) {
                    'video' => $this->cloudinary->videoUrl($media->cloudinary_public_id, $transformation),
                    default => $this->cloudinary->imageUrl($media->cloudinary_public_id, $transformation),
                };

                $media->transformations()->create([
                    'key' => $preset,
                    'url' => $url,
                    'transformation' => $transformation,
                ]);

                return ['url' => $url, 'key' => $preset];
            }
        );
    }

    public function resolveMediableType(?string $type): ?string
    {
        return match ($type) {
            'user' => User::class,
            'article' => Article::class,
            default => null,
        };
    }

    public function getTransformationPreset(string $preset, string $mediaType): array
    {
        return match ($preset) {
            'thumbnail' => ['width' => 200, 'height' => 200, 'crop' => 'fill', 'gravity' => 'auto', 'quality' => 'auto', 'fetch_format' => 'auto'],
            'avatar' => ['width' => 120, 'height' => 120, 'crop' => 'fill', 'gravity' => 'face', 'quality' => 'auto', 'fetch_format' => 'auto'],
            'banner' => ['width' => 1200, 'height' => 400, 'crop' => 'fill', 'gravity' => 'auto', 'quality' => 'auto', 'fetch_format' => 'auto'],
            'preview' => ['width' => 600, 'height' => 400, 'crop' => 'fit', 'quality' => 'auto', 'fetch_format' => 'auto'],
            'hd' => ['width' => 1920, 'height' => 1080, 'crop' => 'fit', 'quality' => 'auto'],
            default => [],
        };
    }
}
