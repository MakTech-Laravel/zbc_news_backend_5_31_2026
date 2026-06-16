<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->change();
            $table->string('language', 16)->default('en')->after('timezone');
            $table->string('meta_title')->nullable()->after('site_logo');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('meta_keywords')->nullable()->after('meta_description');
            $table->string('default_post_format', 32)->default('Standard')->after('default_category_id');
            $table->boolean('enable_auto_save')->default(true)->after('default_post_format');
            $table->boolean('require_featured_image')->default(false)->after('enable_auto_save');
            $table->boolean('enable_ai_writing')->default(false)->after('require_featured_image');
            $table->boolean('auto_approve_known_users')->default(false)->after('authenticate_comment_only');
            $table->string('google_analytics_id')->nullable()->after('g_api_secrete');
            $table->string('facebook_pixel_id')->nullable()->after('google_analytics_id');
            $table->string('mailchimp_api_key')->nullable()->after('facebook_pixel_id');
            $table->string('disqus_shortname')->nullable()->after('mailchimp_api_key');
            $table->string('slack_webhook_url')->nullable()->after('disqus_shortname');
        });

        Schema::create('seo_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 64)->unique();
            $table->string('name');
            $table->string('url_path');
            $table->boolean('is_template')->default(false);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_pages');

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'language',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'default_post_format',
                'enable_auto_save',
                'require_featured_image',
                'enable_ai_writing',
                'auto_approve_known_users',
                'google_analytics_id',
                'facebook_pixel_id',
                'mailchimp_api_key',
                'disqus_shortname',
                'slack_webhook_url',
            ]);
        });
    }
};
