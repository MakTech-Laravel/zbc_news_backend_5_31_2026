<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleHistroy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MostReadArticlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_most_read_ranks_by_unique_visitors_for_today(): void
    {
        [$category, $author] = $this->seedCategoryAndAuthor();

        $top = $this->createPublishedArticle($category, $author, 'Top Unique', 'top-unique');
        $second = $this->createPublishedArticle($category, $author, 'Second Unique', 'second-unique');
        $oldOnly = $this->createPublishedArticle($category, $author, 'Old Reads', 'old-reads');

        $visitors = User::factory()->count(4)->create();

        // Top: 3 unique visitors today
        $this->track($top, user: $visitors[0], ip: '1.1.1.1', readAt: now());
        $this->track($top, user: $visitors[1], ip: '1.1.1.2', readAt: now());
        $this->track($top, user: null, ip: '1.1.1.3', readAt: now());
        // Duplicate visitor should not increase unique count
        $this->track($top, user: $visitors[0], ip: '1.1.1.1', readAt: now());

        // Second: 1 unique today
        $this->track($second, user: $visitors[2], ip: '2.2.2.1', readAt: now());

        // Old article: only yesterday reads (excluded from today)
        $this->track($oldOnly, user: $visitors[3], ip: '3.3.3.1', readAt: now()->subDay());
        $this->track($oldOnly, user: null, ip: '3.3.3.2', readAt: now()->subDay());

        $response = $this->getJson('/api/v1/articles/most-read?period=today&unique=1&per_page=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.articles.0.slug', 'top-unique')
            ->assertJsonPath('data.articles.0.views', 3)
            ->assertJsonPath('data.articles.1.slug', 'second-unique')
            ->assertJsonPath('data.articles.1.views', 1);
    }

    public function test_most_read_paginates_with_load_more_meta(): void
    {
        [$category, $author] = $this->seedCategoryAndAuthor();
        $visitors = User::factory()->count(21)->create();
        $visitorIndex = 0;

        foreach (range(1, 6) as $i) {
            $article = $this->createPublishedArticle(
                $category,
                $author,
                "Article {$i}",
                "article-{$i}",
            );

            foreach (range(1, 7 - $i) as $n) {
                $this->track(
                    $article,
                    user: $visitors[$visitorIndex],
                    ip: "10.0.{$i}.{$n}",
                    readAt: now(),
                );
                $visitorIndex++;
            }
        }

        $page1 = $this->getJson('/api/v1/articles/most-read?period=today&per_page=5&page=1');
        $page1->assertOk()
            ->assertJsonPath('data.meta.per_page', 5)
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonPath('data.meta.total', 6)
            ->assertJsonCount(5, 'data.articles');

        $page2 = $this->getJson('/api/v1/articles/most-read?period=today&per_page=5&page=2');
        $page2->assertOk()
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonCount(1, 'data.articles');
    }

    public function test_most_read_all_period_includes_older_reads(): void
    {
        [$category, $author] = $this->seedCategoryAndAuthor();
        $visitors = User::factory()->count(3)->create();

        $recent = $this->createPublishedArticle($category, $author, 'Recent', 'recent-article');
        $older = $this->createPublishedArticle($category, $author, 'Older', 'older-article');

        $this->track($recent, user: $visitors[0], ip: '1.1.1.1', readAt: now());
        $this->track($older, user: $visitors[1], ip: '2.2.2.2', readAt: now()->subDays(10));
        $this->track($older, user: $visitors[2], ip: '3.3.3.3', readAt: now()->subDays(10));

        $today = $this->getJson('/api/v1/articles/most-read?period=today');
        $today->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.articles.0.slug', 'recent-article');

        $all = $this->getJson('/api/v1/articles/most-read?period=all');
        $all->assertOk()
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.articles.0.slug', 'older-article')
            ->assertJsonPath('data.articles.0.views', 2);
    }

    /**
     * @return array{0: ArticleCategory, 1: User}
     */
    private function seedCategoryAndAuthor(): array
    {
        $category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general-most-read',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);

        $author = User::factory()->create();

        return [$category, $author];
    }

    private function createPublishedArticle(
        ArticleCategory $category,
        User $author,
        string $title,
        string $slug,
    ): Article {
        return Article::query()->create([
            'title' => $title,
            'slug' => $slug,
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
            'views' => 0,
        ]);
    }

    private function track(
        Article $article,
        ?User $user,
        string $ip,
        mixed $readAt,
    ): void {
        ArticleHistroy::query()->create([
            'article_id' => $article->id,
            'user_id' => $user?->id,
            'session_id' => 'session-'.uniqid('', true),
            'ip_address' => $ip,
            'time_spent' => 30,
            'scroll_depth' => 50,
            'is_guest' => $user === null,
            'read_at' => $readAt,
            'read_end_at' => $readAt,
        ]);
    }
}
