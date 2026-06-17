<?php

namespace App\Services;

use App\Models\Role;
use App\Support\SystemRoles;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class RoleService
{
    public function getAllRoles(): Collection
    {
        return Role::with('permissions')->orderByDesc('is_protected')->orderBy('name')->get();
    }

    public function create(array $data): Role
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'api',
            'is_protected' => false,
            'display_name' => null,
        ]);

        if (!empty($permissions)) {
            $role->syncPermissions($permissions);
        }

        activity()
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->withProperties([
                'role_name' => $role->name,
                'permissions' => $permissions,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Role created');

        return $role->load('permissions');
    }

    public function getById(int $id): ?Role
    {
        return Role::with('permissions')->find($id);
    }

    public function update(Role $role, array $data): Role
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        if (SystemRoles::isProtected($role)) {
            $requestedName = isset($data['name']) ? strtolower(trim((string) $data['name'])) : null;
            if ($requestedName !== null && $requestedName !== strtolower($role->name)) {
                throw ValidationException::withMessages([
                    'name' => ['System role names cannot be changed. You can update permissions only.'],
                ]);
            }
        } else {
            $role->update([
                'name' => $data['name'],
            ]);
        }

        if (!empty($permissions)) {
            $this->syncPermissions($role, $permissions);
        }

        activity()
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->withProperties([
                'role_name' => $role->name,
                'permissions' => $permissions,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Role updated');

        return $role->load('permissions');
    }

    private function syncPermissions(Role $role, array $permissions): void
    {
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'api',
            ]);
        }

        $role->syncPermissions($permissions);
    }

    public function delete(Role $role): void
    {
        if (SystemRoles::isProtected($role)) {
            throw ValidationException::withMessages([
                'role' => ['This system role cannot be deleted. You can update its permissions instead.'],
            ]);
        }

        $role->delete();

        activity()
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->withProperties([
                'role_name' => $role->name,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Role deleted');
    }

    public function restore($id)
    {
        $role = Role::withTrashed()
            ->where('id', $id)
            ->firstOrFail();

        $role->restore();

        return $role;
    }
}
