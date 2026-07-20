<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'items_count' => $this->whenCounted('items'),
            'locations' => $this->whenLoaded('locations', fn () => $this->locations->map(fn ($loc) => [
                'id' => $loc->id,
                'key' => $loc->key,
                'name' => $loc->name,
                'render_style' => $loc->render_style instanceof \BackedEnum
                    ? $loc->render_style->value
                    : $loc->render_style,
            ])->values()),
            'items' => $this->when(
                $this->relationLoaded('items') || $request->boolean('with_tree'),
                function () {
                    return app(\App\Services\MenuService::class)->buildTree($this->resource);
                }
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }
}
