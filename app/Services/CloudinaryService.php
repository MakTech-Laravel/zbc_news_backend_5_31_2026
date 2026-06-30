<?php

namespace App\Services;

use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class CloudinaryService
{
    protected Cloudinary $cloudinary;

    protected UploadApi $uploadApi;

    protected AdminApi $adminApi;

    public function __construct()
    {
        $this->configureCloudinary();

        $this->cloudinary = new Cloudinary;
        $this->uploadApi = new UploadApi;
        $this->adminApi = new AdminApi;
    }

    protected function configureCloudinary(): void
    {
        $url = config('cloudinary.url');
        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');

        if (! empty($url)) {
            Configuration::instance($url);
        } elseif ($cloudName && $apiKey && $apiSecret) {
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ],
                'url' => [
                    'secure' => config('cloudinary.secure', true),
                ],
            ]);
        } else {
            throw new RuntimeException(
                'Cloudinary is not configured. Add CLOUDINARY_URL or CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET to your .env file.'
            );
        }
    }

    public function upload(UploadedFile $file, array $options = []): array
    {
        $resourceType = $this->detectResourceType($file);
        $folder = $this->buildFolder($options['folder'] ?? null, $resourceType);
        $publicId = $this->generatePublicId($file);

        $uploadOptions = array_merge(
            $this->defaultUploadOptions($resourceType),
            [
                'public_id' => $publicId,
                'folder' => $folder,
                'resource_type' => $resourceType,
            ],
            $this->filterUploadOptions($options)
        );

        $result = $this->uploadApi->upload($file->getRealPath(), $uploadOptions);

        return $this->normaliseResult((array) $result, $file);
    }

    public function uploadFromUrl(string $url, array $options = []): array
    {
        $resourceType = $options['resource_type'] ?? 'auto';
        $folder = $this->buildFolder($options['folder'] ?? null, $resourceType);
        $uploadOptions = array_merge(
            $this->defaultUploadOptions($resourceType),
            ['folder' => $folder, 'resource_type' => $resourceType],
            $options
        );

        $result = $this->uploadApi->upload($url, $uploadOptions);

        return (array) $result;
    }

    public function uploadBase64(string $base64, array $options = []): array
    {
        $dataUri = Str::startsWith($base64, 'data:')
            ? $base64
            : "data:application/octet-stream;base64,{$base64}";

        $result = $this->uploadApi->upload($dataUri, array_merge(
            ['resource_type' => 'auto'],
            $options
        ));

        return (array) $result;
    }

    public function publicIdFromUrl(string $url): ?string
    {
        if (! str_contains($url, 'res.cloudinary.com')) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! preg_match('#/upload/(?:[^/]+/)*(?:v\d+/)?(.+)$#', $path, $matches)) {
            return null;
        }

        $publicIdWithExt = $matches[1];
        $directory = pathinfo($publicIdWithExt, PATHINFO_DIRNAME);
        $filename = pathinfo($publicIdWithExt, PATHINFO_FILENAME);

        return $directory === '.' ? $filename : "{$directory}/{$filename}";
    }

    public function delete(string $publicId, string $resourceType = 'image'): bool
    {
        if (str_starts_with($publicId, 'pending_')) {
            return true;
        }

        try {
            $result = $this->uploadApi->destroy($publicId, [
                'resource_type' => $this->normalizeResourceType($resourceType),
                'invalidate' => true,
            ]);

            return ($result['result'] ?? '') === 'ok';
        } catch (\Throwable $e) {
            Log::error('Cloudinary delete failed', [
                'public_id' => $publicId,
                'resource_type' => $resourceType,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function deleteMany(array $publicIds, string $resourceType = 'image'): array
    {
        if (empty($publicIds)) {
            return ['deleted' => []];
        }

        return (array) $this->adminApi->deleteAssets($publicIds, [
            'resource_type' => $this->normalizeResourceType($resourceType),
            'invalidate' => true,
        ]);
    }

    protected function normalizeResourceType(string $resourceType): string
    {
        return match ($resourceType) {
            'video' => 'video',
            'raw' => 'raw',
            default => 'image',
        };
    }

    public function imageUrl(string $publicId, array $transformation = []): string
    {
        $image = $this->cloudinary->image($publicId);

        if (! empty($transformation)) {
            $image->addTransformation($transformation);
        }

        return (string) $image->toUrl();
    }

    public function videoUrl(string $publicId, array $transformation = []): string
    {
        $video = $this->cloudinary->video($publicId);

        if (! empty($transformation)) {
            $video->addTransformation($transformation);
        }

        return (string) $video->toUrl();
    }

    public function thumbnail(string $publicId, int $width = 300, int $height = 300): string
    {
        return $this->imageUrl($publicId, [
            'width' => $width,
            'height' => $height,
            'crop' => 'fill',
            'gravity' => 'auto',
            'quality' => 'auto',
            'fetch_format' => 'auto',
        ]);
    }

    public function videoThumbnail(string $publicId, int $width = 400, int $height = 300): string
    {
        $video = $this->cloudinary->video($publicId);
        $video->addTransformation([
            'width' => $width,
            'height' => $height,
            'crop' => 'fill',
            'format' => 'jpg',
        ]);

        return (string) $video->toUrl();
    }

    public function pdfPreview(string $publicId, int $page = 1): string
    {
        $image = $this->cloudinary->image($publicId);
        $image->addTransformation([
            'page' => $page,
            'format' => 'jpg',
            'width' => 800,
            'quality' => 'auto',
        ]);

        return (string) $image->toUrl();
    }

    public function generateSignedUploadParams(array $options = []): array
    {
        $timestamp = time();
        $params = array_merge([
            'timestamp' => $timestamp,
            'folder' => config('cloudinary.folder'),
        ], $options);

        $signature = $this->cloudinary->signatureUtils()->sign($params);

        return [
            'cloud_name' => config('cloudinary.cloud_name'),
            'api_key' => config('cloudinary.api_key'),
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder' => $params['folder'],
        ];
    }

    public function getAsset(string $publicId, string $resourceType = 'image'): array
    {
        return (array) $this->adminApi->asset($publicId, ['resource_type' => $resourceType]);
    }

    public function listFolder(string $folder): array
    {
        return (array) $this->adminApi->assets(['type' => 'upload', 'prefix' => $folder]);
    }

    protected function detectResourceType(UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/')) {
            return 'video';
        }

        return 'raw';
    }

    protected function detectMediaType(UploadedFile $file): string
    {
        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        $docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'odt'];
        if (in_array($ext, $docExts, true)) {
            return 'document';
        }

        $archiveExts = ['zip', 'tar', 'gz', 'rar', '7z'];
        if (in_array($ext, $archiveExts, true)) {
            return 'archive';
        }

        return 'other';
    }

    protected function buildFolder(mixed $customFolder, string $resourceType): string
    {
        $base = config('cloudinary.folder', 'app');
        $subDir = match ($resourceType) {
            'image' => 'images',
            'video' => 'videos',
            'raw' => 'documents',
            default => 'files',
        };

        $customFolder = is_string($customFolder) ? trim($customFolder) : null;

        return ($customFolder !== null && $customFolder !== '')
            ? "{$base}/{$customFolder}"
            : "{$base}/{$subDir}";
    }

    protected function generatePublicId(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($name) ?: 'file';
        $uid = Str::random(10);

        return "{$slug}_{$uid}";
    }

    protected function filterUploadOptions(array $options): array
    {
        unset($options['collection']);

        if (isset($options['folder']) && ! is_string($options['folder'])) {
            unset($options['folder']);
        }

        return $options;
    }

    protected function defaultUploadOptions(string $resourceType): array
    {
        $base = [
            'overwrite' => false,
            'unique_filename' => true,
            'use_filename' => true,
        ];

        return match ($resourceType) {
            'image' => array_merge($base, [
                'quality' => 'auto',
                'fetch_format' => 'auto',
                'flags' => 'progressive',
            ]),
            'video' => array_merge($base, [
                'resource_type' => 'video',
                'chunk_size' => 6_000_000,
                'eager' => [
                    ['format' => 'mp4', 'quality' => 'auto'],
                ],
                'eager_async' => true,
            ]),
            default => $base,
        };
    }

    protected function normaliseResult(array $result, UploadedFile $file): array
    {
        return array_merge($result, [
            '_media_type' => $this->detectMediaType($file),
            '_original_name' => $file->getClientOriginalName(),
            '_size' => $file->getSize(),
            '_mime' => $file->getMimeType(),
            '_extension' => strtolower($file->getClientOriginalExtension()),
        ]);
    }
}
