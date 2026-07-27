<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sidebar_section_settings') && ! Schema::hasTable('sub_menu_settings')) {
            Schema::rename('sidebar_section_settings', 'sub_menu_settings');
        }

        if (Schema::hasTable('sidebar_featured_articles') && ! Schema::hasTable('sub_menu_featured_articles')) {
            Schema::rename('sidebar_featured_articles', 'sub_menu_featured_articles');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sub_menu_settings') && ! Schema::hasTable('sidebar_section_settings')) {
            Schema::rename('sub_menu_settings', 'sidebar_section_settings');
        }

        if (Schema::hasTable('sub_menu_featured_articles') && ! Schema::hasTable('sidebar_featured_articles')) {
            Schema::rename('sub_menu_featured_articles', 'sidebar_featured_articles');
        }
    }
};
