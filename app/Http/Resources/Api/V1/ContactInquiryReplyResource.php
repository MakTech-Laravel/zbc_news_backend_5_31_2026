<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactInquiryReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'subject' => $this->subject,
            'body' => $this->body,
            'sentAt' => $this->sent_at?->toIso8601String(),
            'sentAtLabel' => $this->sent_at?->diffForHumans() ?? '',
            'adminName' => $this->whenLoaded('user', fn () => $this->user?->name),
        ];
    }
}
