<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            if (! Schema::hasColumn('media', 'alt_text')) {
                $table->string('alt_text')->nullable()->after('original_filename');
            }
            if (! Schema::hasColumn('media', 'caption')) {
                $table->text('caption')->nullable()->after('alt_text');
            }
            if (! Schema::hasColumn('media', 'credit')) {
                $table->string('credit')->nullable()->after('caption');
            }
            if (! Schema::hasColumn('media', 'copyright')) {
                $table->string('copyright')->nullable()->after('credit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            foreach (['alt_text', 'caption', 'credit', 'copyright'] as $column) {
                if (Schema::hasColumn('media', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
