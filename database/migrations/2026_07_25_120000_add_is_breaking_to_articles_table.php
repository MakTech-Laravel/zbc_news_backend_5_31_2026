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
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('is_breaking')->default(false)->after('visibility');
            $table->index('is_breaking');
        });

        $tagIds = DB::table('tags')
            ->whereIn('tag', BreakingTag::VALUES)
            ->pluck('id');

        if ($tagIds->isNotEmpty()) {
            $articleIds = DB::table('article_tags')
                ->whereIn('tag_id', $tagIds)
                ->distinct()
                ->pluck('article_id');

            if ($articleIds->isNotEmpty()) {
                DB::table('articles')
                    ->whereIn('id', $articleIds)
                    ->update(['is_breaking' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['is_breaking']);
            $table->dropColumn('is_breaking');
        });
    }
};
