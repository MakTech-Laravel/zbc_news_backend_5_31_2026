<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function getAllRoles(): Collection
    {
        return Role::with('permissions')->get();
    }

    public function create(array $data): Role
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'api'
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

        $role->update([
            'name' => $data['name']
        ]);

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
                'guard_name' => 'api'
            ]);
        }

        $role->syncPermissions($permissions);
    }

    public function delete(Role $role): void
    {
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
