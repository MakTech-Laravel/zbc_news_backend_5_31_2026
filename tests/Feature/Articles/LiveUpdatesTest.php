<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Enums\LiveUpdateStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleLiveUpdate;
use App\Models\Media;
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
            'live-updates.list',
            'live-updates.create',
            'live-updates.show',
            'live-updates.update',
            'live-updates.delete',
        ] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => str_starts_with($name, 'live-updates') ? 'Live Updates' : 'Articles'],
            );
        }

        $role = Role::query()->firstOrCreate(['name' => 'editor', 'guard_name' => 'api']);
        $role->givePermissionTo([
            'articles.list',
            'live-updates.list',
            'live-updates.create',
            'live-updates.show',
            'live-updates.update',
            'live-updates.delete',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('editor');
        $this->admin->givePermissionTo([
            'articles.list',
            'live-updates.list',
            'live-updates.create',
            'live-updates.show',
            'live-updates.update',
            'live-updates.delete',
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

    public function test_admin_can_publish_live_update_when_article_description_is_null(): void
    {
        $article = $this->makeLiveBlog('null-description-live', [
            'status' => ArticleStatus::DRAFT,
            'published_at' => null,
        ]);

        $this->postJson('/api/v1/admin/live-updates/update/'.$article->slug, [
            'title' => $article->title,
            'slug' => $article->slug,
            'article_description' => null,
            'article_category_id' => $this->category->id,
            'status' => ArticleStatus::PUBLISHED->value,
            'visibility' => 'public',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', ArticleStatus::PUBLISHED->value);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'status' => ArticleStatus::PUBLISHED->value,
            'article_description' => '',
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

    public function test_live_update_shell_attaches_featured_video_and_poster(): void
    {
        $video = Media::factory()->create([
            'uploaded_by' => $this->admin->id,
            'status' => 'ready',
            'media_type' => 'video',
            'resource_type' => 'video',
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
            'original_filename' => 'live.mp4',
            'url' => 'https://res.cloudinary.com/test/video/upload/v1/live.mp4',
            'thumbnail_url' => 'https://res.cloudinary.com/test/video/upload/so_0/live.jpg',
        ]);

        $poster = Media::factory()->create([
            'uploaded_by' => $this->admin->id,
            'status' => 'ready',
            'media_type' => 'image',
            'url' => 'https://res.cloudinary.com/test/image/upload/v1/poster.jpg',
            'thumbnail_url' => 'https://res.cloudinary.com/test/image/upload/w_200/poster.jpg',
        ]);

        $response = $this->postJson('/api/v1/admin/live-updates/store', [
            'title' => 'Video Live Blog',
            'slug' => 'video-live-blog',
            'article_description' => '<p>Intro</p>',
            'article_category_id' => $this->category->id,
            'status' => ArticleStatus::PUBLISHED->value,
            'visibility' => 'public',
            'featured_media_uuid' => $video->uuid,
            'poster_media_uuid' => $poster->uuid,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_live_blog', true)
            ->assertJsonPath('data.featured_media.type', 'video')
            ->assertJsonPath('data.featured_media.uuid', $video->uuid)
            ->assertJsonPath('data.featured_media.poster_uuid', $poster->uuid);

        $this->assertSame(
            'https://res.cloudinary.com/test/image/upload/v1/poster.jpg',
            $response->json('data.featured_media.poster_url'),
        );

        $public = $this->getJson('/api/v1/articles/show/video-live-blog')
            ->assertOk();

        $this->assertSame('video', $public->json('data.featured_media.type'));
        $this->assertSame($video->uuid, $public->json('data.featured_media.uuid'));
        $this->assertSame($poster->uuid, $public->json('data.featured_media.poster_uuid'));
    }

    public function test_live_update_shell_exposes_youtube_live_video_with_poster(): void
    {
        $embedUrl = 'https://www.youtube.com/embed/dQw4w9WgXcQ';
        $posterUrl = 'https://res.cloudinary.com/test/image/upload/v1/live-poster.jpg';

        $response = $this->postJson('/api/v1/admin/live-updates/store', [
            'title' => 'YouTube Live Blog',
            'slug' => 'youtube-live-blog',
            'article_description' => '<p>Intro</p>',
            'article_category_id' => $this->category->id,
            'status' => ArticleStatus::PUBLISHED->value,
            'visibility' => 'public',
            'live_video_url' => $embedUrl,
            'featured_image' => $posterUrl,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.live_video_url', $embedUrl)
            ->assertJsonPath('data.featured_media.type', 'video')
            ->assertJsonPath('data.featured_media.provider', 'youtube')
            ->assertJsonPath('data.featured_media.url', $embedUrl)
            ->assertJsonPath('data.featured_media.poster_url', $posterUrl);

        $this->assertDatabaseHas('articles', [
            'slug' => 'youtube-live-blog',
            'is_live_blog' => true,
            'live_video_url' => $embedUrl,
            'featured_image' => $posterUrl,
        ]);

        $this->getJson('/api/v1/articles/show/youtube-live-blog')
            ->assertOk()
            ->assertJsonPath('data.featured_media.provider', 'youtube')
            ->assertJsonPath('data.featured_media.poster_url', $posterUrl);
    }

    public function test_public_latest_stories_ranks_active_live_blog_by_latest_entry(): void
    {
        $regular = Article::query()->create([
            'title' => 'Regular headline',
            'slug' => 'regular-headline',
            'article_description' => '<p>Body</p>',
            'status' => ArticleStatus::PUBLISHED,
            'is_live_blog' => false,
            'article_category_id' => $this->category->id,
            'user_id' => $this->admin->id,
            'published_at' => now()->subHour(),
        ]);

        $liveBlog = $this->makeLiveBlog('breaking-live', [
            'title' => 'Breaking Live',
            'is_live' => true,
            'live_started_at' => now()->subDays(2),
            'published_at' => now()->subDays(5),
        ]);

        ArticleLiveUpdate::query()->create([
            'article_id' => $liveBlog->id,
            'body' => '<p>Update at 2:30 PM</p>',
            'posted_at' => now(),
            'status' => LiveUpdateStatus::PUBLISHED,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/v1/articles/latest-stories')
            ->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$liveBlog->id, $regular->id], array_slice($ids, 0, 2));
        $this->assertTrue((bool) $response->json('data.0.is_live'));
        $this->assertTrue((bool) $response->json('data.0.is_live_blog'));
    }

    public function test_public_latest_stories_uses_publish_time_for_ended_live_blog(): void
    {
        $recentRegular = Article::query()->create([
            'title' => 'Fresh regular',
            'slug' => 'fresh-regular',
            'article_description' => '<p>Body</p>',
            'status' => ArticleStatus::PUBLISHED,
            'is_live_blog' => false,
            'article_category_id' => $this->category->id,
            'user_id' => $this->admin->id,
            'published_at' => now()->subHour(),
        ]);

        $endedLive = $this->makeLiveBlog('ended-live-feed', [
            'title' => 'Ended Live',
            'is_live' => false,
            'live_started_at' => now()->subDays(3),
            'live_ended_at' => now()->subDay(),
            'published_at' => now()->subDays(4),
        ]);

        ArticleLiveUpdate::query()->create([
            'article_id' => $endedLive->id,
            'body' => '<p>Late update</p>',
            'posted_at' => now(),
            'status' => LiveUpdateStatus::PUBLISHED,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/v1/articles/latest-stories')->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame($recentRegular->id, $ids[0]);
        $this->assertContains($endedLive->id, $ids);
        $this->assertFalse((bool) $response->json('data.0.is_live'));
    }

    public function test_public_latest_article_prefers_active_live_blog_with_recent_entry(): void
    {
        Article::query()->create([
            'title' => 'Regular latest',
            'slug' => 'regular-latest',
            'article_description' => '<p>Body</p>',
            'status' => ArticleStatus::PUBLISHED,
            'is_live_blog' => false,
            'article_category_id' => $this->category->id,
            'user_id' => $this->admin->id,
            'published_at' => now()->subMinutes(10),
        ]);

        $liveBlog = $this->makeLiveBlog('hero-live', [
            'title' => 'Hero Live',
            'is_live' => true,
            'live_started_at' => now()->subDay(),
            'published_at' => now()->subDays(3),
        ]);

        ArticleLiveUpdate::query()->create([
            'article_id' => $liveBlog->id,
            'body' => '<p>Just in</p>',
            'posted_at' => now(),
            'status' => LiveUpdateStatus::PUBLISHED,
            'user_id' => $this->admin->id,
        ]);

        $this->getJson('/api/v1/articles/latest')
            ->assertOk()
            ->assertJsonPath('data.id', $liveBlog->id)
            ->assertJsonPath('data.is_live', true);
    }

    public function test_public_live_blogs_feed_orders_ongoing_first_and_paginates(): void
    {
        $ongoing = $this->makeLiveBlog('ongoing-live', [
            'title' => 'Ongoing Live',
            'is_live' => true,
            'live_started_at' => now()->subHour(),
            'published_at' => now()->subDays(2),
        ]);

        $ended = $this->makeLiveBlog('ended-live', [
            'title' => 'Ended Live',
            'is_live' => false,
            'live_started_at' => now()->subDays(3),
            'live_ended_at' => now()->subDay(),
            'published_at' => now()->subDay(),
        ]);

        Article::query()->create([
            'title' => 'Regular Article',
            'slug' => 'regular-not-live-blog',
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'is_live_blog' => false,
            'article_category_id' => $this->category->id,
            'user_id' => $this->admin->id,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/articles/live-blogs?per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 2);

        $ids = collect($response->json('data.articles'))->pluck('id')->all();
        $this->assertSame([$ongoing->id, $ended->id], $ids);
        $this->assertTrue((bool) $response->json('data.articles.0.is_live'));
        $this->assertFalse((bool) $response->json('data.articles.1.is_live'));
    }
}
