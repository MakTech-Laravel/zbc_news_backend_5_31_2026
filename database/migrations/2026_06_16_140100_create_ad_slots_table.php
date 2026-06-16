<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_slots', function (Blueprint $table): void {
            $table->id();
            $table->string('slot_key')->unique();
            $table->string('name');
            $table->string('placement')->nullable();
            $table->string('provider', 20)->default('manual'); // google|manual
            $table->boolean('is_active')->default(false);
            $table->string('google_ad_client')->nullable();
            $table->string('google_ad_slot')->nullable();
            $table->string('manual_image_url')->nullable();
            $table->string('manual_click_url')->nullable();
            $table->text('manual_html')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_slots');
    }
};

