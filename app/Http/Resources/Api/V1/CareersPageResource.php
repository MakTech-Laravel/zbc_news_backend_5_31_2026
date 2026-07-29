<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareersPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hero' => $this->hero,
            'stats' => $this->stats,
            'perks_section' => $this->perks_section,
            'perks' => $this->perks,
            'positions_section' => $this->positions_section,
            'hiring_section' => $this->hiring_section,
            'hiring_steps' => $this->hiring_steps,
            'testimonials_section' => $this->testimonials_section,
            'testimonials' => $this->testimonials,
            'faq_section' => $this->faq_section,
            'faqs' => $this->faqs,
            'cta' => $this->cta,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
