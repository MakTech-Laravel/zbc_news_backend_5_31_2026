<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TermsOfServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hero_meta' => $this->hero_meta,
            'quick_summary' => $this->quick_summary,
            'account_terms' => $this->account_terms,
            'content_ip' => $this->content_ip,
            'subscriptions' => $this->subscriptions,
            'prohibited' => $this->prohibited,
            'disputes' => $this->disputes,
            'contact' => $this->contact,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
