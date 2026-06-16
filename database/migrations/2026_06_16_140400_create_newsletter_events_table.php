<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('newsletter_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('newsletter_subscriber_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 20); // sent|open|click|unsubscribe
            $table->string('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_events');
    }
};

