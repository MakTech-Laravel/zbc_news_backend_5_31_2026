<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareerJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'department' => $this->department?->value ?? $this->department,
            'employment_type' => $this->employment_type?->value ?? $this->employment_type,
            'type' => $this->employment_type?->value ?? $this->employment_type,
            'location' => $this->location,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'sort_order' => $this->sort_order,
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'applications_count' => $this->whenCounted('applications'),
        ];
    }
}
