<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessibilityReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'issue' => $this->issue,
            'issuePreview' => str($this->issue)->limit(160)->toString(),
            'pageUrl' => $this->page_url,
            'email' => $this->email,
            'status' => $this->status,
            'statusLabel' => str($this->status)->headline()->toString(),
            'ipAddress' => $this->ip_address,
            'userAgent' => $this->user_agent,
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
            'resolvedAtLabel' => $this->resolved_at?->diffForHumans(),
            'submittedAt' => $this->created_at?->toIso8601String(),
            'submittedAtLabel' => $this->created_at?->diffForHumans() ?? '',
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'updatedAtLabel' => $this->updated_at?->diffForHumans() ?? '',
        ];
    }
}
