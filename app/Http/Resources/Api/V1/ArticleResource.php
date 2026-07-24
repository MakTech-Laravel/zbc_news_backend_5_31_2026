<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Media;
use App\Services\SeoMetaService;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'sub_title' => $this->sub_title,
            'excerpt' => $this->excerpt,
            'article_description' => $this->article_description,
            'read_time' => $this->formattedReadTime(),
            'estimated_read_time' => $this->estimatedReadTime(),

            'status' => $this->status?->value ?? $this->status,
            'visibility' => $this->visibility?->value ?? $this->visibility,
            'featured_image' => MediaUrl::resolvePublic($this->featured_image),
            'open_graph_image' => MediaUrl::resolvePublic($this->open_graph_image),
            'featured_media' => $this->resolveFeaturedMediaPayload(),

            'scheduled_publishing' => $this->scheduled_publishing?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'views' => $this->views,
            'saves_count' => $this->save_articles_count ?? 0,
            'comments_count' => (int) ($this->comments_count ?? 0),

            // relations
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category?->id,
                    'title' => $this->category?->title,
                    'slug' => $this->category?->slug,
                ];
            }),

            'user' => $this->whenLoaded('user', function () {
                $info = $this->user?->userInformation;

                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'slug' => $this->user?->slug,
                    'bio' => $info?->bio,
                    'public_title' => $info?->public_title,
                    'profile_image' => MediaUrl::resolvePublic($info?->profile_image),
                ];
            }),

            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'tag' => $tag->tag,
                    ];
                });
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'seo' => $this->when(
                $this->relationLoaded('tags'),
                function () {
                    $resolved = app(SeoMetaService::class)->resolveArticleMeta($this->resource);

                    return $resolved['resolved'];
                },
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveFeaturedMediaPayload(): ?array
    {
        /** @var Media|null $featured */
        $featured = $this->resource->featuredMedia();
        /** @var Media|null $poster */
        $poster = $this->resource->posterMedia();

        if ($featured) {
            $type = match ($featured->media_type) {
                'video' => 'video',
                'audio' => 'audio',
                default => 'image',
            };

            $posterUrl = $poster?->url
                ?? $featured->thumbnail_url
                ?? ($type === 'image' ? $featured->url : null)
                ?? $this->featured_image;

            return [
                'uuid' => $featured->uuid,
                'type' => $type,
                'url' => MediaUrl::resolvePublic($featured->url),
                'thumbnail_url' => MediaUrl::resolvePublic($featured->thumbnail_url),
                'poster_url' => MediaUrl::resolvePublic($posterUrl),
                'poster_uuid' => $poster?->uuid,
                'mime_type' => $featured->mime_type,
            ];
        }

        if ($this->featured_image) {
            return [
                'uuid' => null,
                'type' => 'image',
                'url' => MediaUrl::resolvePublic($this->featured_image),
                'thumbnail_url' => MediaUrl::resolvePublic($this->featured_image),
                'poster_url' => MediaUrl::resolvePublic($this->featured_image),
                'poster_uuid' => null,
                'mime_type' => null,
            ];
        }

        return null;
    }
}
