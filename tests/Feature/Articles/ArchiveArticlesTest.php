<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveArticlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_endpoint_returns_published_articles_by_year_and_month(): void
    {
        $author = User::factory()->create();
        $otherAuthor = User::factory()->create();
        $category = ArticleCategory::query()->create([
            'title' => 'Politics',
            'slug' => 'politics',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);
        $otherCategory = ArticleCategory::query()->create([
            'title' => 'Sports',
            'slug' => 'sports',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);

        $match = Article::query()->create([
            'title' => 'March Politics Story',
            'slug' => 'march-politics-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $author->id,
            'article_category_id' => $category->id,
            'published_at' => '2024-03-15 10:00:00',
        ]);

        Article::query()->create([
            'title' => 'April Politics Story',
            'slug' => 'april-politics-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $author->id,
            'article_category_id' => $category->id,
            'published_at' => '2024-04-01 10:00:00',
        ]);

        Article::query()->create([
            'title' => 'March Sports Story',
            'slug' => 'march-sports-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $otherAuthor->id,
            'article_category_id' => $otherCategory->id,
            'published_at' => '2024-03-20 10:00:00',
        ]);

        Article::query()->create([
            'title' => 'Draft Story',
            'slug' => 'draft-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::DRAFT,
            'user_id' => $author->id,
            'article_category_id' => $category->id,
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/v1/articles/archive?year=2024&month=3&category=politics&author='.$author->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.articles')
            ->assertJsonPath('data.articles.0.slug', $match->slug)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.filters.year', 2024)
            ->assertJsonPath('data.filters.month', 3)
            ->assertJsonPath('data.filters.category', 'politics')
            ->assertJsonPath('data.filters.author', $author->id);
    }

    public function test_archive_filters_endpoint_returns_available_options(): void
    {
        $author = User::factory()->create(['name' => 'Jane Reporter']);
        $category = ArticleCategory::query()->create([
            'title' => 'Business',
            'slug' => 'business',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);

        Article::query()->create([
            'title' => 'Business Story',
            'slug' => 'business-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'user_id' => $author->id,
            'article_category_id' => $category->id,
            'published_at' => '2023-06-10 10:00:00',
        ]);

        $response = $this->getJson('/api/v1/articles/archive/filters?year=2023');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.years.0.year', 2023)
            ->assertJsonPath('data.months.0.month', 6)
            ->assertJsonPath('data.categories.0.slug', 'business')
            ->assertJsonPath('data.authors.0.name', 'Jane Reporter');
    }
}
