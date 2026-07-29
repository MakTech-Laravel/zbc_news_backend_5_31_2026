<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookie_policy_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_meta');
            $table->text('hero_description');
            $table->text('preferences_intro');
            $table->json('categories');
            $table->text('browser_intro');
            $table->json('browser_controls');
            $table->json('faqs');
            $table->string('contact_heading');
            $table->text('contact_description');
            $table->string('contact_email');
            $table->string('banner_title');
            $table->text('banner_description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_policy_settings');
    }
};
