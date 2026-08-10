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
            'is_breaking' => (bool) $this->is_breaking,
            'is_live' => (bool) ($this->is_live ?? false),
            'is_live_blog' => (bool) ($this->is_live_blog ?? false),
            'live_video_url' => $this->live_video_url,
            'live_started_at' => $this->live_started_at?->toIso8601String(),
            'live_ended_at' => $this->live_ended_at?->toIso8601String(),
            'live_updates' => $this->whenLoaded('liveUpdates', function () {
                return ArticleLiveUpdateResource::collection($this->liveUpdates);
            }),
            'breaking_news' => $this->whenLoaded('breakingNewsItem', function () {
                $item = $this->breakingNewsItem;
                if (! $item || ($item->status?->value ?? $item->status) === 'removed') {
                    return null;
                }

                return [
                    'id' => $item->id,
                    'headline_override' => $item->headline_override,
                    'priority' => $item->priority,
                    'status' => $item->status?->value ?? $item->status,
                    'starts_at' => $item->starts_at?->toIso8601String(),
                    'expires_at' => $item->expires_at?->toIso8601String(),
                    'is_live' => $item->isLive(),
                ];
            }),
            'featured_image' => MediaUrl::resolvePublic($this->featured_image),
            'open_graph_image' => MediaUrl::resolvePublic($this->open_graph_image),
            'featured_media' => $this->resolveFeaturedMediaPayload(),
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments
                    ->filter(fn ($attachment) => $attachment->media && $attachment->media->status === 'ready')
                    ->values()
                    ->map(function ($attachment) {
                        $media = $attachment->media;
                        $filename = MediaUrl::downloadFilename(
                            $media->original_filename,
                            $media->extension ?: MediaUrl::extensionFromMime($media->mime_type),
                            $attachment->label,
                        );

                        // Relative paths only — never url(). In Docker, url() uses the
                        // internal request host (e.g. "backend") which browsers cannot resolve.
                        $viewPath = '/api/v1/articles/'.$this->slug.'/attachments/'.$media->uuid.'?disposition=inline';
                        $downloadPath = '/api/v1/articles/'.$this->slug.'/attachments/'.$media->uuid.'?disposition=attachment';

                        return [
                            'id' => $attachment->id,
                            'label' => $attachment->label ?: $media->original_filename,
                            'uuid' => $media->uuid,
                            'url' => $viewPath,
                            'download_url' => $downloadPath,
                            'filename' => $filename,
                            'mime_type' => $media->mime_type,
                            'extension' => $media->extension ?: MediaUrl::extensionFromMime($media->mime_type),
                            'size' => $media->size,
                            'human_size' => $media->humanSize(),
                        ];
                    });
            }),

            'scheduled_publishing' => $this->scheduled_publishing?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'views' => isset($this->read_count) ? (int) $this->read_count : $this->views,
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

        if ($this->live_video_url) {
            return [
                'uuid' => null,
                'type' => 'video',
                'provider' => 'youtube',
                'url' => $this->live_video_url,
                'thumbnail_url' => null,
                'poster_url' => MediaUrl::resolvePublic($poster?->url ?? $this->featured_image),
                'poster_uuid' => $poster?->uuid,
                'mime_type' => 'text/html',
            ];
        }

        if ($featured) {
            $type = match ($featured->media_type) {
                'video' => 'video',
                'audio' => 'audio',
                default => 'image',
            };

            // Prefer the original Cloudinary delivery URL for images. Derived
            // thumbnail transforms (c_fill,g_auto,f_auto,…) often 404 on live and
            // then the home/editor UI shows an empty placeholder.
            $originalUrl = $featured->url ?: $this->featured_image;
            $posterUrl = $poster?->url
                ?? ($type === 'image' ? $originalUrl : null)
                ?? $featured->thumbnail_url
                ?? $originalUrl;

            $thumbnailUrl = $type === 'image'
                ? ($originalUrl ?: $featured->thumbnail_url)
                : ($featured->thumbnail_url ?: $posterUrl);

            return [
                'uuid' => $featured->uuid,
                'type' => $type,
                'provider' => 'native',
                'url' => MediaUrl::resolvePublic($featured->url),
                'thumbnail_url' => MediaUrl::resolvePublic($thumbnailUrl),
                'poster_url' => MediaUrl::resolvePublic($posterUrl),
                'poster_uuid' => $poster?->uuid,
                'mime_type' => $featured->mime_type,
                'alt_text' => $featured->alt_text,
                'caption' => $featured->caption,
                'credit' => $featured->credit,
                'copyright' => $featured->copyright,
            ];
        }

        if ($this->featured_image) {
            return [
                'uuid' => null,
                'type' => 'image',
                'provider' => 'native',
                'url' => MediaUrl::resolvePublic($this->featured_image),
                'thumbnail_url' => MediaUrl::resolvePublic($this->featured_image),
                'poster_url' => MediaUrl::resolvePublic($this->featured_image),
                'poster_uuid' => null,
                'mime_type' => null,
                'alt_text' => null,
                'caption' => null,
                'credit' => null,
                'copyright' => null,
            ];
        }

        return null;
    }
}
