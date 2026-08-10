<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'original_filename' => $this->original_filename,
            'alt_text' => $this->alt_text,
            'caption' => $this->caption,
            'credit' => $this->credit,
            'copyright' => $this->copyright,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'media_type' => $this->media_type,
            'resource_type' => $this->resource_type,
            'size' => $this->size,
            'human_size' => $this->humanSize(),
            'url' => $this->url,
            'thumbnail_url' => $this->resolveThumbnailUrl(),
            'preview_url' => $this->preview_url,
            'collection' => $this->collection,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'transformations' => $this->whenLoaded('transformations', fn () =>
                $this->transformations->map(fn ($t) => [
                    'key' => $t->key,
                    'url' => $t->url,
                ])
            ),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function resolveThumbnailUrl(): ?string
    {
        $isImage = $this->media_type === 'image'
            || str_starts_with((string) $this->mime_type, 'image/');

        // Existing rows may store derived Cloudinary thumbs that 404 on live.
        // For images always expose the original delivery URL to the admin UI.
        if ($isImage) {
            return $this->url ?: $this->thumbnail_url;
        }

        return $this->thumbnail_url ?: $this->url;
    }
}
