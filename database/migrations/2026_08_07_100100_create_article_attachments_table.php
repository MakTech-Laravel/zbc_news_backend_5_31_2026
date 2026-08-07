<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('article_attachments')) {
            return;
        }

        Schema::create('article_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['article_id', 'media_id']);
            $table->index(['article_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_attachments');
    }
};
