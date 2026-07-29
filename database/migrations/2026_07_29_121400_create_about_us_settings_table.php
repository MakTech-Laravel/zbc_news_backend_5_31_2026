<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('about_us_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_title');
            $table->string('hero_subtitle');
            $table->longText('intro_html');
            $table->json('values');
            $table->string('leadership_subtitle');
            $table->json('leaders');
            $table->json('journey');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_us_settings');
    }
};
