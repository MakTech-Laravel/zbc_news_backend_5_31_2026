<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\SystemRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

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
            ['name' => 'Writer', 'email' => 'writer@dev.com', 'role' => 'writer'],
            ['name' => 'Moderator', 'email' => 'moderator@dev.com', 'role' => 'moderator'],
            ['name' => 'Subscriber', 'email' => 'subscriber@dev.com', 'role' => 'subscriber'],
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
        $protected = SystemRoles::protectedDefinitions();

        while (($row = fgetcsv($rolesCsv, 0, ',')) !== false) {
            $data = array_combine($header, $row);
            $slug = $data['name'];

            $role = Role::firstOrCreate(
                ['name' => $slug, 'guard_name' => $data['guard_name']],
                [
                    'is_protected' => array_key_exists($slug, $protected),
                    'display_name' => $protected[$slug] ?? null,
                ],
            );

            if (array_key_exists($slug, $protected)) {
                $role->update([
                    'is_protected' => true,
                    'display_name' => $protected[$slug],
                ]);
            }
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
