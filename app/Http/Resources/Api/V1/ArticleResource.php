<?php

namespace App\Http\Resources\Api\V1;

use App\Support\ReadTime;
use App\Services\SeoMetaService;
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
            'read_time' => ReadTime::fromHtml($this->article_description),

            'status' => $this->status?->value ?? $this->status,
            'visibility' => $this->visibility?->value ?? $this->visibility,
            'featured_image' => $this->resolvePublicImageUrl($this->featured_image),
            'open_graph_image' => $this->resolvePublicImageUrl($this->open_graph_image),

            'scheduled_publishing' => $this->scheduled_publishing?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'views' => $this->views,
            'saves_count' => $this->save_articles_count ?? 0,

            // relations
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category?->id,
                    'title' => $this->category?->title,
                    'slug' => $this->category?->slug,
                ];
            }),

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'email' => $this->user?->email,
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

    private function resolvePublicImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $normalized = str_starts_with($path, '/') ? $path : '/' . $path;
        $publicFile = public_path(ltrim($normalized, '/'));

        if (! is_file($publicFile)) {
            return null;
        }

        return url($normalized);
    }
}