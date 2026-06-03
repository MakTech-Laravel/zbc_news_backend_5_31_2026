<?php

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
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 255)->nullable();
            $table->string('site_tag', 255)->nullable();
            $table->string('site_logo', 255)->nullable();
            $table->bigInteger('timezone');
            $table->unsignedBigInteger('default_category_id')->nullable();
            $table->integer('posts_per_page')->default(10);
            $table->boolean('allow_comments')->default(false);
            $table->boolean('authenticate_comment_only')->default(true);
            $table->integer('related_article')->default(10);
            $table->bigInteger('pixeld_id')->nullable();
            $table->bigInteger('g_messurment_id')->nullable();
            $table->string('g_api_secrete')->nullable();
            $table->boolean('enable_comments')->default(true);
            
            $table->foreign('default_category_id')->references('id')->on('article_categories')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
