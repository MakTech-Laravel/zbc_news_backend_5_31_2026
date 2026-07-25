<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleStatus;
use App\Enums\BreakingNewsStatus;
use App\Models\Article;
use App\Models\BreakingNewsItem;
use App\Models\User;
use App\Support\BreakingTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BreakingNewsArticlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_breaking_news_endpoint_returns_live_ordered_items(): void
    {
        $author = User::factory()->create();

        $newer = Article::query()->create([
            'title' => 'Second Priority',
            'slug' => 'second-priority',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'is_breaking' => true,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        $older = Article::query()->create([
            'title' => 'First Priority',
            'slug' => 'first-priority',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'is_breaking' => true,
            'user_id' => $author->id,
            'published_at' => now()->subHour(),
        ]);

        BreakingNewsItem::query()->create([
            'article_id' => $newer->id,
            'priority' => 20,
            'status' => BreakingNewsStatus::ACTIVE,
        ]);

        BreakingNewsItem::query()->create([
            'article_id' => $older->id,
            'priority' => 10,
            'status' => BreakingNewsStatus::ACTIVE,
        ]);

        Article::query()->create([
            'title' => 'Not Breaking',
            'slug' => 'not-breaking',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/articles/breaking?limit=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'first-priority')
            ->assertJsonPath('data.1.slug', 'second-priority')
            ->assertJsonPath('data.0.title', 'First Priority');
    }

    public function test_paused_and_expired_items_are_hidden_from_ticker(): void
    {
        $author = User::factory()->create();

        $pausedArticle = Article::query()->create([
            'title' => 'Paused Story',
            'slug' => 'paused-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        $expiredArticle = Article::query()->create([
            'title' => 'Expired Story',
            'slug' => 'expired-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        $scheduledArticle = Article::query()->create([
            'title' => 'Future Story',
            'slug' => 'future-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        BreakingNewsItem::query()->create([
            'article_id' => $pausedArticle->id,
            'priority' => 10,
            'status' => BreakingNewsStatus::PAUSED,
        ]);

        BreakingNewsItem::query()->create([
            'article_id' => $expiredArticle->id,
            'priority' => 20,
            'status' => BreakingNewsStatus::ACTIVE,
            'expires_at' => now()->subMinute(),
        ]);

        BreakingNewsItem::query()->create([
            'article_id' => $scheduledArticle->id,
            'priority' => 30,
            'status' => BreakingNewsStatus::ACTIVE,
            'starts_at' => now()->addHour(),
        ]);

        $response = $this->getJson('/api/v1/articles/breaking?limit=5');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_breaking_news_limit_is_capped_at_ten(): void
    {
        $author = User::factory()->create();

        for ($i = 1; $i <= 12; $i++) {
            $article = Article::query()->create([
                'title' => "Breaking {$i}",
                'slug' => "breaking-{$i}",
                'article_description' => 'Body',
                'status' => ArticleStatus::PUBLISHED,
                'user_id' => $author->id,
                'published_at' => now()->subMinutes($i),
            ]);

            BreakingNewsItem::query()->create([
                'article_id' => $article->id,
                'priority' => $i * 10,
                'status' => BreakingNewsStatus::ACTIVE,
            ]);
        }

        $response = $this->getJson('/api/v1/articles/breaking?limit=25');

        $response->assertOk()
            ->assertJsonCount(10, 'data');
    }

    #[DataProvider('breakingTagProvider')]
    public function test_breaking_tag_values_match_configured_list(string $tagValue): void
    {
        $this->assertTrue(BreakingTag::isBreaking($tagValue));
        $this->assertContains($tagValue, BreakingTag::VALUES);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function breakingTagProvider(): array
    {
        return array_combine(
            BreakingTag::VALUES,
            array_map(fn (string $value): array => [$value], BreakingTag::VALUES),
        );
    }
}
