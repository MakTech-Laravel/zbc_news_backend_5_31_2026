<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_live_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->longText('body');
            $table->timestamp('posted_at')->index();
            $table->string('status', 20)->default('published')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['article_id', 'status', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_live_updates');
    }
};
