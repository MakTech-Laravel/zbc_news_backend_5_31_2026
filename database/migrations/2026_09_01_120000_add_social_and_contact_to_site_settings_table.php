<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->text('social_facebook_url')->nullable()->after('admin_notification_email');
            $table->text('social_x_url')->nullable()->after('social_facebook_url');
            $table->text('social_linkedin_url')->nullable()->after('social_x_url');
            $table->text('social_tiktok_url')->nullable()->after('social_linkedin_url');
            $table->text('social_instagram_url')->nullable()->after('social_tiktok_url');
            $table->string('contact_general_email')->nullable()->after('social_instagram_url');
            $table->string('contact_press_email')->nullable()->after('contact_general_email');
            $table->string('contact_advertising_email')->nullable()->after('contact_press_email');
            $table->string('contact_corrections_email')->nullable()->after('contact_advertising_email');
            $table->text('contact_office_address')->nullable()->after('contact_corrections_email');
            $table->text('contact_office_maps_url')->nullable()->after('contact_office_address');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'social_facebook_url',
                'social_x_url',
                'social_linkedin_url',
                'social_tiktok_url',
                'social_instagram_url',
                'contact_general_email',
                'contact_press_email',
                'contact_advertising_email',
                'contact_corrections_email',
                'contact_office_address',
                'contact_office_maps_url',
            ]);
        });
    }
};
