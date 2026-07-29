<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('careers_page_settings', function (Blueprint $table) {
            $table->id();
            $table->json('hero');
            $table->json('stats');
            $table->json('perks_section');
            $table->json('perks');
            $table->json('positions_section');
            $table->json('hiring_section');
            $table->json('hiring_steps');
            $table->json('testimonials_section');
            $table->json('testimonials');
            $table->json('faq_section');
            $table->json('faqs');
            $table->json('cta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('careers_page_settings');
    }
};
