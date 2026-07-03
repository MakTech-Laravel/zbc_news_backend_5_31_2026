<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Newsletter\NewsletterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NewsletterVerificationAndCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'super_admin', 'user'] as $role) {
            Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }
    }

    public function test_verify_preview_returns_subscriber_status_without_verifying(): void
    {
        Event::fake();

        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'preview@example.com',
        ])->assertCreated();

        $token = (string) NewsletterSubscriber::query()
            ->where('email', 'preview@example.com')
            ->value('verification_token');

        $this->getJson('/api/v1/newsletter/verify/preview?token='.$token)
            ->assertOk()
            ->assertJsonPath('data.email', 'preview@example.com')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'preview@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_verify_preview_shows_verified_status_for_already_verified_subscriber(): void
    {
        Event::fake();

        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'preview-verified@example.com',
        ])->assertCreated();

        $token = (string) NewsletterSubscriber::query()
            ->where('email', 'preview-verified@example.com')
            ->value('verification_token');

        $this->getJson('/api/v1/newsletter/verify?token='.$token)->assertOk();

        $this->getJson('/api/v1/newsletter/verify/preview?token='.$token)
            ->assertOk()
            ->assertJsonPath('data.email', 'preview-verified@example.com')
            ->assertJsonPath('data.status', 'verified');
    }

    public function test_verify_is_idempotent_and_keeps_verification_token(): void
    {
        Event::fake();

        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'reader@example.com',
            'name' => 'Reader',
        ])->assertCreated();

        $token = (string) NewsletterSubscriber::query()
            ->where('email', 'reader@example.com')
            ->value('verification_token');

        $this->getJson('/api/v1/newsletter/verify?token='.$token)
            ->assertOk()
            ->assertJsonPath('data.already_verified', false);

        $this->getJson('/api/v1/newsletter/verify?token='.$token)
            ->assertOk()
            ->assertJsonPath('data.already_verified', true);

        $subscriber = NewsletterSubscriber::query()->where('email', 'reader@example.com')->first();

        $this->assertSame('verified', $subscriber->status);
        $this->assertSame($token, $subscriber->verification_token);
    }

    public function test_admin_verify_preserves_token_for_subsequent_email_verification(): void
    {
        Event::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'admin-verified@example.com',
        ])->assertCreated();

        $subscriber = NewsletterSubscriber::query()
            ->where('email', 'admin-verified@example.com')
            ->firstOrFail();

        $token = $subscriber->verification_token;

        $this->postJson("/api/v1/admin/newsletter/subscribers/update/{$subscriber->id}", [
            'status' => 'verified',
        ])->assertOk();

        $this->getJson('/api/v1/newsletter/verify?token='.$token)
            ->assertOk()
            ->assertJsonPath('data.already_verified', true);

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'admin-verified@example.com',
            'status' => 'verified',
            'verification_token' => $token,
        ]);
    }

    public function test_verified_subscriber_status_cannot_be_modified_by_admin(): void
    {
        Event::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'locked@example.com',
        ])->assertCreated();

        $subscriber = NewsletterSubscriber::query()
            ->where('email', 'locked@example.com')
            ->firstOrFail();

        $token = $subscriber->verification_token;

        $this->getJson('/api/v1/newsletter/verify?token='.$token)->assertOk();

        $this->postJson("/api/v1/admin/newsletter/subscribers/update/{$subscriber->id}", [
            'status' => 'pending',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Verified subscribers cannot be set back to pending.');
    }

    public function test_admin_can_unsubscribe_verified_subscriber(): void
    {
        Event::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'unsub-verified@example.com',
        ])->assertCreated();

        $subscriber = NewsletterSubscriber::query()
            ->where('email', 'unsub-verified@example.com')
            ->firstOrFail();

        $this->postJson("/api/v1/admin/newsletter/subscribers/update/{$subscriber->id}", [
            'status' => 'verified',
        ])->assertOk();

        $this->postJson("/api/v1/admin/newsletter/subscribers/update/{$subscriber->id}", [
            'status' => 'unsubscribed',
        ])->assertOk();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'unsub-verified@example.com',
            'status' => 'unsubscribed',
        ]);
    }

    public function test_send_campaign_without_eligible_recipients_returns_user_friendly_error(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        $campaign = NewsletterCampaign::query()->create([
            'title' => 'Weekly digest',
            'subject' => 'This week at ZBC',
            'content_html' => '<p>Hello</p>',
            'status' => 'draft',
            'premium_only' => true,
        ]);

        $this->postJson("/api/v1/admin/newsletter/campaigns/send/{$campaign->id}")
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'No eligible recipients for this campaign. Users may be unsubscribed or the audience is empty.',
            );
    }

    public function test_process_due_scheduled_campaigns_reverts_failed_campaign_to_draft(): void
    {
        $campaign = NewsletterCampaign::query()->create([
            'title' => 'Scheduled digest',
            'subject' => 'Scheduled subject',
            'content_html' => '<p>Hello</p>',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
            'segments' => ['category_slugs' => ['sports']],
        ]);

        $processed = app(NewsletterService::class)->processDueScheduledCampaigns();

        $this->assertSame(0, $processed);
        $this->assertDatabaseHas('newsletter_campaigns', [
            'id' => $campaign->id,
            'status' => 'draft',
            'scheduled_at' => null,
        ]);
    }

    public function test_verified_subscriber_is_not_reset_when_subscribing_again(): void
    {
        Event::fake();

        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'repeat@example.com',
        ])->assertCreated();

        $token = (string) NewsletterSubscriber::query()
            ->where('email', 'repeat@example.com')
            ->value('verification_token');

        $this->getJson('/api/v1/newsletter/verify?token='.$token)->assertOk();

        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'repeat@example.com',
        ])->assertCreated();

        $subscriber = NewsletterSubscriber::query()
            ->where('email', 'repeat@example.com')
            ->firstOrFail();

        $this->assertSame('verified', $subscriber->status);
        $this->assertSame($token, $subscriber->verification_token);
    }
}
