<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_policy_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_meta');
            $table->longText('plain_summary');
            $table->longText('overview');
            $table->longText('data_we_collect');
            $table->longText('how_we_use');
            $table->longText('your_rights');
            $table->longText('data_security');
            $table->longText('third_parties');
            $table->longText('contact');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_policy_settings');
    }
};
