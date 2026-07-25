<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BreakingNewsItem */
class BreakingNewsItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $article = $this->whenLoaded('article') ? $this->article : null;

        return [
            'id' => $this->id,
            'article_id' => $this->article_id,
            'headline' => $this->displayHeadline(),
            'headline_override' => $this->headline_override,
            'priority' => $this->priority,
            'status' => $this->status?->value ?? $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_live' => $this->isLive(),
            'notified_at' => $this->notified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'article' => $this->when($article !== null, function () use ($article) {
                return [
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
                ];
            }),
            // Flat fields for public ticker compatibility
            'title' => $this->displayHeadline(),
            'slug' => $article?->slug,
        ];
    }
}
