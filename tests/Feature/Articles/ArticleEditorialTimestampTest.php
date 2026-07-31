<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use App\Enums\LiveUpdateStatus;
use App\Jobs\DispatchArticlePublishedNotifications;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Media;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArticleEditorialTimestampTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ArticleCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->user->assignRole('editor');
        $this->user->givePermissionTo([
            'articles.create',
            'articles.update',
            'live-updates.list',
            'live-updates.create',
            'live-updates.show',
            'live-updates.update',
            'live-updates.delete',
        ]);

        Passport::actingAs($this->user);

        $this->category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general-editorial-ts',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);

        Bus::fake([DispatchArticlePublishedNotifications::class]);
        Event::fake();
    }

    public function test_body_edit_bumps_updated_at_but_keeps_published_at(): void
    {
        $article = $this->makePublishedArticle();
        $publishedAt = $article->published_at->copy();
        $frozenUpdatedAt = Carbon::parse('2026-06-01 12:00:00', 'UTC');
        $article->forceFill(['updated_at' => $frozenUpdatedAt])->save();

        Carbon::setTestNow(Carbon::parse('2026-07-30 15:30:00', 'UTC'));

        $this->postJson('/api/v1/admin/articles/update/'.$article->slug, $this->basePayload($article, [
            'article_description' => '<p>Edited body sentence.</p>',
            'published_at' => $publishedAt->copy()->addHours(5)->toIso8601String(),
        ]))->assertOk();

        $article->refresh();

        $this->assertTrue(
            $article->published_at->equalTo($publishedAt),
            'published_at must stay immutable after first publish',
        );
        $this->assertTrue(
            $article->updated_at->gt($frozenUpdatedAt),
            'updated_at must bump after editorial body save',
        );
    }

    public function test_status_only_save_does_not_bump_updated_at(): void
    {
        $article = $this->makePublishedArticle([
            'article_description' => '<p>Unchanged body</p>',
        ]);
        $frozenUpdatedAt = Carbon::parse('2026-06-01 12:00:00', 'UTC');
        $article->forceFill(['updated_at' => $frozenUpdatedAt])->save();

        Carbon::setTestNow(Carbon::parse('2026-07-30 16:00:00', 'UTC'));

        $this->postJson('/api/v1/admin/articles/update/'.$article->slug, $this->basePayload($article, [
            'status' => ArticleStatus::PUBLISHED->value,
            'visibility' => ArticleVisibility::PUBLIC->value,
        ]))->assertOk();

        $article->refresh();

        $this->assertSame(
            $frozenUpdatedAt->utc()->format('Y-m-d H:i:s'),
            $article->updated_at->utc()->format('Y-m-d H:i:s'),
            'Non-editorial save must not bump updated_at',
        );
    }

    public function test_live_video_url_change_bumps_updated_at(): void
    {
        $article = $this->makePublishedArticle([
            'is_live_blog' => true,
            'live_video_url' => null,
        ]);
        $frozenUpdatedAt = Carbon::parse('2026-06-01 12:00:00', 'UTC');
        $article->forceFill(['updated_at' => $frozenUpdatedAt])->save();

        Carbon::setTestNow(Carbon::parse('2026-07-30 17:00:00', 'UTC'));

        $this->postJson('/api/v1/admin/articles/update/'.$article->slug, $this->basePayload($article, [
            'is_live_blog' => true,
            'live_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]))->assertOk();

        $article->refresh();

        $this->assertTrue($article->updated_at->gt($frozenUpdatedAt));
        $this->assertSame(
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            $article->live_video_url,
        );
    }

    public function test_featured_media_uuid_change_bumps_updated_at(): void
    {
        $article = $this->makePublishedArticle();
        $frozenUpdatedAt = Carbon::parse('2026-06-01 12:00:00', 'UTC');
        $article->forceFill(['updated_at' => $frozenUpdatedAt])->save();

        $media = Media::factory()->create([
            'uploaded_by' => $this->user->id,
            'status' => 'ready',
            'media_type' => 'image',
            'url' => 'https://res.cloudinary.com/test/image/upload/v1/featured.jpg',
            'thumbnail_url' => 'https://res.cloudinary.com/test/image/upload/w_200/featured.jpg',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-07-30 18:00:00', 'UTC'));

        $this->postJson('/api/v1/admin/articles/update/'.$article->slug, $this->basePayload($article, [
            'featured_media_uuid' => $media->uuid,
        ]))->assertOk();

        $article->refresh();

        $this->assertTrue($article->updated_at->gt($frozenUpdatedAt));
        $this->assertSame($media->uuid, $article->featuredMedia()?->uuid);
    }

    public function test_auto_save_body_change_does_not_bump_updated_at(): void
    {
        $article = $this->makePublishedArticle([
            'article_description' => '<p>Original</p>',
        ]);
        $frozenUpdatedAt = Carbon::parse('2026-06-01 12:00:00', 'UTC');
        $article->forceFill(['updated_at' => $frozenUpdatedAt])->save();

        Carbon::setTestNow(Carbon::parse('2026-07-30 19:00:00', 'UTC'));

        $this->postJson('/api/v1/admin/articles/auto-save/'.$article->slug, [
            'title' => $article->title,
            'article_description' => '<p>Auto-saved body</p>',
            'article_category_id' => $this->category->id,
        ])->assertOk();

        $article->refresh();

        $this->assertSame(
            $frozenUpdatedAt->utc()->format('Y-m-d H:i:s'),
            $article->updated_at->utc()->format('Y-m-d H:i:s'),
            'Auto-save must not change updated_at',
        );
        $this->assertSame('<p>Auto-saved body</p>', $article->article_description);
    }

    public function test_view_tracking_does_not_bump_updated_at(): void
    {
        $article = $this->makePublishedArticle(['views' => 0]);
        $frozenUpdatedAt = Carbon::parse('2026-06-01 12:00:00', 'UTC');
        $article->forceFill(['updated_at' => $frozenUpdatedAt])->save();

        Carbon::setTestNow(Carbon::parse('2026-07-30 20:00:00', 'UTC'));

        $this->postJson('/api/v1/articles/track-read', [
            'article_id' => $article->id,
            'session_id' => 'editorial-ts-session-1',
            'time_spent' => 30,
            'scroll_depth' => 80,
        ])->assertOk();

        $article->refresh();

        $this->assertSame(
            $frozenUpdatedAt->utc()->format('Y-m-d H:i:s'),
            $article->updated_at->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertGreaterThan(0, (int) $article->views);
    }

    public function test_start_and_end_live_coverage_does_not_bump_updated_at(): void
    {
        $article = $this->makePublishedArticle([
            'is_live_blog' => true,
            'is_live' => false,
        ]);
        $frozenUpdatedAt = Carbon::parse('2026-06-01 12:00:00', 'UTC');
        $article->forceFill(['updated_at' => $frozenUpdatedAt])->save();

        Carbon::setTestNow(Carbon::parse('2026-07-30 21:00:00', 'UTC'));

        $this->postJson('/api/v1/admin/live-updates/'.$article->slug.'/live/start')
            ->assertOk()
            ->assertJsonPath('data.is_live', true);

        $article->refresh();
        $this->assertSame(
            $frozenUpdatedAt->utc()->format('Y-m-d H:i:s'),
            $article->updated_at->utc()->format('Y-m-d H:i:s'),
            'Start live coverage must not bump updated_at',
        );

        Carbon::setTestNow(Carbon::parse('2026-07-30 21:05:00', 'UTC'));

        $this->postJson('/api/v1/admin/live-updates/'.$article->slug.'/live/end')
            ->assertOk()
            ->assertJsonPath('data.is_live', false);

        $article->refresh();
        $this->assertSame(
            $frozenUpdatedAt->utc()->format('Y-m-d H:i:s'),
            $article->updated_at->utc()->format('Y-m-d H:i:s'),
            'End live coverage must not bump updated_at',
        );
    }

    public function test_live_entry_publish_bumps_parent_updated_at(): void
    {
        $article = $this->makePublishedArticle([
            'is_live_blog' => true,
        ]);
        $frozenUpdatedAt = Carbon::parse('2026-06-01 12:00:00', 'UTC');
        $article->forceFill(['updated_at' => $frozenUpdatedAt])->save();

        Carbon::setTestNow(Carbon::parse('2026-07-30 22:00:00', 'UTC'));

        $this->postJson('/api/v1/admin/live-updates/'.$article->slug.'/entries', [
            'body' => '<p>Live timeline post</p>',
            'posted_at' => now()->toIso8601String(),
            'status' => LiveUpdateStatus::PUBLISHED->value,
        ])->assertCreated();

        $article->refresh();

        $this->assertTrue(
            $article->updated_at->gt($frozenUpdatedAt),
            'Publishing a live entry must bump parent updated_at',
        );
    }

    public function test_create_does_not_surface_updated_before_editorial_save(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-31 04:00:00', 'UTC'));

        $response = $this->postJson('/api/v1/admin/articles/store', [
            'title' => 'Brand new article',
            'slug' => 'brand-new-article',
            'article_description' => '<p>Fresh body</p>',
            'status' => ArticleStatus::PUBLISHED->value,
            'visibility' => ArticleVisibility::PUBLIC->value,
            'article_category_id' => $this->category->id,
        ]);

        $response->assertCreated();

        $article = Article::query()->where('slug', 'brand-new-article')->first();
        $this->assertNotNull($article);
        $this->assertFalse((bool) $article->pending_editorial_timestamp);
        $this->assertSame(
            $article->created_at?->utc()->format('Y-m-d H:i:s'),
            $article->updated_at?->utc()->format('Y-m-d H:i:s'),
            'On create, updated_at must match created_at so Updated stays hidden',
        );
    }

    public function test_manual_save_after_auto_save_bumps_updated_at(): void
    {
        \App\Models\SiteSettings::query()->create([
            'site_name' => 'ZBC News',
            'enable_auto_save' => true,
            'default_category_id' => $this->category->id,
        ]);

        $article = $this->makePublishedArticle([
            'article_description' => '<p>Original body</p>',
        ]);
        $publishedAt = $article->published_at->copy();
        $frozenUpdatedAt = Carbon::parse('2026-06-01 12:00:00', 'UTC');
        $article->forceFill(['updated_at' => $frozenUpdatedAt])->save();

        Carbon::setTestNow(Carbon::parse('2026-07-30 14:00:00', 'UTC'));

        $this->postJson('/api/v1/admin/articles/auto-save/'.$article->slug, [
            'title' => $article->title,
            'article_description' => '<p>Auto-saved body change</p>',
            'article_category_id' => $this->category->id,
        ])->assertOk();

        $article->refresh();
        $this->assertSame(
            $frozenUpdatedAt->utc()->format('Y-m-d H:i:s'),
            $article->updated_at->utc()->format('Y-m-d H:i:s'),
            'Auto-save must not bump updated_at',
        );
        $this->assertTrue(
            (bool) $article->pending_editorial_timestamp,
            'Auto-save editorial change must set pending flag',
        );
        $this->assertSame('<p>Auto-saved body change</p>', $article->article_description);

        Carbon::setTestNow(Carbon::parse('2026-07-30 14:05:00', 'UTC'));

        // Same body as DB (already auto-saved) — manual Save must still bump.
        $this->postJson('/api/v1/admin/articles/update/'.$article->slug, $this->basePayload($article, [
            'article_description' => '<p>Auto-saved body change</p>',
        ]))->assertOk();

        $article->refresh();

        $this->assertTrue(
            $article->published_at->equalTo($publishedAt),
            'published_at must stay immutable',
        );
        $this->assertTrue(
            $article->updated_at->gt($frozenUpdatedAt),
            'Manual Save after auto-save must bump updated_at',
        );
        $this->assertFalse(
            (bool) $article->pending_editorial_timestamp,
            'Pending flag must clear after manual Save',
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePublishedArticle(array $overrides = []): Article
    {
        $publishedAt = Carbon::parse('2026-07-01 09:00:00', 'UTC');

        return Article::query()->create(array_merge([
            'title' => 'Editorial Timestamp Article',
            'slug' => 'editorial-timestamp-article',
            'article_description' => '<p>Original body</p>',
            'status' => ArticleStatus::PUBLISHED,
            'visibility' => ArticleVisibility::PUBLIC,
            'article_category_id' => $this->category->id,
            'user_id' => $this->user->id,
            'published_at' => $publishedAt,
            'is_live_blog' => false,
            'is_breaking' => false,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function basePayload(Article $article, array $overrides = []): array
    {
        return array_merge([
            'title' => $article->title,
            'slug' => $article->slug,
            'article_description' => $article->article_description,
            'status' => $article->status instanceof ArticleStatus
                ? $article->status->value
                : (string) $article->status,
            'visibility' => ArticleVisibility::PUBLIC->value,
            'article_category_id' => $article->article_category_id,
            'published_at' => $article->published_at?->toIso8601String(),
            'is_live_blog' => (bool) $article->is_live_blog,
        ], $overrides);
    }

    private function seedPermissions(): void
    {
        $permissions = [
            'articles.create' => 'Articles',
            'articles.update' => 'Articles',
            'live-updates.list' => 'Live Updates',
            'live-updates.create' => 'Live Updates',
            'live-updates.show' => 'Live Updates',
            'live-updates.update' => 'Live Updates',
            'live-updates.delete' => 'Live Updates',
        ];

        foreach ($permissions as $name => $group) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => $group],
            );
        }

        $role = Role::query()->firstOrCreate(['name' => 'editor', 'guard_name' => 'api']);
        $role->givePermissionTo(array_keys($permissions));
    }
}
