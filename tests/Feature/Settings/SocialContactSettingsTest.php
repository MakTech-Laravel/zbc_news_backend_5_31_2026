<?php

namespace Tests\Feature\Settings;

use App\Models\SiteSettings;
use App\Models\User;
use App\Services\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialContactSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['site-settings.list', 'site-settings.update'] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Settings'],
            );
        }

        $role = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'api']);
        $role->givePermissionTo(['site-settings.list', 'site-settings.update']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        Passport::actingAs($this->admin);

        SiteSettings::query()->create([
            'site_name' => 'ZBC News',
            'posts_per_page' => 10,
            'allow_comments' => true,
            'enable_comments' => true,
        ]);
    }

    public function test_public_site_settings_expose_default_social_and_contact_values(): void
    {
        app(SiteSettingsService::class)->clearCache();

        $this->getJson('/api/v1/site-settings')
            ->assertOk()
            ->assertJsonPath('data.social_facebook_url', 'https://facebook.com/zomibroadcasting')
            ->assertJsonPath('data.contact_general_email', 'info@zbc.news');
    }

    public function test_admin_can_update_social_and_contact_settings(): void
    {
        $this->post('/api/v1/admin/site-settings/update', [
            'social_facebook_url' => 'https://facebook.com/custom-page',
            'social_x_url' => 'https://x.com/custom-handle',
            'contact_general_email' => 'hello@zbc.news',
            'contact_press_email' => 'press@zbc.news',
            'contact_advertising_email' => 'sales@zbc.news',
            'contact_corrections_email' => 'fix@zbc.news',
        ])->assertOk();

        $stored = SiteSettings::query()->first()?->social_contact_settings;
        $this->assertIsArray($stored);
        $this->assertSame('https://facebook.com/custom-page', $stored['social_facebook_url']);
        $this->assertSame('hello@zbc.news', $stored['contact_general_email']);

        app(SiteSettingsService::class)->clearCache();

        $this->getJson('/api/v1/site-settings')
            ->assertOk()
            ->assertJsonPath('data.social_facebook_url', 'https://facebook.com/custom-page')
            ->assertJsonPath('data.contact_corrections_email', 'fix@zbc.news');
    }

    public function test_admin_can_update_office_settings(): void
    {
        $address = "100 Main Street\nBoston, MA 02108\nUnited States";

        $this->post('/api/v1/admin/site-settings/update', [
            'contact_office_address' => $address,
            'contact_office_maps_url' => 'https://maps.google.com/?q=100+Main+Street+Boston',
        ])->assertOk();

        app(SiteSettingsService::class)->clearCache();

        $this->getJson('/api/v1/site-settings')
            ->assertOk()
            ->assertJsonPath('data.contact_office_address', $address)
            ->assertJsonPath(
                'data.contact_office_maps_url',
                'https://maps.google.com/?q=100+Main+Street+Boston',
            );
    }
}
