<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_id' => $this->menu_id,
            'parent_id' => $this->parent_id,
            'type' => $this->type,
            'label' => $this->label,
            'url' => $this->url,
            'target' => $this->target instanceof \BackedEnum ? $this->target->value : $this->target,
            'icon' => $this->icon,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'meta' => $this->meta,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'title' => $this->category->title,
                'slug' => $this->category->slug,
            ] : null),
            'children' => MenuItemResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
