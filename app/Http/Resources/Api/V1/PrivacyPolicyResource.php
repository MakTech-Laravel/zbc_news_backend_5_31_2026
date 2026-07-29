<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrivacyPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hero_meta' => $this->hero_meta,
            'plain_summary' => $this->plain_summary,
            'overview' => $this->overview,
            'data_we_collect' => $this->data_we_collect,
            'how_we_use' => $this->how_we_use,
            'your_rights' => $this->your_rights,
            'data_security' => $this->data_security,
            'third_parties' => $this->third_parties,
            'contact' => $this->contact,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
