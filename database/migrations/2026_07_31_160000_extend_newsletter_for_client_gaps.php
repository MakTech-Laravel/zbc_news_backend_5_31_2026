<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_campaigns', function (Blueprint $table): void {
            if (! Schema::hasColumn('newsletter_campaigns', 'article_id')) {
                $table->foreignId('article_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('articles')
                    ->nullOnDelete();
            }
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_settings', 'brevo_list_id')) {
                $table->string('brevo_list_id')->nullable()->after('brevo_api_key');
            }
            if (! Schema::hasColumn('site_settings', 'newsletter_webhook_secret')) {
                $table->string('newsletter_webhook_secret')->nullable()->after('brevo_list_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_campaigns', function (Blueprint $table): void {
            if (Schema::hasColumn('newsletter_campaigns', 'article_id')) {
                $table->dropConstrainedForeignId('article_id');
            }
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $columns = [];
            if (Schema::hasColumn('site_settings', 'brevo_list_id')) {
                $columns[] = 'brevo_list_id';
            }
            if (Schema::hasColumn('site_settings', 'newsletter_webhook_secret')) {
                $columns[] = 'newsletter_webhook_secret';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
