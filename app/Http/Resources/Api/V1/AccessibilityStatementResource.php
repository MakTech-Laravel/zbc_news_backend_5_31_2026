<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessibilityStatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hero_eyebrow' => $this->hero_eyebrow,
            'hero_title' => $this->hero_title,
            'hero_intro' => $this->hero_intro,
            'badges' => $this->badges,
            'commitment_heading' => $this->commitment_heading,
            'commitment_paragraphs' => $this->commitment_paragraphs,
            'commitment_stats' => $this->commitment_stats,
            'features_heading' => $this->features_heading,
            'features' => $this->features,
            'shortcuts_heading' => $this->shortcuts_heading,
            'keyboard_shortcuts' => $this->keyboard_shortcuts,
            'technologies_heading' => $this->technologies_heading,
            'supported_technologies' => $this->supported_technologies,
            'known_limitations' => $this->known_limitations,
            'report_heading' => $this->report_heading,
            'report_intro' => $this->report_intro,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'contact_address' => $this->contact_address,
            'cta_text' => $this->cta_text,
            'cta_button_label' => $this->cta_button_label,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
