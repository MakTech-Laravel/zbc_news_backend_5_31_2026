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
        ]);

        ArticleCategory::query()->create([
            'title' => 'Archived',
            'slug' => 'archived',
            'status' => ArticleCategoryStatus::INACTIVE,
        ]);

        $response = $this->getJson('/api/v1/newsletter/categories');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Technology')
            ->assertJsonPath('data.0.slug', 'technology');
    }
}
