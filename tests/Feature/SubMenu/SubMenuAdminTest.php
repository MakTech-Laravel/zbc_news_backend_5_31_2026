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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubMenuAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ArticleCategory $category;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['articles.list', 'articles.update'] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Articles'],
            );
        }

        $role = Role::query()->firstOrCreate(['name' => 'editor', 'guard_name' => 'api']);
        $role->givePermissionTo(['articles.list', 'articles.update']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('editor');
        $this->admin->givePermissionTo(['articles.list', 'articles.update']);
        Passport::actingAs($this->admin);

        $this->author = User::factory()->create();
        $this->category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general-sidebar-admin',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);
    }

    private function makeArticle(string $title, string $slug): Article
    {
        return Article::query()->create([
            'title' => $title,
            'slug' => $slug,
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $this->category->id,
            'user_id' => $this->author->id,
            'published_at' => now(),
        ]);
    }

    public function test_admin_can_load_sub_menu_snapshots(): void
    {
        $this->getJson('/api/v1/admin/sub-menu')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'trending' => ['settings', 'manual', 'algorithmic', 'items'],
                    'most_read' => ['settings', 'manual', 'algorithmic', 'items'],
                    'live_updates' => ['settings', 'manual', 'algorithmic', 'items'],
                    'editorial_picks' => ['settings', 'manual', 'algorithmic', 'items'],
                ],
            ]);
    }

    public function test_admin_can_update_section_settings(): void
    {
        $this->postJson('/api/v1/admin/sub-menu/settings/most_read', [
            'limit' => 7,
            'most_read_default_period' => 'week',
            'pinned_slots' => 2,
            'is_enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.limit', 7)
            ->assertJsonPath('data.most_read_default_period', 'week')
            ->assertJsonPath('data.pinned_slots', 2);

        $settings = SubMenuSetting::query()
            ->where('section_key', SubMenuKey::MOST_READ->value)
            ->first();

        $this->assertNotNull($settings);
        $this->assertSame(7, (int) $settings->limit);
        $this->assertSame('week', (string) $settings->most_read_default_period);
    }

    public function test_admin_can_pin_reorder_and_remove_manual_entries(): void
    {
        $first = $this->makeArticle('First Pick', 'first-pick');
        $second = $this->makeArticle('Second Pick', 'second-pick');

        $this->postJson('/api/v1/admin/sub-menu/manual/trending', [
            'article_id' => $first->id,
            'is_pinned' => true,
            'is_active' => true,
            'sort_order' => 0,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/sub-menu/manual/trending', [
            'article_id' => $second->id,
            'is_pinned' => true,
            'is_active' => true,
            'sort_order' => 1,
        ])->assertCreated();

        $ids = SubMenuFeaturedArticle::query()
            ->where('section_key', SubMenuKey::TRENDING->value)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        $this->assertCount(2, $ids);

        $this->postJson('/api/v1/admin/sub-menu/manual/trending/reorder', [
            'ids' => array_reverse($ids),
        ])->assertOk();

        $reordered = SubMenuFeaturedArticle::query()
            ->where('section_key', SubMenuKey::TRENDING->value)
            ->orderBy('sort_order')
            ->pluck('article_id')
            ->all();

        $this->assertSame([(int) $second->id, (int) $first->id], array_map('intval', $reordered));

        $this->deleteJson('/api/v1/admin/sub-menu/manual/'.$ids[0])
            ->assertOk();

        $this->assertSame(
            1,
            SubMenuFeaturedArticle::query()
                ->where('section_key', SubMenuKey::TRENDING->value)
                ->count(),
        );
    }

    public function test_admin_can_start_and_end_live_coverage(): void
    {
        $article = $this->makeArticle('Breaking Live', 'breaking-live');

        $this->postJson('/api/v1/admin/sub-menu/live/start/'.$article->id)
            ->assertOk()
            ->assertJsonPath('data.is_live', true);

        $this->assertTrue((bool) $article->fresh()->is_live);

        $livePublic = $this->getJson('/api/v1/sub-menu/live_updates')->assertOk();
        $this->assertSame($article->id, $livePublic->json('data.items.0.id'));

        $this->postJson('/api/v1/admin/sub-menu/live/end/'.$article->id)
            ->assertOk()
            ->assertJsonPath('data.is_live', false);

        $this->assertFalse((bool) $article->fresh()->is_live);
    }

    public function test_settings_update_busts_public_cache(): void
    {
        Cache::flush();

        $this->getJson('/api/v1/sub-menu/trending')->assertOk();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->getJson('/api/v1/sub-menu/trending')->assertOk();
        $this->assertSame(0, $queries, 'Warm public sidebar cache must not re-query the database.');

        $this->postJson('/api/v1/admin/sub-menu/settings/trending', [
            'limit' => 8,
            'is_enabled' => true,
        ])->assertOk();

        $this->assertNull(Cache::get(SubMenuSetting::cacheKey('trending', 'public')));

        $this->getJson('/api/v1/sub-menu/trending')
            ->assertOk()
            ->assertJsonPath('data.settings.limit', 8);
    }

    public function test_disabled_section_returns_empty_public_items(): void
    {
        $article = $this->makeArticle('Hidden Pick', 'hidden-pick');

        SubMenuFeaturedArticle::query()->create([
            'section_key' => SubMenuKey::EDITORIAL_PICKS,
            'article_id' => $article->id,
            'sort_order' => 1,
            'is_pinned' => true,
            'is_active' => true,
        ]);

        app(SubMenuService::class)->updateSettings(SubMenuKey::EDITORIAL_PICKS, [
            'is_enabled' => false,
        ]);

        $this->getJson('/api/v1/sub-menu/editorial_picks')
            ->assertOk()
            ->assertJsonPath('data.settings.is_enabled', false)
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.manual', [])
            ->assertJsonPath('data.algorithmic', []);
    }

    public function test_rejects_invalid_section_key(): void
    {
        $this->postJson('/api/v1/admin/sub-menu/settings/not_a_section', [
            'limit' => 5,
        ])->assertStatus(422);
    }
}
