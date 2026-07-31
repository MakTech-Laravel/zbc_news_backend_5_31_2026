<?php

namespace Tests\Feature\Newsletter;

use App\Enums\ArticleCategoryStatus;
use App\Enums\ArticleStatus;
use App\Jobs\SendNewsletterWelcomeEmailJob;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterEvent;
use App\Models\NewsletterSubscriber;
use App\Models\SiteSettings;
use App\Models\User;
use App\Services\Newsletter\NewsletterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NewsletterClientGapsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'super_admin', 'user'] as $role) {
            Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
        Passport::actingAs($this->admin);
    }

    public function test_verify_dispatches_welcome_email_job(): void
    {
        Event::fake();
        Bus::fake([SendNewsletterWelcomeEmailJob::class]);

        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'welcome@example.com',
            'name' => 'Welcome Reader',
        ])->assertCreated();

        $token = (string) NewsletterSubscriber::query()
            ->where('email', 'welcome@example.com')
            ->value('verification_token');

        $this->getJson('/api/v1/newsletter/verify?token='.$token)->assertOk();

        $subscriber = NewsletterSubscriber::query()->where('email', 'welcome@example.com')->first();
        $this->assertNotNull($subscriber);
        $this->assertSame('verified', $subscriber->status);

        Bus::assertDispatched(
            SendNewsletterWelcomeEmailJob::class,
            fn (SendNewsletterWelcomeEmailJob $job) => $job->subscriberId === $subscriber->id,
        );
    }

    public function test_build_article_email_block_includes_headline_summary_and_link(): void
    {
        $article = $this->makePublishedArticle([
            'title' => 'City Council Passes Budget',
            'slug' => 'city-council-passes-budget',
            'excerpt' => 'Lawmakers approved the annual spending plan.',
        ]);

        $block = app(NewsletterService::class)->buildArticleEmailBlock($article->id);

        $this->assertSame('City Council Passes Budget', $block['title']);
        $this->assertSame('Lawmakers approved the annual spending plan.', $block['excerpt']);
        $this->assertStringContainsString('city-council-passes-budget', $block['url']);
        $this->assertStringContainsString('City Council Passes Budget', $block['html']);
        $this->assertStringContainsString('Lawmakers approved the annual spending plan.', $block['html']);
        $this->assertStringContainsString('city-council-passes-budget', $block['html']);
        $this->assertStringContainsString('Read full article', $block['html']);
    }

    public function test_admin_can_search_published_articles_and_fetch_email_block(): void
    {
        $this->makePublishedArticle([
            'title' => 'Alpha Story About Markets',
            'slug' => 'alpha-story-markets',
            'excerpt' => 'Markets rose today.',
        ]);
        $this->makePublishedArticle([
            'title' => 'Beta Sports Recap',
            'slug' => 'beta-sports-recap',
        ]);

        $search = $this->getJson('/api/v1/admin/newsletter/articles/search?q=Markets');
        $search->assertOk()
            ->assertJsonPath('data.0.title', 'Alpha Story About Markets');

        $articleId = (int) $search->json('data.0.id');

        $this->getJson("/api/v1/admin/newsletter/articles/{$articleId}/email-block")
            ->assertOk()
            ->assertJsonPath('data.title', 'Alpha Story About Markets')
            ->assertJsonStructure(['data' => ['html', 'title', 'url']]);
    }

    public function test_admin_can_store_campaign_with_article_id(): void
    {
        $article = $this->makePublishedArticle([
            'title' => 'Linked Story',
            'slug' => 'linked-story',
        ]);

        $response = $this->postJson('/api/v1/admin/newsletter/campaigns/store', [
            'title' => 'Campaign with article',
            'content_html' => '<p>Hello readers</p>',
            'article_id' => $article->id,
            'premium_only' => false,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('newsletter_campaigns', [
            'title' => 'Campaign with article',
            'article_id' => $article->id,
        ]);
    }

    public function test_admin_can_send_test_campaign_email(): void
    {
        $campaign = NewsletterCampaign::query()->create([
            'title' => 'Testable Campaign',
            'subject' => 'Testable Campaign',
            'content_html' => '<p>Preview content</p>',
            'status' => 'draft',
        ]);

        $sent = [];
        $provider = \Mockery::mock(\App\Contracts\Newsletter\EmailProviderInterface::class);
        $provider->shouldReceive('send')->once()->andReturnUsing(function (array $payload) use (&$sent): void {
            $sent[] = $payload;
        });

        $this->mock(\App\Services\Newsletter\NewsletterEmailProviderFactory::class, function ($mock) use ($provider): void {
            $mock->shouldReceive('make')->andReturn($provider);
            $mock->shouldReceive('fromAddress')->andReturn([
                'email' => 'newsletter@example.com',
                'name' => 'ZBC News',
            ]);
        });

        $this->postJson("/api/v1/admin/newsletter/campaigns/test/{$campaign->id}", [
            'email' => 'previewer@example.com',
        ])->assertOk();

        $this->assertCount(1, $sent);
        $this->assertSame('previewer@example.com', $sent[0]['to']);
        $this->assertStringStartsWith('[TEST]', $sent[0]['subject']);
        $this->assertStringContainsString('Preview content', $sent[0]['html']);

        $campaign->refresh();
        $this->assertSame('draft', $campaign->status);
    }

    public function test_brevo_webhook_rejects_invalid_secret_and_handles_bounce(): void
    {
        SiteSettings::query()->create([
            'site_name' => 'ZBC News',
            'newsletter_webhook_secret' => 'test-secret',
        ]);

        NewsletterSubscriber::query()->create([
            'email' => 'bounced@example.com',
            'status' => 'verified',
            'verification_token' => 'verify-token',
            'unsubscribe_token' => 'unsub-token',
            'verified_at' => now(),
        ]);

        $this->postJson('/api/v1/newsletter/webhooks/brevo', [
            'email' => 'bounced@example.com',
            'event' => 'hard_bounce',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/newsletter/webhooks/brevo?secret=test-secret', [
            'email' => 'bounced@example.com',
            'event' => 'hard_bounce',
        ])->assertOk();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'bounced@example.com',
            'status' => 'unsubscribed',
        ]);

        $this->assertTrue(
            NewsletterEvent::query()
                ->where('event_type', 'bounce')
                ->whereHas('subscriber', fn ($q) => $q->where('email', 'bounced@example.com'))
                ->exists()
            || NewsletterEvent::query()->where('event_type', 'bounce')->exists()
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePublishedArticle(array $overrides = []): Article
    {
        $category = ArticleCategory::query()->firstOrCreate(
            ['slug' => 'newsletter-gaps'],
            [
                'title' => 'Newsletter Gaps',
                'status' => ArticleCategoryStatus::ACTIVE,
            ],
        );

        $author = User::factory()->create();

        return Article::query()->create(array_merge([
            'title' => 'Default Story',
            'slug' => 'default-story-'.uniqid(),
            'article_description' => '<p>Body</p>',
            'excerpt' => 'A short summary.',
            'status' => ArticleStatus::PUBLISHED,
            'article_category_id' => $category->id,
            'user_id' => $author->id,
            'published_at' => now(),
        ], $overrides));
    }
}
