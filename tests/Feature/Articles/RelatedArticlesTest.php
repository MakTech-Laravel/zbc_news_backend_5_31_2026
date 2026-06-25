<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedArticlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_related_articles_returns_same_category_articles(): void
    {
        $category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);

        $otherCategory = ArticleCategory::query()->create([
            'title' => 'Sports',
            'slug' => 'sports',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);

        $author = User::factory()->create();

        $article = Article::query()->create([
            'title' => 'Main Article',
            'slug' => 'main-article',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        Article::query()->create([
            'title' => 'Related Article',
            'slug' => 'related-article',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now()->subHour(),
        ]);

        Article::query()->create([
            'title' => 'Unrelated Article',
            'slug' => 'unrelated-article',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $otherCategory->id,
            'user_id' => $author->id,
            'published_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/v1/articles/related/main-article');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'related-article');
    }
}
