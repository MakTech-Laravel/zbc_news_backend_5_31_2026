<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SubMenuFeaturedArticle */
class SubMenuFeaturedArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $article = $this->whenLoaded('article') ? $this->article : null;
        $creator = $this->whenLoaded('creator') ? $this->creator : null;

        return [
            'id' => $this->id,
            'section_key' => $this->section_key?->value ?? $this->section_key,
            'article_id' => $this->article_id,
            'sort_order' => (int) $this->sort_order,
            'is_pinned' => (bool) $this->is_pinned,
            'is_active' => (bool) $this->is_active,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'creator' => $this->when($creator !== null, fn () => [
                'id' => $creator?->id,
                'name' => $creator?->name,
            ]),
            'article' => $this->when($article !== null, fn () => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'status' => $article->status?->value ?? $article->status,
                'published_at' => $article->published_at?->toIso8601String(),
                'category' => $article->relationLoaded('category') && $article->category
                    ? [
                        'id' => $article->category->id,
                        'title' => $article->category->title,
                        'slug' => $article->category->slug,
                    ]
                    : null,
                'user' => $article->relationLoaded('user') && $article->user
                    ? [
                        'id' => $article->user->id,
                        'name' => $article->user->name,
                    ]
                    : null,
            ]),
        ];
    }
}