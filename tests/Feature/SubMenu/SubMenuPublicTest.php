<?php

namespace Tests\Feature\SubMenu;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Enums\SubMenuKey;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\SubMenuFeaturedArticle;
use App\Models\SubMenuSetting;
use App\Models\User;
use App\Services\SubMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubMenuPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_sub_menu_endpoint_returns_all_sections(): void
    {
        $category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general-sidebars',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);

        $author = User::factory()->create();

        $article = Article::query()->create([
            'title' => 'Pinned Sidebar Story',
            'slug' => 'pinned-sidebar-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        SubMenuFeaturedArticle::query()->create([
            'section_key' => SubMenuKey::EDITORIAL_PICKS,
            'article_id' => $article->id,
            'sort_order' => 10,
            'is_pinned' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/sub-menu');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'trending',
                    'most_read',
                    'live_updates',
                    'editorial_picks',
                ],
            ]);

        $editorial = $this->getJson('/api/v1/sub-menu/editorial_picks');
        $editorial->assertOk()
            ->assertJsonPath('data.settings.is_enabled', true)
            ->assertJsonPath('data.items.0.id', $article->id)
            ->assertJsonPath('data.items.0.title', 'Pinned Sidebar Story')
            ->assertJsonPath('data.items.0.slug', 'pinned-sidebar-story');
    }

    public function test_sub_menu_processor_expires_ended_manual_entries(): void
    {
        $category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general-sidebars-expire',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);
        $author = User::factory()->create();

        $article = Article::query()->create([
            'title' => 'Expiring Pick',
            'slug' => 'expiring-pick',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        $entry = SubMenuFeaturedArticle::query()->create([
            'section_key' => SubMenuKey::EDITORIAL_PICKS,
            'article_id' => $article->id,
            'sort_order' => 10,
            'is_pinned' => false,
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->subMinute(),
        ]);

        $processed = app(SubMenuService::class)->processScheduledWindows();

        $this->assertGreaterThanOrEqual(1, $processed);
        $this->assertFalse($entry->fresh()->is_active);
    }

    public function test_sub_menu_settings_are_seeded_by_migration_defaults(): void
    {
        $keys = SubMenuSetting::query()->pluck('section_key')->map(
            fn ($value) => $value instanceof SubMenuKey ? $value->value : (string) $value,
        )->all();

        $this->assertEqualsCanonicalizing(
            SubMenuKey::values(),
            $keys,
        );
    }

    public function test_live_updates_section_includes_live_articles(): void
    {
        $category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general-live-sidebars',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);
        $author = User::factory()->create();

        $live = Article::query()->create([
            'title' => 'Live Story',
            'slug' => 'live-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
            'is_live' => true,
            'live_started_at' => now(),
        ]);

        Article::query()->create([
            'title' => 'Not Live Story',
            'slug' => 'not-live-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
            'is_live' => false,
        ]);

        $response = $this->getJson('/api/v1/sub-menu/live_updates');

        $response->assertOk()
            ->assertJsonPath('data.items.0.id', $live->id)
            ->assertJsonPath('data.items.0.is_live', true)
            ->assertJsonPath('data.items.0.title', 'Live Story');
    }

    public function test_editorial_picks_respect_schedule_windows(): void
    {
        $category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general-editorial-schedule',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);
        $author = User::factory()->create();

        $activePick = Article::query()->create([
            'title' => 'Active Editorial',
            'slug' => 'active-editorial',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        $futurePick = Article::query()->create([
            'title' => 'Future Editorial',
            'slug' => 'future-editorial',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        SubMenuFeaturedArticle::query()->create([
            'section_key' => SubMenuKey::EDITORIAL_PICKS,
            'article_id' => $activePick->id,
            'sort_order' => 1,
            'is_pinned' => true,
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        SubMenuFeaturedArticle::query()->create([
            'section_key' => SubMenuKey::EDITORIAL_PICKS,
            'article_id' => $futurePick->id,
            'sort_order' => 2,
            'is_pinned' => true,
            'is_active' => true,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/v1/sub-menu/editorial_picks');

        $response->assertOk()
            ->assertJsonPath('data.items.0.id', $activePick->id);

        $ids = collect($response->json('data.items'))->pluck('id')->all();
        $this->assertContains($activePick->id, $ids);
        $this->assertNotContains($futurePick->id, $ids);
    }

    public function test_editorial_picks_manual_before_algorithmic(): void
    {
        $category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general-editorial-manual-first',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);
        $author = User::factory()->create();

        $auto = Article::query()->create([
            'title' => 'Auto Latest',
            'slug' => 'auto-latest-editorial',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
        ]);

        $manual = Article::query()->create([
            'title' => 'Manual Older',
            'slug' => 'manual-older-editorial',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now()->subDay(),
        ]);

        SubMenuFeaturedArticle::query()->create([
            'section_key' => SubMenuKey::EDITORIAL_PICKS,
            'article_id' => $manual->id,
            'sort_order' => 10,
            'is_pinned' => false,
            'is_active' => true,
        ]);

        app(SubMenuService::class)->updateSettings(SubMenuKey::EDITORIAL_PICKS, [
            'limit' => 5,
            'is_enabled' => true,
        ]);

        $response = $this->getJson('/api/v1/sub-menu/editorial_picks')->assertOk();
        $ids = collect($response->json('data.items'))->pluck('id')->all();

        $this->assertSame($manual->id, $ids[0] ?? null);
        $this->assertContains($auto->id, $ids);
        $this->assertLessThan(
            array_search($auto->id, $ids, true),
            array_search($manual->id, $ids, true),
        );
    }

    public function test_disabled_sub_menu_returns_empty_items_on_public_endpoint(): void
    {
        $category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general-disabled-sidebars',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);
        $author = User::factory()->create();

        $article = Article::query()->create([
            'title' => 'Should Hide',
            'slug' => 'should-hide',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
            'is_live' => true,
            'live_started_at' => now(),
        ]);

        app(SubMenuService::class)->updateSettings(SubMenuKey::LIVE_UPDATES, [
            'is_enabled' => false,
        ]);

        $this->getJson('/api/v1/sub-menu/live_updates')
            ->assertOk()
            ->assertJsonPath('data.settings.is_enabled', false)
            ->assertJsonPath('data.items', []);

        $this->assertTrue((bool) $article->fresh()->is_live);
    }
}