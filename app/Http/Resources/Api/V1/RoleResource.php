<?php

namespace App\Http\Resources\Api\V1;

use App\Support\SystemRoles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isProtected = (bool) ($this->is_protected ?? SystemRoles::isProtected($this->name));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name ?: SystemRoles::displayName((string) $this->name),
            'is_protected' => $isProtected,
            'guard_name' => $this->guard_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'guard_name' => $permission->guard_name,
                    ];
                });
            }),
        ];
    }
}
