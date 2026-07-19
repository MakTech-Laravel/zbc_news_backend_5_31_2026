<?php

namespace Tests\Feature\Ads;

use App\Models\AdSlot;
use App\Models\SiteSettings;
use App\Models\User;
use App\Services\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end: admin saves global Publisher ID + per-placement Ad Unit ID,
 * then public APIs expose both so the frontend can render AdSense.
 */
class AdSenseCredentialsFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['site-settings.list', 'site-settings.update', 'monetization.list', 'monetization.update'] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Settings'],
            );
        }

        $role = Role::query()->firstOrCreate(['name' => 'editor', 'guard_name' => 'api']);
        $role->givePermissionTo(['site-settings.list', 'site-settings.update', 'monetization.list', 'monetization.update']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('editor');
        $this->admin->givePermissionTo(['site-settings.list', 'site-settings.update', 'monetization.list', 'monetization.update']);

        Passport::actingAs($this->admin);

        SiteSettings::query()->create([
            'site_name' => 'ZBC News',
            'posts_per_page' => 10,
            'allow_comments' => true,
            'enable_comments' => true,
        ]);
    }

    public function test_publisher_id_saved_and_exposed_on_public_site_settings(): void
    {
        $this->post('/api/v1/admin/site-settings/update', [
            'google_adsense_client' => '  ca-pub-1234567890123456  ',
        ])->assertOk();

        $this->assertSame(
            'ca-pub-1234567890123456',
            SiteSettings::query()->first()?->google_adsense_client,
        );

        // Bust service cache the same way a fresh request would after admin save.
        app(SiteSettingsService::class)->clearCache();

        $this->getJson('/api/v1/site-settings')
            ->assertOk()
            ->assertJsonPath('data.google_adsense_client', 'ca-pub-1234567890123456');
    }

    public function test_google_placement_unit_id_saved_and_exposed_on_public_ads_slots(): void
    {
        $slot = AdSlot::query()->create([
            'slot_key' => 'home_banner_top',
            'name' => 'Home Banner Top',
            'placement' => 'home',
            'provider' => 'manual',
            'is_active' => false,
        ]);

        $this->postJson("/api/v1/admin/ad-slots/update/{$slot->id}", [
            'provider' => 'google',
            'is_active' => true,
            'google_ad_slot' => '  9876543210  ',
        ])->assertOk();

        $slot->refresh();
        $this->assertSame('google', $slot->provider);
        $this->assertTrue($slot->is_active);
        $this->assertSame('9876543210', $slot->google_ad_slot);

        $this->getJson('/api/v1/ads/slots')
            ->assertOk()
            ->assertJsonPath('data.home_banner_top.provider', 'google')
            ->assertJsonPath('data.home_banner_top.google_ad_slot', '9876543210');
    }

    public function test_full_adsense_config_is_available_to_frontend_consumers(): void
    {
        $this->post('/api/v1/admin/site-settings/update', [
            'google_adsense_client' => 'ca-pub-1111222233334444',
        ])->assertOk();

        $slot = AdSlot::query()->create([
            'slot_key' => 'left_sidebar_primary',
            'name' => 'Left Sidebar',
            'provider' => 'manual',
            'is_active' => false,
        ]);

        $this->postJson("/api/v1/admin/ad-slots/update/{$slot->id}", [
            'provider' => 'google',
            'is_active' => true,
            'google_ad_slot' => '5555666677',
        ])->assertOk();

        app(SiteSettingsService::class)->clearCache();

        $settings = $this->getJson('/api/v1/site-settings')->assertOk()->json('data');
        $slots = $this->getJson('/api/v1/ads/slots')->assertOk()->json('data');

        $this->assertSame('ca-pub-1111222233334444', $settings['google_adsense_client']);
        $this->assertSame('google', $slots['left_sidebar_primary']['provider']);
        $this->assertSame('5555666677', $slots['left_sidebar_primary']['google_ad_slot']);

        // Frontend AdUnit needs BOTH of these to call adsbygoogle.
        $this->assertNotEmpty($settings['google_adsense_client']);
        $this->assertNotEmpty($slots['left_sidebar_primary']['google_ad_slot']);
    }

    public function test_inactive_or_incomplete_google_slot_is_not_served_as_live_ad(): void
    {
        AdSlot::query()->create([
            'slot_key' => 'inactive_google',
            'name' => 'Inactive',
            'provider' => 'google',
            'is_active' => false,
            'google_ad_slot' => '111',
        ]);

        AdSlot::query()->create([
            'slot_key' => 'active_google_missing_unit',
            'name' => 'Missing unit',
            'provider' => 'google',
            'is_active' => true,
            'google_ad_slot' => null,
        ]);

        AdSlot::flushPublicCache();

        $slots = $this->getJson('/api/v1/ads/slots')->assertOk()->json('data');

        $this->assertArrayNotHasKey('inactive_google', $slots);
        $this->assertArrayHasKey('active_google_missing_unit', $slots);
        $this->assertNull($slots['active_google_missing_unit']['google_ad_slot']);
    }
}
