<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        User::query()
            ->where(function ($query): void {
                $query->whereNull('slug')
                    ->orWhere('slug', '');
            })
            ->orderBy('id')
            ->each(function (User $user): void {
                $user->forceFill([
                    'slug' => User::generateUniqueSlug($user->name ?: 'user', $user->id),
                ])->saveQuietly();
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->change();
        });
    }
};
