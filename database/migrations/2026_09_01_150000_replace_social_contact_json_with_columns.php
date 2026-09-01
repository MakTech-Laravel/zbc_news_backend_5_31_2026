<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Converts the earlier JSON `social_contact_settings` column (if present)
 * into nullable columns matching the rest of the schema style.
 * Safe no-op when columns already exist from a fresh migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('site_settings', 'social_contact_settings')) {
            Schema::table('site_settings', function (Blueprint $table) {
                $table->dropColumn('social_contact_settings');
            });
        }

        Schema::table('site_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('site_settings', 'social_facebook_url')) {
                $table->text('social_facebook_url')->nullable()->after('admin_notification_email');
            }
            if (! Schema::hasColumn('site_settings', 'social_x_url')) {
                $table->text('social_x_url')->nullable()->after('social_facebook_url');
            }
            if (! Schema::hasColumn('site_settings', 'social_linkedin_url')) {
                $table->text('social_linkedin_url')->nullable()->after('social_x_url');
            }
            if (! Schema::hasColumn('site_settings', 'social_tiktok_url')) {
                $table->text('social_tiktok_url')->nullable()->after('social_linkedin_url');
            }
            if (! Schema::hasColumn('site_settings', 'social_instagram_url')) {
                $table->text('social_instagram_url')->nullable()->after('social_tiktok_url');
            }
            if (! Schema::hasColumn('site_settings', 'contact_general_email')) {
                $table->string('contact_general_email')->nullable()->after('social_instagram_url');
            }
            if (! Schema::hasColumn('site_settings', 'contact_press_email')) {
                $table->string('contact_press_email')->nullable()->after('contact_general_email');
            }
            if (! Schema::hasColumn('site_settings', 'contact_advertising_email')) {
                $table->string('contact_advertising_email')->nullable()->after('contact_press_email');
            }
            if (! Schema::hasColumn('site_settings', 'contact_corrections_email')) {
                $table->string('contact_corrections_email')->nullable()->after('contact_advertising_email');
            }
            if (! Schema::hasColumn('site_settings', 'contact_office_address')) {
                $table->text('contact_office_address')->nullable()->after('contact_corrections_email');
            }
            if (! Schema::hasColumn('site_settings', 'contact_office_maps_url')) {
                $table->text('contact_office_maps_url')->nullable()->after('contact_office_address');
            }
        });
    }

    public function down(): void
    {
        // Intentionally empty: rolling back would reintroduce the JSON shape we removed.
    }
};
