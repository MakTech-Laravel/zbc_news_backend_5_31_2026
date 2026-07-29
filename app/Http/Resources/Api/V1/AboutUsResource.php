<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AboutUsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hero_title' => $this->hero_title,
            'hero_subtitle' => $this->hero_subtitle,
            'intro_html' => $this->intro_html,
            'values' => $this->values,
            'leadership_subtitle' => $this->leadership_subtitle,
            'leaders' => $this->leaders,
            'journey' => $this->journey,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
