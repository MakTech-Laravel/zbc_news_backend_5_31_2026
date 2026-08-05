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
            'settings' => $settings,
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.contact_inquiry.dashboard', false)
            ->assertJsonPath('data.settings.contact_inquiry.email', true)
            ->assertJsonPath('data.settings.security_alert.dashboard', true)
            ->assertJsonPath('data.settings.security_alert.email', false);

        $this->getJson('/api/v1/admin/admin-notification-settings')
            ->assertOk()
            ->assertJsonPath('data.settings.contact_inquiry.dashboard', false)
            ->assertJsonPath('data.settings.security_alert.email', false);
    }

    public function test_non_admin_staff_cannot_view_or_update_global_settings(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        Passport::actingAs($editor);

        $this->getJson('/api/v1/admin/admin-notification-settings')->assertForbidden();
        $this->putJson('/api/v1/admin/admin-notification-settings', [
            'settings' => AdminNotificationPreferenceService::DEFAULTS,
        ])->assertForbidden();
    }

    public function test_enabled_channels_include_all_staff_but_exclude_user_role(): void
    {
        $reader = User::factory()->create();
        $reader->assignRole('user');

        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $service = app(AdminNotificationPreferenceService::class);

        $dashboardIds = $service->dashboardRecipientIds(
            AdminNotificationPreferenceService::EVENT_TASK_FAILURE,
        );
        $emailIds = $service->emailRecipients(
            AdminNotificationPreferenceService::EVENT_TASK_FAILURE,
        )->pluck('id');

        $this->assertEqualsCanonicalizing(
            [$editor->id, $admin->id, $superAdmin->id],
            $dashboardIds->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$editor->id, $admin->id, $superAdmin->id],
            $emailIds->all(),
        );
        $this->assertNotContains($reader->id, $dashboardIds);

        $settings = AdminNotificationPreferenceService::DEFAULTS;
        $settings[AdminNotificationPreferenceService::EVENT_TASK_FAILURE]['dashboard'] = false;
        $service->update($settings);

        $this->assertEmpty(
            $service->dashboardRecipientIds(
                AdminNotificationPreferenceService::EVENT_TASK_FAILURE,
            ),
        );
        $this->assertCount(
            3,
            $service->emailRecipients(
                AdminNotificationPreferenceService::EVENT_TASK_FAILURE,
            ),
        );
    }
}
