<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'deletion_requested_at')) {
                $table->timestamp('deletion_requested_at')->nullable()->after('privacy_accepted_at');
            }
            if (! Schema::hasColumn('users', 'scheduled_permanent_deletion_at')) {
                $table->timestamp('scheduled_permanent_deletion_at')->nullable()->after('deletion_requested_at');
            }
            if (! Schema::hasColumn('users', 'deletion_cancel_token')) {
                $table->string('deletion_cancel_token', 64)->nullable()->after('scheduled_permanent_deletion_at');
            }
            if (! Schema::hasColumn('users', 'permanently_deleted_at')) {
                $table->timestamp('permanently_deleted_at')->nullable()->after('deletion_cancel_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            foreach ([
                'deletion_requested_at',
                'scheduled_permanent_deletion_at',
                'deletion_cancel_token',
                'permanently_deleted_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
