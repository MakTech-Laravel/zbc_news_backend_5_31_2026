<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('site_settings', 'google_adsense_client')) {
                $table->string('google_adsense_client')->nullable()->after('facebook_pixel_id');
            }
            if (! Schema::hasColumn('site_settings', 'google_adsense_banner_slot')) {
                $table->string('google_adsense_banner_slot')->nullable()->after('google_adsense_client');
            }
            if (! Schema::hasColumn('site_settings', 'google_adsense_sidebar_slot')) {
                $table->string('google_adsense_sidebar_slot')->nullable()->after('google_adsense_banner_slot');
            }
            if (! Schema::hasColumn('site_settings', 'google_adsense_square_slot')) {
                $table->string('google_adsense_square_slot')->nullable()->after('google_adsense_sidebar_slot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $columns = [
                'google_adsense_client',
                'google_adsense_banner_slot',
                'google_adsense_sidebar_slot',
                'google_adsense_square_slot',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('site_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
