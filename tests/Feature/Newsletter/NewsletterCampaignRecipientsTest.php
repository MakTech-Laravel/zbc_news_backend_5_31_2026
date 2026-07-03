<?php

namespace Tests\Feature\Newsletter;

use App\Jobs\FinalizeNewsletterCampaignJob;
use App\Jobs\SendNewsletterCampaignJob;
use App\Jobs\SendNewsletterEmailJob;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Newsletter\NewsletterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NewsletterCampaignRecipientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'super_admin', 'user', 'premium', 'member'] as $role) {
            Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }
    }

    public function test_eligible_count_for_premium_campaign_counts_only_verified_subscribers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        NewsletterSubscriber::query()->create([
            'email' => 'premium@example.com',
            'status' => 'verified',
            'is_premium' => true,
            'verification_token' => 'token-1',
            'unsubscribe_token' => 'unsub-1',
            'verified_at' => now(),
        ]);

        NewsletterSubscriber::query()->create([
            'email' => 'regular@example.com',
            'status' => 'verified',
            'is_premium' => false,
            'verification_token' => 'token-2',
            'unsubscribe_token' => 'unsub-2',
            'verified_at' => now(),
        ]);

        NewsletterSubscriber::query()->create([
            'email' => 'pending@example.com',
            'status' => 'pending',
            'verification_token' => 'token-3',
            'unsubscribe_token' => 'unsub-3',
        ]);

        $user = User::factory()->create(['email' => 'portal-user@example.com']);
        $user->assignRole('user');

        $this->getJson('/api/v1/admin/newsletter/campaigns/eligible-count?premium_only=1')
            ->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.breakdown.subscribers', 2)
            ->assertJsonPath('data.breakdown.users', 0);
    }

    public function test_eligible_count_for_standard_campaign_includes_all_subscribers_and_users(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        NewsletterSubscriber::query()->create([
            'email' => 'verified@example.com',
            'status' => 'verified',
            'verification_token' => 'token-3',
            'unsubscribe_token' => 'unsub-3',
            'verified_at' => now(),
        ]);

        NewsletterSubscriber::query()->create([
            'email' => 'pending@example.com',
            'status' => 'pending',
            'verification_token' => 'token-4',
            'unsubscribe_token' => 'unsub-4',
        ]);

        $user = User::factory()->create(['email' => 'portal-user@example.com']);
        $user->assignRole('user');

        $this->getJson('/api/v1/admin/newsletter/campaigns/eligible-count?premium_only=0')
            ->assertOk()
            ->assertJsonPath('data.count', 3)
            ->assertJsonPath('data.breakdown.subscribers', 2)
            ->assertJsonPath('data.breakdown.users', 1);
    }

    public function test_premium_campaign_excludes_pending_subscribers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        NewsletterSubscriber::query()->create([
            'email' => 'pending@example.com',
            'status' => 'pending',
            'verification_token' => 'token-5',
            'unsubscribe_token' => 'unsub-5',
        ]);

        $this->getJson('/api/v1/admin/newsletter/campaigns/eligible-count?premium_only=1')
            ->assertOk()
            ->assertJsonPath('data.count', 0);
    }

    public function test_standard_campaign_includes_verified_subscribers_and_users(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        NewsletterSubscriber::query()->create([
            'email' => 'verified@example.com',
            'status' => 'verified',
            'verification_token' => 'token-6',
            'unsubscribe_token' => 'unsub-6',
            'verified_at' => now(),
        ]);

        $user = User::factory()->create(['email' => 'portal-user@example.com']);
        $user->assignRole('user');

        $this->getJson('/api/v1/admin/newsletter/campaigns/eligible-count?premium_only=0')
            ->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.breakdown.subscribers', 1)
            ->assertJsonPath('data.breakdown.users', 1);
    }

    public function test_unsubscribed_emails_are_excluded_from_user_audience(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        $user = User::factory()->create(['email' => 'blocked@example.com']);
        $user->assignRole('user');

        NewsletterSubscriber::query()->create([
            'email' => 'blocked@example.com',
            'status' => 'unsubscribed',
            'verification_token' => 'token-4',
            'unsubscribe_token' => 'unsub-4',
            'unsubscribed_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/newsletter/campaigns/eligible-count?premium_only=0')
            ->assertOk()
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.breakdown.users', 0);
    }

    public function test_resolve_campaign_recipients_can_include_same_email_twice_for_subscriber_and_user(): void
    {
        $user = User::factory()->create(['email' => 'dual@example.com']);
        $user->assignRole('user');

        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'dual@example.com',
            'status' => 'verified',
            'user_id' => $user->id,
            'verification_token' => 'token-5',
            'unsubscribe_token' => 'unsub-5',
            'verified_at' => now(),
        ]);

        $campaign = NewsletterCampaign::query()->create([
            'title' => 'Broad reach',
            'subject' => 'Hello',
            'content_html' => '<p>Hi</p>',
            'status' => 'draft',
            'premium_only' => false,
        ]);

        $recipientIds = app(NewsletterService::class)->resolveCampaignRecipientIds($campaign);

        $this->assertCount(2, $recipientIds);
        $this->assertSame([(int) $subscriber->id, (int) $subscriber->id], $recipientIds);
    }

    public function test_dispatch_campaign_provisions_subscriber_for_registered_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        $user = User::factory()->create(['email' => 'account-only@example.com']);
        $user->assignRole('user');

        $campaign = NewsletterCampaign::query()->create([
            'title' => 'Account reach',
            'subject' => 'Hello account user',
            'content_html' => '<p>Welcome</p>',
            'status' => 'draft',
            'premium_only' => false,
        ]);

        $this->postJson("/api/v1/admin/newsletter/campaigns/send/{$campaign->id}")
            ->assertOk();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'account-only@example.com',
            'status' => 'verified',
            'source' => 'account',
        ]);
    }

    public function test_campaign_job_dispatches_sequential_email_job_chain(): void
    {
        Bus::fake();

        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'chain@example.com',
            'status' => 'verified',
            'verification_token' => 'token-chain',
            'unsubscribe_token' => 'unsub-chain',
            'verified_at' => now(),
        ]);

        $campaign = NewsletterCampaign::query()->create([
            'title' => 'Chained send',
            'subject' => 'Hello chain',
            'content_html' => '<p>Hi</p>',
            'status' => 'sending',
            'premium_only' => true,
        ]);

        (new SendNewsletterCampaignJob($campaign->id))->handle(app(NewsletterService::class));

        Bus::assertChained([
            new SendNewsletterEmailJob($campaign->id, $subscriber->id),
            new FinalizeNewsletterCampaignJob($campaign->id),
        ]);
    }
}
