<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactInquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'message' => $this->message,
            'messagePreview' => str($this->message)->limit(160)->toString(),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'ipAddress' => $this->ip_address,
            'userAgent' => $this->user_agent,
            'repliedAt' => $this->replied_at?->toIso8601String(),
            'repliedAtLabel' => $this->replied_at?->diffForHumans(),
            'submittedAt' => $this->created_at?->toIso8601String(),
            'submittedAtLabel' => $this->created_at?->diffForHumans() ?? '',
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'updatedAtLabel' => $this->updated_at?->diffForHumans() ?? '',
            'replies' => ContactInquiryReplyResource::collection(
                $this->whenLoaded('replies'),
            ),
        ];
    }
}
