<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermisionDefaultUser extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->permissionsSeeder();
        $this->rolesSeeder();
        $this->assignPermissionsToRoles();
    }

    private function assignPermissionsToRoles(): void
    {
      User::create([
        'name' => 'Super Admin',
        'email' => 'superadmin@dev.com',
        'password' => Hash::make('superadmin@dev.com'),
      ])->assignRole('super_admin');

      User::create([
        'name' => 'Admin',
        'email' => 'admin@dev.com',
        'password' => Hash::make('admin@dev.com'),
      ])->assignRole('admin');

      User::create([
        'name' => 'Editor',
        'email' => 'editor@dev.com',
        'password' => Hash::make('editor@dev.com'),
      ])->assignRole('editor');
      
      User::create([
        'name' => 'Author',
        'email' => 'author@dev.com',
        'password' => Hash::make('author@dev.com'),
      ])->assignRole('author');
      
      
      User::create([
        'name' => 'Sub Editor',
        'email' => 'subeditor@dev.com',
        'password' => Hash::make('subeditor@dev.com'),
      ])->assignRole('sub_editor');
      
      User::create([
        'name' => 'User',
        'email' => 'user@dev.com',
        'password' => Hash::make('user@dev.com'),
      ])->assignRole('user');
        
      $this->command->info('Default users seeded successfully');
            
    }
    private function permissionsSeeder(): void
    {
        $permissionsCsv = fopen(database_path('data/permissions.csv'), 'r');

        $header = fgetcsv($permissionsCsv, 0, ',');

        $rows = [];

        while (($row = fgetcsv($permissionsCsv, 0, ',')) !== false) {
            $rows[] = array_combine($header, $row);
        }

        fclose($permissionsCsv);

        Permission::insert($rows);

        $this->command->info('Permissions seeded successfully');
    }

    private function rolesSeeder(): void
    {
        $rolesCsv = fopen(database_path('data/roles.csv'), 'r');
        $header = fgetcsv($rolesCsv, 0, ',');
        $rows = [];
        while (($row = fgetcsv($rolesCsv, 0, ',')) !== false) {
            $rows[] = array_combine($header, $row);
        }
        fclose($rolesCsv);
        Role::insert($rows);
        $this->command->info('Roles seeded successfully');
    }
}
