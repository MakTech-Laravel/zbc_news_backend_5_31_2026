<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_of_service_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_meta');
            $table->longText('quick_summary');
            $table->longText('account_terms');
            $table->longText('content_ip');
            $table->longText('subscriptions');
            $table->longText('prohibited');
            $table->longText('disputes');
            $table->longText('contact');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_of_service_settings');
    }
};
