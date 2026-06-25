<?php

namespace Tests\Feature\User;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UserDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_dashboard_endpoint_returns_dashboard_payload(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $category = ArticleCategory::query()->create([
            'title' => 'Science',
            'slug' => 'science',
            'status' => 'active',
        ]);

        Article::query()->create([
            'title' => 'Dashboard Article',
            'slug' => 'dashboard-article',
            'article_description' => '<p>Body content for read time.</p>',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $user->id,
            'published_at' => now(),
            'views' => 10,
        ]);

        $response = $this->getJson('/api/v1/admin/user/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'featured_story',
                    'feeds' => ['recommended', 'latest', 'trending'],
                    'continue_reading',
                    'trending_topics',
                    'this_week' => ['articlesRead', 'readingTime'],
                ],
            ]);
    }
}
