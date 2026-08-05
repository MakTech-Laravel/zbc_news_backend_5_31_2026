<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_task_failures', function (Blueprint $table) {
            $table->string('failed_job_uuid')->nullable()->index()->after('task_type');
            $table->string('queue_connection', 64)->nullable()->after('failed_job_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_task_failures', function (Blueprint $table) {
            $table->dropIndex(['failed_job_uuid']);
            $table->dropColumn(['failed_job_uuid', 'queue_connection']);
        });
    }
};
