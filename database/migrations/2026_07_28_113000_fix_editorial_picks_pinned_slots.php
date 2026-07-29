<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Editorial Picks defaulted to pinned_slots=0, which made Pin a no-op on
        // the public merge. Align with Trending so reserved pin slots work.
        DB::table('sub_menu_settings')
            ->where('section_key', 'editorial_picks')
            ->where('pinned_slots', 0)
            ->update([
                'pinned_slots' => 3,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('sub_menu_settings')
            ->where('section_key', 'editorial_picks')
            ->where('pinned_slots', 3)
            ->update([
                'pinned_slots' => 0,
                'updated_at' => now(),
            ]);
    }
};
