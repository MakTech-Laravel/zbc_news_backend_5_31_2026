<?php

use App\Models\Role;
use App\Support\SystemRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $rolesTable = $tableNames['roles'] ?? 'roles';

        Schema::table($rolesTable, function (Blueprint $table): void {
            $table->boolean('is_protected')->default(false)->after('guard_name');
            $table->string('display_name')->nullable()->after('is_protected');
        });

        foreach (SystemRoles::protectedDefinitions() as $slug => $label) {
            $role = Role::query()->firstOrCreate(
                ['name' => $slug, 'guard_name' => 'api'],
                [
                    'is_protected' => true,
                    'display_name' => $label,
                ],
            );

            $role->update([
                'is_protected' => true,
                'display_name' => $label,
            ]);
        }
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $rolesTable = $tableNames['roles'] ?? 'roles';

        Schema::table($rolesTable, function (Blueprint $table): void {
            $table->dropColumn(['is_protected', 'display_name']);
        });
    }
};
