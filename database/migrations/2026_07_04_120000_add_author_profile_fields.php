<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        Schema::table('user_information', function (Blueprint $table) {
            $table->string('public_title')->nullable()->after('bio');
            $table->json('social_links')->nullable()->after('public_title');
        });

        User::query()->orderBy('id')->each(function (User $user) {
            if ($user->slug) {
                return;
            }

            $base = Str::slug($user->name) ?: 'author';
            $slug = $base;
            $count = 2;

            while (User::query()->where('slug', $slug)->where('id', '!=', $user->id)->exists()) {
                $slug = "{$base}-{$count}";
                $count++;
            }

            $user->forceFill(['slug' => $slug])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('user_information', function (Blueprint $table) {
            $table->dropColumn(['public_title', 'social_links']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
