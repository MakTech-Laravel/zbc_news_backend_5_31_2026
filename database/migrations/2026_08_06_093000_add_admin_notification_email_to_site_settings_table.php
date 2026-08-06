<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('site_settings', 'admin_notification_email')) {
            Schema::table('site_settings', function (Blueprint $table): void {
                $table->string('admin_notification_email')->nullable()->after('admin_notification_channels');
            });
        }

        DB::table('site_settings')
            ->whereNull('admin_notification_email')
            ->orWhere('admin_notification_email', '')
            ->update(['admin_notification_email' => 'newsroom@zbc.news']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('site_settings', 'admin_notification_email')) {
            Schema::table('site_settings', function (Blueprint $table): void {
                $table->dropColumn('admin_notification_email');
            });
        }
    }
};
