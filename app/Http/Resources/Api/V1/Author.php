<?php

namespace App\Http\Resources\Api\V1;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Author extends JsonResource
{
    /**
     * Public author profile (no email, roles, or permissions).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $info = $this->userInformation;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'bio' => $info?->bio,
            'profile_image' => MediaUrl::resolvePublic($info?->profile_image),
            'public_title' => $info?->public_title,
            'social_links' => $info?->social_links ?? [],
            'published_articles_count' => (int) ($this->published_articles_count ?? 0),
        ];
    }
}
