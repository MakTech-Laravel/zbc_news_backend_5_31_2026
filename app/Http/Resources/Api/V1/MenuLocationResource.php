<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'render_style' => $this->render_style instanceof \BackedEnum
                ? $this->render_style->value
                : $this->render_style,
            'menu_id' => $this->menu_id,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'menu' => $this->whenLoaded('menu', fn () => $this->menu ? [
                'id' => $this->menu->id,
                'name' => $this->menu->name,
                'slug' => $this->menu->slug,
                'status' => $this->menu->status instanceof \BackedEnum
                    ? $this->menu->status->value
                    : $this->menu->status,
            ] : null),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
