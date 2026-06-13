<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermisionDefaultUser extends Seeder
{
    public function run(): void
    {
        $this->permissionsSeeder();
        $this->rolesSeeder();
        $this->assignPermissionsToRoles();
        $this->defaultUsersSeeder();
    }

    private function defaultUsersSeeder(): void
    {
        $users = [
            ['name' => 'Super Admin', 'email' => 'superadmin@dev.com', 'role' => 'super_admin'],
            ['name' => 'Admin', 'email' => 'admin@dev.com', 'role' => 'admin'],
            ['name' => 'Editor', 'email' => 'editor@dev.com', 'role' => 'editor'],
            ['name' => 'Author', 'email' => 'author@dev.com', 'role' => 'author'],
            ['name' => 'Sub Editor', 'email' => 'subeditor@dev.com', 'role' => 'sub_editor'],
            ['name' => 'Journalist', 'email' => 'journalist@dev.com', 'role' => 'journalist'],
            ['name' => 'User', 'email' => 'user@dev.com', 'role' => 'user'],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($data['email']),
                ]
            );

            if (! $user->hasRole($data['role'])) {
                $user->assignRole($data['role']);
            }
        }

        $this->command->info('Default users seeded successfully');
    }

    private function permissionsSeeder(): void
    {
        $permissionsCsv = fopen(database_path('data/permissions.csv'), 'r');

        $header = fgetcsv($permissionsCsv, 0, ',');

        while (($row = fgetcsv($permissionsCsv, 0, ',')) !== false) {
            $data = array_combine($header, $row);

            Permission::firstOrCreate(
                ['name' => $data['name'], 'guard_name' => $data['guard_name']],
                ['group_name' => $data['group_name']]
            );
        }

        fclose($permissionsCsv);

        $this->command->info('Permissions seeded successfully');
    }

    private function rolesSeeder(): void
    {
        $rolesCsv = fopen(database_path('data/roles.csv'), 'r');
        $header = fgetcsv($rolesCsv, 0, ',');
        while (($row = fgetcsv($rolesCsv, 0, ',')) !== false) {
            $data = array_combine($header, $row);
            Role::firstOrCreate(
                ['name' => $data['name'], 'guard_name' => $data['guard_name']]
            );
        }
        fclose($rolesCsv);
        $this->command->info('Roles seeded successfully');
    }

    private function assignPermissionsToRoles(): void
    {
        $adminRole = Role::findByName('admin', 'api');
        $adminRole->syncPermissions(Permission::all());

        $rolePermissionsCsv = fopen(database_path('data/role_permissions.csv'), 'r');
        $header = fgetcsv($rolePermissionsCsv, 0, ',');
        $grouped = [];

        while (($row = fgetcsv($rolePermissionsCsv, 0, ',')) !== false) {
            $data = array_combine($header, $row);
            $grouped[$data['role_name']][] = $data['permission_name'];
        }

        fclose($rolePermissionsCsv);

        foreach ($grouped as $roleName => $permissionNames) {
            $role = Role::findByName($roleName, 'api');
            $role->syncPermissions($permissionNames);
        }

        $this->command->info('Role permissions assigned successfully');
    }
}
