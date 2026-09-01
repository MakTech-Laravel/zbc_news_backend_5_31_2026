<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULTS = [
        'social_facebook_url' => 'https://facebook.com/zomibroadcasting',
        'social_x_url' => 'https://x.com/zbcglobalnews',
        'social_linkedin_url' => 'https://www.linkedin.com/company/zbcnews',
        'social_tiktok_url' => 'https://www.tiktok.com/@zbcnews',
        'social_instagram_url' => 'https://www.instagram.com/zomibroadcasting',
        'contact_general_email' => 'info@zbc.news',
        'contact_press_email' => 'newsroom@zbc.news',
        'contact_advertising_email' => 'ads@zbc.news',
        'contact_corrections_email' => 'corrections@zbc.news',
    ];

    /** @var list<string> */
    private array $legacyColumns = [
        'social_facebook_url',
        'social_x_url',
        'social_linkedin_url',
        'social_tiktok_url',
        'social_instagram_url',
        'contact_general_email',
        'contact_press_email',
        'contact_advertising_email',
        'contact_corrections_email',
    ];

    public function up(): void
    {
        foreach ($this->legacyColumns as $column) {
            if (Schema::hasColumn('site_settings', $column)) {
                Schema::table('site_settings', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }

        if (! Schema::hasColumn('site_settings', 'social_contact_settings')) {
            Schema::table('site_settings', function (Blueprint $table): void {
                $table->json('social_contact_settings')->nullable()->after('admin_notification_email');
            });
        }

        DB::table('site_settings')
            ->select('id', 'social_contact_settings')
            ->orderBy('id')
            ->get()
            ->each(function ($row): void {
                $current = json_decode((string) ($row->social_contact_settings ?? ''), true);
                $merged = array_merge(self::DEFAULTS, is_array($current) ? $current : []);

                DB::table('site_settings')
                    ->where('id', $row->id)
                    ->update(['social_contact_settings' => json_encode($merged)]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('site_settings', 'social_contact_settings')) {
            Schema::table('site_settings', function (Blueprint $table): void {
                $table->dropColumn('social_contact_settings');
            });
        }
    }
};
