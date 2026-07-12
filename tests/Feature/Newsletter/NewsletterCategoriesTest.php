<?php

namespace Tests\Feature\Newsletter;

use App\Enums\ArticleCategoryStatus;
use App\Models\ArticleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_newsletter_categories_returns_active_categories_with_name_field(): void
    {
        ArticleCategory::query()->create([
            'title' => 'Technology',
            'slug' => 'technology',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 1,
        ]);

        ArticleCategory::query()->create([
            'title' => 'Archived',
            'slug' => 'archived',
            'status' => ArticleCategoryStatus::INACTIVE,
            'sort_order' => 2,
        ]);

        $response = $this->getJson('/api/v1/newsletter/categories');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Technology')
            ->assertJsonPath('data.0.slug', 'technology');
    }

    public function test_newsletter_categories_are_ordered_by_sort_order(): void
    {
        ArticleCategory::query()->create([
            'title' => 'Zebra',
            'slug' => 'zebra',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 3,
        ]);
        ArticleCategory::query()->create([
            'title' => 'Alpha',
            'slug' => 'alpha',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 1,
        ]);
        ArticleCategory::query()->create([
            'title' => 'Middle',
            'slug' => 'middle',
            'status' => ArticleCategoryStatus::ACTIVE,
            'sort_order' => 2,
        ]);

        $response = $this->getJson('/api/v1/newsletter/categories');

        $response->assertOk()
            ->assertJsonPath('data.0.slug', 'alpha')
            ->assertJsonPath('data.1.slug', 'middle')
            ->assertJsonPath('data.2.slug', 'zebra');
    }
}
