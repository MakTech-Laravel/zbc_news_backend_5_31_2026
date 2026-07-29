<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CookiePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hero_meta' => $this->hero_meta,
            'hero_description' => $this->hero_description,
            'preferences_intro' => $this->preferences_intro,
            'categories' => $this->categories,
            'browser_intro' => $this->browser_intro,
            'browser_controls' => $this->browser_controls,
            'faqs' => $this->faqs,
            'contact_heading' => $this->contact_heading,
            'contact_description' => $this->contact_description,
            'contact_email' => $this->contact_email,
            'banner_title' => $this->banner_title,
            'banner_description' => $this->banner_description,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
