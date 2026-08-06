<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AdminNotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'editor', 'admin', 'super_admin'] as $role) {
            Role::query()->firstOrCreate([
                'name' => $role,
                'guard_name' => 'api',
            ]);
        }
    }

    public function test_admin_can_update_dashboard_and_email_channels_independently(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        $settings = AdminNotificationPreferenceService::DEFAULTS;
        $settings[AdminNotificationPreferenceService::EVENT_CONTACT_INQUIRY] = [
            'dashboard' => false,
            'email' => true,
        ];
        $settings[AdminNotificationPreferenceService::EVENT_SECURITY_ALERT] = [
            'dashboard' => true,
            'email' => false,
        ];

        $this->putJson('/api/v1/admin/admin-notification-settings', [
            'admin_notification_email' => 'newsroom@zbc.news',
            'settings' => $settings,
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.contact_inquiry.dashboard', false)
            ->assertJsonPath('data.settings.contact_inquiry.email', true)
            ->assertJsonPath('data.settings.security_alert.dashboard', true)
            ->assertJsonPath('data.settings.security_alert.email', false)
            ->assertJsonPath('data.admin_notification_email', 'newsroom@zbc.news');

        $this->getJson('/api/v1/admin/admin-notification-settings')
            ->assertOk()
            ->assertJsonPath('data.settings.contact_inquiry.dashboard', false)
            ->assertJsonPath('data.settings.security_alert.email', false)
            ->assertJsonPath('data.admin_notification_email', 'newsroom@zbc.news');
    }

    public function test_admin_can_change_primary_notification_email(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        $this->putJson('/api/v1/admin/admin-notification-settings', [
            'admin_notification_email' => 'ops@zbc.news',
            'settings' => AdminNotificationPreferenceService::DEFAULTS,
        ])
            ->assertOk()
            ->assertJsonPath('data.admin_notification_email', 'ops@zbc.news');

        $service = app(AdminNotificationPreferenceService::class);
        $emails = $service->emailRecipients(
            AdminNotificationPreferenceService::EVENT_CONTACT_INQUIRY,
        )->pluck('email')->map(fn ($email) => strtolower((string) $email))->all();

        $this->assertContains('ops@zbc.news', $emails);
        $this->assertContains(strtolower((string) $admin->email), $emails);
    }

    public function test_non_admin_staff_cannot_view_or_update_global_settings(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        Passport::actingAs($editor);

        $this->getJson('/api/v1/admin/admin-notification-settings')->assertForbidden();
        $this->putJson('/api/v1/admin/admin-notification-settings', [
            'admin_notification_email' => 'newsroom@zbc.news',
            'settings' => AdminNotificationPreferenceService::DEFAULTS,
        ])->assertForbidden();
    }

    public function test_email_recipients_include_inbox_and_admins_but_exclude_editor(): void
    {
        $reader = User::factory()->create();
        $reader->assignRole('user');

        $editor = User::factory()->create(['email' => 'editor@example.com']);
        $editor->assignRole('editor');

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create(['email' => 'super@example.com']);
        $superAdmin->assignRole('super_admin');

        $service = app(AdminNotificationPreferenceService::class);
        $service->update(
            AdminNotificationPreferenceService::DEFAULTS,
            'newsroom@zbc.news',
        );

        $dashboardIds = $service->dashboardRecipientIds(
            AdminNotificationPreferenceService::EVENT_TASK_FAILURE,
        );
        $emailAddresses = $service->emailRecipients(
            AdminNotificationPreferenceService::EVENT_TASK_FAILURE,
        )->pluck('email')->map(fn ($email) => strtolower((string) $email))->all();

        $this->assertEqualsCanonicalizing(
            [$editor->id, $admin->id, $superAdmin->id],
            $dashboardIds->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['newsroom@zbc.news', 'admin@example.com', 'super@example.com'],
            $emailAddresses,
        );
        $this->assertNotContains('editor@example.com', $emailAddresses);
        $this->assertNotContains($reader->id, $dashboardIds);

        $settings = AdminNotificationPreferenceService::DEFAULTS;
        $settings[AdminNotificationPreferenceService::EVENT_TASK_FAILURE]['email'] = false;
        $service->update($settings, 'newsroom@zbc.news');

        $this->assertEmpty(
            $service->emailRecipients(
                AdminNotificationPreferenceService::EVENT_TASK_FAILURE,
            ),
        );
    }

    public function test_primary_inbox_is_deduped_when_matching_admin_email(): void
    {
        $admin = User::factory()->create(['email' => 'newsroom@zbc.news']);
        $admin->assignRole('admin');

        $service = app(AdminNotificationPreferenceService::class);
        $service->update(
            AdminNotificationPreferenceService::DEFAULTS,
            'newsroom@zbc.news',
        );

        $emails = $service->emailRecipients(
            AdminNotificationPreferenceService::EVENT_SECURITY_ALERT,
        )->pluck('email')->map(fn ($email) => strtolower((string) $email))->all();

        $this->assertSame(['newsroom@zbc.news'], array_values(array_unique($emails)));
        $this->assertCount(1, $emails);
    }
}
