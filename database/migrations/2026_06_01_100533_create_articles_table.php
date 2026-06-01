<?php

use App\Enums\ArticleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('seo_title');
            $table->text('sub_title')->nullable();
            $table->longText('article_description');
            $table->string('status')->index()->default(ArticleStatus::PENDING->value);
            $table->string('featured_image')->nullable();
            $table->unsignedBigInteger('article_category_id')->nullable();
            $table->string('excerpt')->nullable();
            $table->timestamp('scheduled_publishing')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();


            $table->foreign('article_category_id')->references('id')->on('article_categories')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
