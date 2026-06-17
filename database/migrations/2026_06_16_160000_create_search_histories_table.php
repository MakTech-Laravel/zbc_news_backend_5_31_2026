<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 64)->nullable();
            $table->string('query', 255);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('searched_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'searched_at']);
            $table->index(['session_id', 'searched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_histories');
    }
};
