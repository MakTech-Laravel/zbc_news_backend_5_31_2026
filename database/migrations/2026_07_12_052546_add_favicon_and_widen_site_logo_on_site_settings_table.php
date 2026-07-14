<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE site_settings MODIFY site_logo TEXT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE site_settings ALTER COLUMN site_logo TYPE TEXT');
        }

        Schema::table('site_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('site_settings', 'favicon')) {
                $table->text('favicon')->nullable()->after('site_logo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (Schema::hasColumn('site_settings', 'favicon')) {
                $table->dropColumn('favicon');
            }
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE site_settings MODIFY site_logo VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE site_settings ALTER COLUMN site_logo TYPE VARCHAR(255)');
        }
    }
};
