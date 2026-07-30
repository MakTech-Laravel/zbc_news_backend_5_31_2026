<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Enums\LiveUpdateStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleLiveUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LiveUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ArticleCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'articles.list',
            'articles.create',
            'articles.show',
            'articles.update',
            'articles.delete',
        ] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Articles'],
            );
        }

        $role = Role::query()->firstOrCreate(['name' => 'editor', 'guard_name' => 'api']);
        $role->givePermissionTo([
            'articles.list',
            'articles.create',
            'articles.show',
            'articles.update',
            'articles.delete',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('editor');
        $this->admin->givePermissionTo([
            'articles.list',
            'articles.create',
            'articles.show',
            'articles.update',
            'articles.delete',
        ]);
        Passport::actingAs($this->admin);

        $this->category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general-live-updates',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);
    }

    private function makeLiveBlog(string $slug = 'election-live', array $overrides = []): Article
    {
        return Article::query()->create(array_merge([
            'title' => 'Election Live Blog',
            'slug' => $slug,
            'article_description' => '<p>Intro</p>',
            'status' => ArticleStatus::PUBLISHED,
            'is_live_blog' => true,
            'article_category_id' => $this->category->id,
            'user_id' => $this->admin->id,
            'published_at' => now(),
        ], $overrides));
    }

    public function test_admin_can_create_live_update_shell(): void
    {
        $response = $this->postJson('/api/v1/admin/live-updates/store', [
            'title' => 'Budget Day Live',
            'slug' => 'budget-day-live',
            'article_description' => '<p>Coverage starts at noon</p>',
            'article_category_id' => $this->category->id,
            'status' => ArticleStatus::DRAFT->value,
            'visibility' => 'public',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_live_blog', true)
            ->assertJsonPath('data.slug', 'budget-day-live');

        $this->assertDatabaseHas('articles', [
            'slug' => 'budget-day-live',
            'is_live_blog' => true,
        ]);
    }

    public function test_admin_articles_list_excludes_live_blogs(): void
    {
        Article::query()->create([
            'title' => 'Regular Story',
            'slug' => 'regular-story',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'is_live_blog' => false,
            'article_category_id' => $this->category->id,
            'user_id' => $this->admin->id,
            'published_at' => now(),
        ]);

        $this->makeLiveBlog();

        $articles = $this->getJson('/api/v1/admin/articles')
            ->assertOk()
            ->json('data');

        $slugs = collect($articles)->pluck('slug')->all();
        $this->assertContains('regular-story', $slugs);
        $this->assertNotContains('election-live', $slugs);

        $live = $this->getJson('/api/v1/admin/live-updates')
            ->assertOk()
            ->json('data');

        $liveSlugs = collect($live)->pluck('slug')->all();
        $this->assertContains('election-live', $liveSlugs);
        $this->assertNotContains('regular-story', $liveSlugs);
    }

    public function test_entries_are_ordered_newest_first_and_exposed_publicly(): void
    {
        $article = $this->makeLiveBlog();

        $older = ArticleLiveUpdate::query()->create([
            'article_id' => $article->id,
            'body' => '<p>Older update</p>',
            'posted_at' => now()->subHours(2),
            'status' => LiveUpdateStatus::PUBLISHED,
            'user_id' => $this->admin->id,
        ]);

        $newer = ArticleLiveUpdate::query()->create([
            'article_id' => $article->id,
            'body' => '<p>Newer update</p>',
            'posted_at' => now()->subMinutes(5),
            'status' => LiveUpdateStatus::PUBLISHED,
            'user_id' => $this->admin->id,
        ]);

        ArticleLiveUpdate::query()->create([
            'article_id' => $article->id,
            'body' => '<p>Draft hidden</p>',
            'posted_at' => now(),
            'status' => LiveUpdateStatus::DRAFT,
            'user_id' => $this->admin->id,
        ]);

        $public = $this->getJson('/api/v1/articles/show/election-live')
            ->assertOk()
            ->assertJsonPath('data.is_live_blog', true)
            ->assertJsonCount(2, 'data.live_updates');

        $this->assertSame($newer->id, $public->json('data.live_updates.0.id'));
        $this->assertSame($older->id, $public->json('data.live_updates.1.id'));
        $this->assertSame('<p>Newer update</p>', $public->json('data.live_updates.0.body'));
    }

    public function test_admin_can_crud_live_update_entries(): void
    {
        $this->makeLiveBlog();

        $create = $this->postJson('/api/v1/admin/live-updates/election-live/entries', [
            'body' => '<p>First post</p>',
            'posted_at' => now()->toIso8601String(),
            'status' => LiveUpdateStatus::PUBLISHED->value,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.body', '<p>First post</p>');

        $entryId = (int) $create->json('data.id');

        $this->postJson("/api/v1/admin/live-updates/election-live/entries/{$entryId}", [
            'body' => '<p>Updated post</p>',
            'status' => LiveUpdateStatus::PUBLISHED->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.body', '<p>Updated post</p>');

        $this->deleteJson("/api/v1/admin/live-updates/election-live/entries/{$entryId}")
            ->assertOk();

        $this->assertSoftDeleted('article_live_updates', ['id' => $entryId]);
    }

    public function test_start_and_end_live_coverage(): void
    {
        $this->makeLiveBlog();

        $this->postJson('/api/v1/admin/live-updates/election-live/live/start')
            ->assertOk()
            ->assertJsonPath('data.is_live', true);

        $this->assertDatabaseHas('articles', [
            'slug' => 'election-live',
            'is_live' => true,
        ]);

        $this->postJson('/api/v1/admin/live-updates/election-live/live/end')
            ->assertOk()
            ->assertJsonPath('data.is_live', false);

        $this->assertDatabaseHas('articles', [
            'slug' => 'election-live',
            'is_live' => false,
        ]);
    }

    public function test_cannot_add_entries_to_non_live_blog_article(): void
    {
        Article::query()->create([
            'title' => 'Normal',
            'slug' => 'normal-article',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'is_live_blog' => false,
            'article_category_id' => $this->category->id,
            'user_id' => $this->admin->id,
            'published_at' => now(),
        ]);

        $this->postJson('/api/v1/admin/live-updates/normal-article/entries', [
            'body' => '<p>Nope</p>',
        ])->assertNotFound();
    }
}
