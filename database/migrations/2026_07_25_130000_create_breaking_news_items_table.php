<?php

use App\Support\BreakingTag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breaking_news_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('headline_override')->nullable();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('article_id');
        });

        if (Schema::hasColumn('articles', 'is_breaking')) {
            $rows = DB::table('articles')
                ->where('is_breaking', true)
                ->whereNull('deleted_at')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get(['id', 'published_at']);

            $priority = 10;
            foreach ($rows as $row) {
                DB::table('breaking_news_items')->insert([
                    'article_id' => $row->id,
                    'headline_override' => null,
                    'priority' => $priority,
                    'status' => 'active',
                    'starts_at' => null,
                    'expires_at' => null,
                    'notified_at' => null,
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $priority += 10;
            }

            // Also migrate legacy tag-only breaking articles not yet flagged.
            $tagIds = DB::table('tags')
                ->whereIn('tag', BreakingTag::VALUES)
                ->pluck('id');

            if ($tagIds->isNotEmpty()) {
                $taggedArticleIds = DB::table('article_tags')
                    ->whereIn('tag_id', $tagIds)
                    ->pluck('article_id')
                    ->unique();

                $existing = DB::table('breaking_news_items')->pluck('article_id');

                foreach ($taggedArticleIds->diff($existing) as $articleId) {
                    $exists = DB::table('articles')
                        ->where('id', $articleId)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $exists) {
                        continue;
                    }

                    DB::table('breaking_news_items')->insert([
                        'article_id' => $articleId,
                        'headline_override' => null,
                        'priority' => $priority,
                        'status' => 'active',
                        'starts_at' => null,
                        'expires_at' => null,
                        'notified_at' => null,
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $priority += 10;

                    DB::table('articles')
                        ->where('id', $articleId)
                        ->update(['is_breaking' => true]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('breaking_news_items');
    }
};
