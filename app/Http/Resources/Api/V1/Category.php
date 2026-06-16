<?php

namespace App\Http\Resources\Api\V1;

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
            'id'        => $this->id,
            'title'     => $this->title,
            'slug'      => $this->slug,
            'status'    => $this->status,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'seo'       => app(SeoMetaService::class)->resolveCategoryMeta($this->resource)['resolved'],
            'parent'    => $this->whenLoaded('parent', fn() => [
                'id'    => $this->parent->id,
                'title' => $this->parent->title,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
