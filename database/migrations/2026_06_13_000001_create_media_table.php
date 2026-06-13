<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->ulid('uuid')->unique();

            $table->nullableMorphs('mediable');

            $table->string('cloudinary_public_id')->unique();
            $table->string('cloudinary_version')->nullable();
            $table->string('cloudinary_signature')->nullable();

            $table->string('original_filename');
            $table->string('disk_name')->nullable();
            $table->string('mime_type');
            $table->string('extension', 20);
            $table->enum('resource_type', ['image', 'video', 'raw', 'auto'])->default('auto');
            $table->enum('media_type', [
                'image', 'video', 'document', 'audio', 'archive', 'other',
            ])->default('other');
            $table->unsignedBigInteger('size');
            $table->json('metadata')->nullable();

            $table->text('url');
            $table->text('thumbnail_url')->nullable();
            $table->text('preview_url')->nullable();

            $table->string('folder')->nullable();
            $table->string('collection')->nullable();

            $table->enum('status', [
                'pending',
                'uploading',
                'ready',
                'failed',
                'deleted',
            ])->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('upload_attempts')->default(0);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('resource_type');
            $table->index('media_type');
            $table->index('collection');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
