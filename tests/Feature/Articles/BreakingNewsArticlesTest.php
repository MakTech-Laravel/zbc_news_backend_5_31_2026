<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Tag;
use App\Models\User;
use App\Support\BreakingTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BreakingNewsArticlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_breaking_news_endpoint_is_public_and_returns_latest_matching_articles(): void
    {
        $author = User::factory()->create();

        $breakingTag = Tag::query()->create(['tag' => 'Breaking-News']);
        $otherTag = Tag::query()->create(['tag' => 'sports']);

        $newestBreaking = Article::query()->create([
            'title' => 'Newest Breaking',
            'slug' => 'newest-breaking',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);
        $newestBreaking->tags()->attach($breakingTag->id);

        $olderBreaking = Article::query()->create([
            'title' => 'Older Breaking',
            'slug' => 'older-breaking',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $author->id,
            'published_at' => now()->subHour(),
        ]);
        $olderBreaking->tags()->attach($breakingTag->id);

        $nonBreaking = Article::query()->create([
            'title' => 'Sports Story',
            'slug' => 'sports-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $author->id,
            'published_at' => now()->subMinutes(30),
        ]);
        $nonBreaking->tags()->attach($otherTag->id);

        $response = $this->getJson('/api/v1/articles/breaking?limit=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'newest-breaking')
            ->assertJsonPath('data.1.slug', 'older-breaking');
    }

    public function test_breaking_news_limit_is_capped_at_ten(): void
    {
        $author = User::factory()->create();
        $breakingTag = Tag::query()->create(['tag' => 'breaking_news']);

        for ($i = 1; $i <= 12; $i++) {
            $article = Article::query()->create([
                'title' => "Breaking {$i}",
                'slug' => "breaking-{$i}",
                'article_description' => 'Body',
                'status' => ArticleStatus::PUBLISHED,
                'user_id' => $author->id,
                'published_at' => now()->subMinutes($i),
            ]);
            $article->tags()->attach($breakingTag->id);
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
