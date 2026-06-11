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
        Schema::create('article_histroys', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamp('read_end_at')->nullable();

            $table->string('session_id')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->unsignedInteger('time_spent')->default(0);
            $table->tinyInteger('scroll_depth')->default(0);
            $table->boolean('is_guest')->default(false);

            $table->foreign('article_id')->references('id')->on('articles')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_histroys');
    }
};
