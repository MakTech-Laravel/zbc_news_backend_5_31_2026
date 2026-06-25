<?php

namespace Tests\Feature\Newsletter;

use App\Events\UserNotificationCreated;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NewsletterSubscriptionAdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'super_admin', 'user'] as $role) {
            Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }
    }

    public function test_admin_receives_realtime_notification_when_someone_subscribes(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $regularUser = User::factory()->create();
        $regularUser->assignRole('user');

        $response = $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'subscriber@example.com',
            'name' => 'News Reader',
            'source' => 'website',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'category' => 'system',
            'title' => 'New newsletter subscription',
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $regularUser->id,
            'title' => 'New newsletter subscription',
        ]);

        Event::assertDispatched(UserNotificationCreated::class, function (UserNotificationCreated $event) use ($admin) {
            return (int) $event->notification->user_id === (int) $admin->id;
        });
    }

    public function test_admin_receives_notification_when_subscription_is_verified(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'verified@example.com',
            'name' => 'Verified Reader',
        ])->assertCreated();

        $token = (string) \App\Models\NewsletterSubscriber::query()
            ->where('email', 'verified@example.com')
            ->value('verification_token');

        UserNotification::query()->delete();

        $this->getJson('/api/v1/newsletter/verify?token='.$token)
            ->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'category' => 'system',
            'title' => 'Newsletter subscription verified',
        ]);

        Event::assertDispatched(UserNotificationCreated::class);
    }
}
