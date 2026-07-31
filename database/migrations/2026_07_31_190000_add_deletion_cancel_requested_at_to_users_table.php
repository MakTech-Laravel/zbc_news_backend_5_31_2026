<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'deletion_cancel_requested_at')) {
                $table->timestamp('deletion_cancel_requested_at')
                    ->nullable()
                    ->after('deletion_cancel_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deletion_cancel_requested_at')) {
                $table->dropColumn('deletion_cancel_requested_at');
            }
        });
    }
};
