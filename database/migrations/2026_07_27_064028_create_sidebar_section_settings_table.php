<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidebar_section_settings', function (Blueprint $table) {
            $table->id();
            $table->string('section_key', 50)->unique();
            $table->unsignedInteger('limit')->default(5);
            $table->unsignedInteger('trending_window_hours')->default(24);
            $table->string('most_read_default_period', 20)->default('today');
            $table->unsignedInteger('pinned_slots')->default(3);
            $table->boolean('is_enabled')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('sidebar_section_settings')->insert([
            [
                'section_key' => 'trending',
                'limit' => 5,
                'trending_window_hours' => 24,
                'most_read_default_period' => 'today',
                'pinned_slots' => 3,
                'is_enabled' => true,
                'config' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'section_key' => 'most_read',
                'limit' => 5,
                'trending_window_hours' => 24,
                'most_read_default_period' => 'today',
                'pinned_slots' => 3,
                'is_enabled' => true,
                'config' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'section_key' => 'live_updates',
                'limit' => 5,
                'trending_window_hours' => 24,
                'most_read_default_period' => 'today',
                'pinned_slots' => 3,
                'is_enabled' => true,
                'config' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'section_key' => 'editorial_picks',
                'limit' => 5,
                'trending_window_hours' => 24,
                'most_read_default_period' => 'today',
                'pinned_slots' => 3,
                'is_enabled' => true,
                'config' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sidebar_section_settings');
    }
};
