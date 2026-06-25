<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate(['name' => 'user', 'guard_name' => 'api']);

        foreach (['notification-preferences.show', 'notification-preferences.update'] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Notification Preferences'],
            );
        }

        Role::findByName('user', 'api')->givePermissionTo([
            'notification-preferences.show',
            'notification-preferences.update',
        ]);
    }

    public function test_user_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Passport::actingAs($user);

        $payload = [
            'breaking_news' => true,
            'daily_newsletter' => false,
            'personalized_recommendations' => true,
            'comment_replies' => true,
            'saved_article_updates' => false,
            'platform_announcements' => true,
        ];

        $response = $this->putJson('/api/v1/admin/notification-preferences/update', $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.platform_announcements', true)
            ->assertJsonPath('data.comment_replies', true);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'platform_announcements' => true,
            'comment_replies' => true,
        ]);
    }
}
