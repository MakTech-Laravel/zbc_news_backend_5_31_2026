<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('is_live')->default(false)->after('is_breaking');
            $table->timestamp('live_started_at')->nullable()->after('is_live');
            $table->timestamp('live_ended_at')->nullable()->after('live_started_at');

            $table->index('is_live');
            $table->index('live_started_at');
            $table->index('live_ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['is_live']);
            $table->dropIndex(['live_started_at']);
            $table->dropIndex(['live_ended_at']);

            $table->dropColumn(['is_live', 'live_started_at', 'live_ended_at']);
        });
    }
};