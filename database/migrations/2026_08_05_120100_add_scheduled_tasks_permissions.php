<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            ['name' => 'scheduled-tasks.list', 'group_name' => 'Scheduled Tasks'],
            ['name' => 'scheduled-tasks.rerun', 'group_name' => 'Scheduled Tasks'],
        ] as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'api'],
                ['group_name' => $permission['group_name']],
            );
        }

        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'api')->first();
            if (! $role) {
                continue;
            }

            $role->givePermissionTo([
                'scheduled-tasks.list',
                'scheduled-tasks.rerun',
            ]);
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'api')->first();
            if ($role) {
                $role->revokePermissionTo([
                    'scheduled-tasks.list',
                    'scheduled-tasks.rerun',
                ]);
            }
        }

        Permission::query()
            ->where('guard_name', 'api')
            ->whereIn('name', ['scheduled-tasks.list', 'scheduled-tasks.rerun'])
            ->delete();
    }
};
