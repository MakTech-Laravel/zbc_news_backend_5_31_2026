<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accessibility_statement_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_eyebrow');
            $table->string('hero_title');
            $table->text('hero_intro');
            $table->json('badges');
            $table->string('commitment_heading');
            $table->json('commitment_paragraphs');
            $table->json('commitment_stats');
            $table->string('features_heading');
            $table->json('features');
            $table->string('shortcuts_heading');
            $table->json('keyboard_shortcuts');
            $table->string('technologies_heading');
            $table->json('supported_technologies');
            $table->text('known_limitations');
            $table->string('report_heading');
            $table->text('report_intro');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->string('contact_address');
            $table->string('cta_text');
            $table->string('cta_button_label');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accessibility_statement_settings');
    }
};
