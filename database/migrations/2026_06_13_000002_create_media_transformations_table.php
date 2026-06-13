<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_transformations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('url');
            $table->json('transformation')->nullable();
            $table->timestamps();

            $table->unique(['media_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_transformations');
    }
};
