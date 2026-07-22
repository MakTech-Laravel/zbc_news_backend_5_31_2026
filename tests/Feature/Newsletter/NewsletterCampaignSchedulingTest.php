<?php

namespace Tests\Feature\Newsletter;

use App\Jobs\SendNewsletterCampaignJob;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Newsletter\NewsletterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NewsletterCampaignSchedulingTest extends TestCase
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
        $this->admin->assignRole('admin');
        Passport::actingAs($this->admin);
    }

    public function test_schedule_converts_offset_datetime_to_utc(): void
    {
        Bus::fake([SendNewsletterCampaignJob::class]);

        $campaign = NewsletterCampaign::query()->create([
            'title' => 'Offset schedule',
            'subject' => 'Offset schedule',
            'content_html' => '<p>Hello</p>',
            'status' => 'draft',
        ]);

        // Wall-clock 13:00 in UTC+6 => 07:00 UTC.
        $localWithOffset = Carbon::parse(
            now('UTC')->addDay()->format('Y-m-d').' 13:00:00',
            '+06:00',
        );

        $response = $this->postJson("/api/v1/admin/newsletter/campaigns/schedule/{$campaign->id}", [
            'scheduled_at' => $localWithOffset->toIso8601String(),
        ]);

        $response->assertOk();

        $campaign->refresh();

        $this->assertSame('scheduled', $campaign->status);
        $this->assertSame(
            $localWithOffset->copy()->utc()->format('Y-m-d H:i:s'),
            $campaign->scheduled_at?->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertSame('07:00:00', $campaign->scheduled_at?->utc()->format('H:i:s'));
    }

    public function test_past_schedule_dispatches_immediately_when_recipients_exist(): void
    {
        Bus::fake([SendNewsletterCampaignJob::class]);

        NewsletterSubscriber::query()->create([
            'email' => 'reader@example.com',
            'status' => 'verified',
            'verification_token' => 'verify-token',
            'unsubscribe_token' => 'unsub-token',
        ]);

        $campaign = NewsletterCampaign::query()->create([
            'title' => 'Past schedule',
            'subject' => 'Past schedule',
            'content_html' => '<p>Hello</p>',
            'status' => 'draft',
            'premium_only' => false,
        ]);

        $past = now('UTC')->subMinutes(5);

        $response = $this->postJson("/api/v1/admin/newsletter/campaigns/schedule/{$campaign->id}", [
            'scheduled_at' => $past->toIso8601String(),
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Campaign is sending now');

        $campaign->refresh();

        $this->assertSame('sending', $campaign->status);
        Bus::assertDispatched(SendNewsletterCampaignJob::class);
    }

    public function test_process_due_scheduled_campaigns_does_not_bump_updated_at(): void
    {
        Bus::fake([SendNewsletterCampaignJob::class]);

        NewsletterSubscriber::query()->create([
            'email' => 'due@example.com',
            'status' => 'verified',
            'verification_token' => 'verify-token-2',
            'unsubscribe_token' => 'unsub-token-2',
        ]);

        $campaign = NewsletterCampaign::query()->create([
            'title' => 'Due campaign',
            'subject' => 'Due campaign',
            'content_html' => '<p>Hello</p>',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
            'premium_only' => false,
        ]);

        $frozenUpdatedAt = now()->subDay()->startOfSecond();
        $campaign->timestamps = false;
        $campaign->forceFill(['updated_at' => $frozenUpdatedAt])->save();
        $campaign->timestamps = true;
        $campaign->refresh();

        $processed = app(NewsletterService::class)->processDueScheduledCampaigns();

        $this->assertSame(1, $processed);

        $campaign->refresh();
        $this->assertSame('sending', $campaign->status);
        $this->assertSame(
            $frozenUpdatedAt->utc()->format('Y-m-d H:i:s'),
            $campaign->updated_at->utc()->format('Y-m-d H:i:s'),
        );
    }

    public function test_listing_campaigns_processes_overdue_scheduled_campaigns(): void
    {
        Bus::fake([SendNewsletterCampaignJob::class]);

        NewsletterSubscriber::query()->create([
            'email' => 'list@example.com',
            'status' => 'verified',
            'verification_token' => 'verify-token-3',
            'unsubscribe_token' => 'unsub-token-3',
        ]);

        $campaign = NewsletterCampaign::query()->create([
            'title' => 'Overdue list campaign',
            'subject' => 'Overdue list campaign',
            'content_html' => '<p>Hello</p>',
            'status' => 'scheduled',
            'scheduled_at' => now()->subHour(),
            'premium_only' => false,
        ]);

        app(NewsletterService::class)->listCampaigns();

        $campaign->refresh();
        $this->assertSame('sending', $campaign->status);
        Bus::assertDispatched(SendNewsletterCampaignJob::class);
    }
}
