<?php

namespace Tests\Feature\Notifications;

use App\Enums\ArticleStatus;
use App\Events\UserNotificationCreated;
use App\Jobs\DispatchArticlePublishedNotifications;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleHistroy;
use App\Models\NotificationPreference;
use App\Models\SaveArticle;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function createCategory(): ArticleCategory
    {
        return ArticleCategory::query()->create([
            'title' => 'Technology',
            'slug' => 'technology',
            'status' => 'active',
        ]);
    }

    private function createArticle(ArticleCategory $category, User $author, array $overrides = []): Article
    {
        return Article::query()->create(array_merge([
            'title' => 'Test Article',
            'slug' => 'test-article-'.uniqid(),
            'article_description' => 'Body',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
        ], $overrides));
    }

    public function test_breaking_news_only_notifies_opted_in_users(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $category = $this->createCategory();
        $author = User::factory()->create();

        $optedIn = User::factory()->create();
        NotificationPreference::query()->create([
            'user_id' => $optedIn->id,
            'breaking_news' => true,
            'daily_newsletter' => false,
            'personalized_recommendations' => false,
            'comment_replies' => false,
            'saved_article_updates' => false,
            'platform_announcements' => true,
        ]);

        $optedOut = User::factory()->create();
        NotificationPreference::query()->create([
            'user_id' => $optedOut->id,
            'breaking_news' => false,
            'daily_newsletter' => false,
            'personalized_recommendations' => false,
            'comment_replies' => false,
            'saved_article_updates' => false,
            'platform_announcements' => true,
        ]);

        $article = $this->createArticle($category, $author);
        $breakingTag = Tag::query()->create(['tag' => 'breaking']);
        $article->tags()->attach($breakingTag->id);

        DispatchArticlePublishedNotifications::dispatchSync($article->id, 'published');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $optedIn->id,
            'category' => 'breaking',
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $optedOut->id,
            'category' => 'breaking',
        ]);

        Event::assertDispatched(UserNotificationCreated::class);
    }

    public function test_topic_recommendation_respects_preferences(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $category = $this->createCategory();
        $author = User::factory()->create();
        $reader = User::factory()->create();

        NotificationPreference::query()->create([
            'user_id' => $reader->id,
            'breaking_news' => false,
            'daily_newsletter' => false,
            'personalized_recommendations' => true,
            'comment_replies' => false,
            'saved_article_updates' => false,
            'platform_announcements' => true,
        ]);

        $article = $this->createArticle($category, $author);

        ArticleHistroy::query()->create([
            'user_id' => $reader->id,
            'article_id' => $article->id,
        ]);

        app(UserNotificationService::class)->dispatchArticlePublishedNotifications($article);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $reader->id,
            'category' => 'topic',
        ]);
    }

    public function test_saved_article_update_respects_preferences(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $category = $this->createCategory();
        $author = User::factory()->create();
        $saver = User::factory()->create();

        NotificationPreference::query()->create([
            'user_id' => $saver->id,
            'breaking_news' => false,
            'daily_newsletter' => false,
            'personalized_recommendations' => false,
            'comment_replies' => false,
            'saved_article_updates' => true,
            'platform_announcements' => true,
        ]);

        $article = $this->createArticle($category, $author);

        SaveArticle::query()->create([
            'user_id' => $saver->id,
            'article_id' => $article->id,
        ]);

        DispatchArticlePublishedNotifications::dispatchSync($article->id, 'updated');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $saver->id,
            'category' => 'saved',
        ]);
    }
}
