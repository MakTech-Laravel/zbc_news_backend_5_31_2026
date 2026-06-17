<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('source', 40)->nullable()->after('name');
            $table->boolean('is_premium')->default(false)->after('preferences');
            $table->json('audience_tags')->nullable()->after('is_premium');
            $table->string('provider_contact_id')->nullable()->after('audience_tags');
        });

        Schema::table('newsletter_campaigns', function (Blueprint $table): void {
            $table->string('preview_text')->nullable()->after('subject');
            $table->string('audience_type', 30)->default('all')->after('segments');
            $table->boolean('premium_only')->default(false)->after('audience_type');
            $table->unsignedInteger('failed_count')->default(0)->after('click_count');
        });

        Schema::table('newsletter_events', function (Blueprint $table): void {
            $table->dropColumn('meta');
        });

        Schema::table('newsletter_events', function (Blueprint $table): void {
            $table->json('meta')->nullable()->after('event_type');
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('newsletter_provider', 20)->default('smtp')->after('mailchimp_api_key');
            $table->string('newsletter_from_email')->nullable()->after('newsletter_provider');
            $table->string('newsletter_from_name')->nullable()->after('newsletter_from_email');
            $table->string('resend_api_key')->nullable()->after('newsletter_from_name');
            $table->string('brevo_api_key')->nullable()->after('resend_api_key');
            $table->string('mailchimp_list_id')->nullable()->after('brevo_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'newsletter_provider',
                'newsletter_from_email',
                'newsletter_from_name',
                'resend_api_key',
                'brevo_api_key',
                'mailchimp_list_id',
            ]);
        });

        Schema::table('newsletter_events', function (Blueprint $table): void {
            $table->string('meta')->nullable()->change();
        });

        Schema::table('newsletter_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['preview_text', 'audience_type', 'premium_only', 'failed_count']);
        });

        Schema::table('newsletter_subscribers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['source', 'is_premium', 'audience_tags', 'provider_contact_id']);
        });
    }
};
