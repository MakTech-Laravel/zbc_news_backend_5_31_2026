<?php

namespace App\Http\Resources\Api\V1;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSuperAdmin = $this->hasRole('super_admin');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'roles' => $this->getRoleNames()->values()->all(),
            'is_super_admin' => $isSuperAdmin,
            // Plain permission name strings for frontend `can()` checks.
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'user_information' => $this->whenLoaded('userInformation', fn() => [
                'profile_image' => MediaUrl::resolvePublic($this->userInformation->profile_image),
                'bio'           => $this->userInformation->bio,
                'region'        => $this->userInformation->region,
                'public_title'  => $this->userInformation->public_title,
                'social_links'  => $this->userInformation->social_links ?? [],
            ]),
        ];
    }
}
