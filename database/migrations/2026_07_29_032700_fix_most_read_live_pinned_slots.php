<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Align Most Read / Live Updates with Trending + Editorial defaults so
        // pin / reorder behavior is consistent across all sub-menu sections.
        DB::table('sub_menu_settings')
            ->whereIn('section_key', ['most_read', 'live_updates'])
            ->where('pinned_slots', 0)
            ->update([
                'pinned_slots' => 3,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('sub_menu_settings')
            ->whereIn('section_key', ['most_read', 'live_updates'])
            ->where('pinned_slots', 3)
            ->update([
                'pinned_slots' => 0,
                'updated_at' => now(),
            ]);
    }
};
