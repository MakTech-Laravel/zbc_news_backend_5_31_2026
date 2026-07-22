<?php

namespace Tests\Feature\Articles;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Jobs\DispatchArticlePublishedNotifications;
use App\Jobs\PublishScheduledArticles;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use App\Services\ArticleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduledArticlePublishingTest extends TestCase
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
        $this->user->givePermissionTo(['articles.create', 'articles.update']);

        Passport::actingAs($this->user);

        $this->category = ArticleCategory::query()->create([
            'title' => 'General',
            'slug' => 'general',
            'status' => ArticleCategoryStatus::ACTIVE,
        ]);
    }

    public function test_store_converts_offset_datetime_to_utc(): void
    {
        Bus::fake([DispatchArticlePublishedNotifications::class]);
        Event::fake();

        // Wall-clock 13:00 in UTC+6 => 07:00 UTC.
        $localWithOffset = Carbon::parse(
            now('UTC')->addDay()->format('Y-m-d').' 13:00:00',
            '+06:00',
        );
        $expectedUtc = $localWithOffset->copy()->utc()->format('Y-m-d H:i:s');

        $response = $this->postJson('/api/v1/admin/articles/store', [
            'title' => 'Scheduled from offset',
            'slug' => 'scheduled-from-offset',
            'article_description' => '<p>Body</p>',
            'status' => ArticleStatus::SCHEDULED->value,
            'article_category_id' => $this->category->id,
            'scheduled_publishing' => $localWithOffset->toIso8601String(),
        ]);

        $response->assertCreated();

        $article = Article::query()->where('slug', 'scheduled-from-offset')->first();

        $this->assertNotNull($article);
        $this->assertSame(ArticleStatus::SCHEDULED, $article->status);
        $this->assertSame(
            $expectedUtc,
            $article->scheduled_publishing?->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertSame('07:00:00', $article->scheduled_publishing?->utc()->format('H:i:s'));
    }

    public function test_store_accepts_utc_iso_datetime(): void
    {
        Bus::fake([DispatchArticlePublishedNotifications::class]);
        Event::fake();

        $scheduledUtc = Carbon::parse(
            now('UTC')->addDay()->format('Y-m-d').' 07:00:00',
            'UTC',
        );

        $response = $this->postJson('/api/v1/admin/articles/store', [
            'title' => 'Scheduled from utc iso',
            'slug' => 'scheduled-from-utc-iso',
            'article_description' => '<p>Body</p>',
            'status' => ArticleStatus::SCHEDULED->value,
            'article_category_id' => $this->category->id,
            'scheduled_publishing' => $scheduledUtc->toIso8601String(),
        ]);

        $response->assertCreated();

        $article = Article::query()->where('slug', 'scheduled-from-utc-iso')->first();

        $this->assertNotNull($article);
        $this->assertSame(
            $scheduledUtc->format('Y-m-d H:i:s'),
            $article->scheduled_publishing?->utc()->format('Y-m-d H:i:s'),
        );
    }

    public function test_publish_scheduled_articles_job_publishes_due_articles(): void
    {
        Bus::fake([DispatchArticlePublishedNotifications::class]);
        Event::fake();

        $due = Article::query()->create([
            'title' => 'Due scheduled',
            'slug' => 'due-scheduled',
            'article_description' => '<p>Body</p>',
            'status' => ArticleStatus::SCHEDULED->value,
            'article_category_id' => $this->category->id,
            'user_id' => $this->user->id,
            'scheduled_publishing' => now()->subMinute(),
        ]);

        // Freeze updated_at in the past so we can assert the job does not bump it.
        $frozenUpdatedAt = now()->subDay()->startOfSecond();
        $due->timestamps = false;
        $due->forceFill(['updated_at' => $frozenUpdatedAt])->save();
        $due->timestamps = true;
        $due->refresh();

        $future = Article::query()->create([
            'title' => 'Future scheduled',
            'slug' => 'future-scheduled',
            'article_description' => '<p>Body</p>',
            'status' => ArticleStatus::SCHEDULED->value,
            'article_category_id' => $this->category->id,
            'user_id' => $this->user->id,
            'scheduled_publishing' => now()->addHour(),
        ]);

        (new PublishScheduledArticles)->handle(app(ArticleService::class));

        $due->refresh();
        $future->refresh();

        $this->assertSame(ArticleStatus::PUBLISHED, $due->status);
        $this->assertNotNull($due->published_at);
        $this->assertTrue(
            $due->published_at->equalTo($due->scheduled_publishing),
        );
        $this->assertSame(
            $frozenUpdatedAt->utc()->format('Y-m-d H:i:s'),
            $due->updated_at->utc()->format('Y-m-d H:i:s'),
            'Scheduled publish job must not change updated_at',
        );

        $this->assertSame(ArticleStatus::SCHEDULED, $future->status);
        $this->assertNull($future->published_at);

        Bus::assertDispatched(DispatchArticlePublishedNotifications::class, function ($job) use ($due) {
            return $job->articleId === $due->id && $job->eventType === 'published';
        });
    }

    public function test_store_with_past_schedule_publishes_immediately(): void
    {
        Bus::fake([DispatchArticlePublishedNotifications::class]);
        Event::fake();

        $pastSchedule = now('UTC')->subMinutes(5);

        $response = $this->postJson('/api/v1/admin/articles/store', [
            'title' => 'Past schedule immediate',
            'slug' => 'past-schedule-immediate',
            'article_description' => '<p>Body</p>',
            'status' => ArticleStatus::SCHEDULED->value,
            'article_category_id' => $this->category->id,
            'scheduled_publishing' => $pastSchedule->toIso8601String(),
        ]);

        $response->assertCreated();

        $article = Article::query()->where('slug', 'past-schedule-immediate')->first();

        $this->assertNotNull($article);
        $this->assertSame(ArticleStatus::PUBLISHED, $article->status);
        $this->assertNotNull($article->published_at);
        $this->assertSame(
            $pastSchedule->format('Y-m-d H:i:s'),
            $article->published_at->utc()->format('Y-m-d H:i:s'),
        );

        Bus::assertDispatched(DispatchArticlePublishedNotifications::class, function ($job) use ($article) {
            return $job->articleId === $article->id && $job->eventType === 'published';
        });
    }

    public function test_listing_articles_publishes_overdue_scheduled_articles(): void
    {
        Bus::fake([DispatchArticlePublishedNotifications::class]);
        Event::fake();

        $overdue = Article::query()->create([
            'title' => 'Overdue scheduled',
            'slug' => 'overdue-scheduled',
            'article_description' => '<p>Body</p>',
            'status' => ArticleStatus::SCHEDULED->value,
            'article_category_id' => $this->category->id,
            'user_id' => $this->user->id,
            'scheduled_publishing' => now()->subHour(),
        ]);

        app(ArticleService::class)->getAllArticles();

        $overdue->refresh();
        $this->assertSame(ArticleStatus::PUBLISHED, $overdue->status);
        $this->assertNotNull($overdue->published_at);
    }

    private function seedPermissions(): void
    {
        foreach (['articles.create', 'articles.update'] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Articles'],
            );
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'editor', 'guard_name' => 'api'],
        );

        $role->givePermissionTo(['articles.create', 'articles.update']);
    }
}
