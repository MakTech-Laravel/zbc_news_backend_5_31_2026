<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidebar_featured_articles', function (Blueprint $table) {
            $table->id();
            $table->string('section_key', 50)->index();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_pinned')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['section_key', 'article_id']);
            $table->index(['section_key', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidebar_featured_articles');
    }
};
