<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->index();
            $table->string('purpose', 32)->index();
            $table->string('code');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_otp_codes');
    }
};
