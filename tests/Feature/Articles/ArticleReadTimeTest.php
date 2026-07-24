<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleHistroy;
use App\Models\User;
use App\Support\ReadTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleReadTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_time_uses_total_time_spent_from_history(): void
    {
        $category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);

        $author = User::factory()->create();

        $article = Article::query()->create([
            'title' => 'Tracked Article',
            'slug' => 'tracked-article',
            'article_description' => '<p>Short body.</p>',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        ArticleHistroy::query()->create([
            'article_id' => $article->id,
            'user_id' => $author->id,
            'session_id' => 'session-1',
            'ip_address' => '127.0.0.1',
            'time_spent' => 60,
            'scroll_depth' => 80,
            'is_guest' => false,
            'read_at' => now(),
            'read_end_at' => now()->addMinute(),
        ]);

        ArticleHistroy::query()->create([
            'article_id' => $article->id,
            'user_id' => $author->id,
            'session_id' => 'session-2',
            'ip_address' => '127.0.0.1',
            'time_spent' => 120,
            'scroll_depth' => 90,
            'is_guest' => false,
            'read_at' => now(),
            'read_end_at' => now()->addMinutes(2),
        ]);

        $article->loadSum('histroy', 'time_spent');

        $this->assertSame('3 min read', $article->formattedReadTime());
        $this->assertSame('3 min read', (new ArticleResource($article))->toArray(request())['read_time']);
    }

    public function test_read_time_falls_back_to_content_estimate_without_history(): void
    {
        $longContent = str_repeat('word ', 400);

        $this->assertSame('2 min read', ReadTime::fromHtml($longContent));
    }

    public function test_estimated_read_time_uses_250_words_per_minute(): void
    {
        $this->assertSame('1 min read', ReadTime::estimatedFromHtml(str_repeat('word ', 250)));
        $this->assertSame('1 min read', ReadTime::estimatedFromHtml(str_repeat('word ', 100)));
        $this->assertSame('2 min read', ReadTime::estimatedFromHtml(str_repeat('word ', 251)));
        $this->assertSame('2 min read', ReadTime::estimatedFromHtml(str_repeat('word ', 500)));
        $this->assertSame('3 min read', ReadTime::estimatedFromHtml(str_repeat('word ', 501)));
    }

    public function test_estimated_read_time_ignores_engagement_history(): void
    {
        $category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general-estimated',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);

        $author = User::factory()->create();

        $article = Article::query()->create([
            'title' => 'Estimated Article',
            'slug' => 'estimated-article',
            'article_description' => '<p>'.str_repeat('word ', 100).'</p>',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        ArticleHistroy::query()->create([
            'article_id' => $article->id,
            'user_id' => $author->id,
            'session_id' => 'session-est',
            'ip_address' => '127.0.0.1',
            'time_spent' => 600,
            'scroll_depth' => 80,
            'is_guest' => false,
            'read_at' => now(),
            'read_end_at' => now()->addMinutes(10),
        ]);

        $article->loadSum('histroy', 'time_spent');

        $this->assertSame('10 min read', $article->formattedReadTime());
        $this->assertSame('1 min read', $article->estimatedReadTime());
        $this->assertSame(
            '1 min read',
            (new ArticleResource($article))->toArray(request())['estimated_read_time'],
        );
    }
}
