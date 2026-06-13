<?php

namespace App\Http\Resources\Api\V1;

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
            'sub_title' => $this->sub_title,
            'excerpt' => $this->excerpt,
            'article_description' => $this->article_description,

            'status' => $this->status?->value ?? $this->status,
            'visibility' => $this->visibility?->value ?? $this->visibility,
            'featured_image' => $this->featured_image,
            'open_graph_image' => $this->open_graph_image,

            'scheduled_publishing' => $this->scheduled_publishing,
            'published_at' => $this->published_at,
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

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}