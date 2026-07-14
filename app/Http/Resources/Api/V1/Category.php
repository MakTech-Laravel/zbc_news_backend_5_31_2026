<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ArticleCategory;
use App\Services\SeoMetaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Category extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'sort_order' => (int) $this->sort_order,
            'is_featured' => (bool) $this->is_featured,
            'parent_id' => $this->parent_id,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'seo' => app(SeoMetaService::class)->resolveCategoryMeta($this->resource)['resolved'],
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'title' => $this->parent->title,
                'slug' => $this->parent->slug,
            ] : null),
            'children' => $this->whenLoaded('children', function () {
                return $this->children->map(function (ArticleCategory $child) {
                    return [
                        'id' => $child->id,
                        'title' => $child->title,
                        'slug' => $child->slug,
                        'status' => $child->status,
                        'sort_order' => (int) $child->sort_order,
                        'is_featured' => (bool) $child->is_featured,
                        'parent_id' => $child->parent_id,
                    ];
                })->values();
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
