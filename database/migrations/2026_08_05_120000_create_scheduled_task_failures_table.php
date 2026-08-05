<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_task_failures', function (Blueprint $table) {
            $table->id();
            $table->string('task_key')->index();
            $table->string('task_name');
            $table->string('task_type', 32); // job|command
            $table->text('exception_message');
            $table->longText('exception_trace')->nullable();
            $table->string('status', 32)->default('failed')->index(); // failed|resolved|rerun_queued
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('failed_at')->index();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_failures');
    }
};
