<?php

namespace Tests\Feature\Ads;

use App\Models\AdSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The public ad-slots endpoint is cached on a long TTL because it is admin-edited and changes
 * rarely. AdSlot has only two write paths — store and update — and no delete path; see
 * test_ad_slots_still_has_no_delete_path, which fails if that ever stops being true.
 */
class AdSlotsCacheTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['monetization.list', 'monetization.create', 'monetization.update'] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Monetization'],
            );
        }

        $role = Role::query()->firstOrCreate(['name' => 'editor', 'guard_name' => 'api']);
        $role->givePermissionTo(['monetization.list', 'monetization.create', 'monetization.update']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('editor');
        $this->admin->givePermissionTo(['monetization.list', 'monetization.create', 'monetization.update']);

        Passport::actingAs($this->admin);
    }

    private function makeSlot(array $overrides = []): AdSlot
    {
        return AdSlot::query()->create(array_merge([
            'slot_key' => 'home_top',
            'name' => 'Home Top',
            'provider' => 'manual',
            'is_active' => true,
            'manual_image_url' => 'https://example.test/a.png',
        ], $overrides));
    }

    private function slotKeys(): array
    {
        $response = $this->getJson('/api/v1/ads/slots')->assertOk();

        return array_keys($response->json('data') ?? []);
    }

    /**
     * The plan records that ad-slots has no delete path, so store/update are the only two
     * places needing invalidation. If a delete route is ever added, this fails loudly rather
     * than silently leaving the public cache stale after a deletion.
     */
    public function test_ad_slots_still_has_no_delete_path(): void
    {
        $deleteRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'ad-slots'))
            ->filter(fn ($route) => in_array('DELETE', $route->methods(), true))
            ->map(fn ($route) => $route->uri())
            ->values()
            ->all();

        $this->assertSame(
            [],
            $deleteRoutes,
            'A delete path for ad-slots now exists — it must call AdSlot::flushPublicCache(), then update this test.',
        );
    }

    public function test_repeat_request_is_served_from_cache(): void
    {
        $this->makeSlot();

        $this->getJson('/api/v1/ads/slots')->assertOk();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->getJson('/api/v1/ads/slots')->assertOk();

        $this->assertSame(0, $queries, 'Warm cache must not re-query the database.');
    }

    public function test_store_busts_the_cache(): void
    {
        $this->makeSlot();
        $this->assertSame(['home_top'], $this->slotKeys());

        $this->postJson('/api/v1/admin/ad-slots/store', [
            'slot_key' => 'sidebar',
            'name' => 'Sidebar',
            'provider' => 'manual',
            'is_active' => true,
        ])->assertCreated();

        $this->assertSame(['home_top', 'sidebar'], $this->slotKeys());
    }

    public function test_update_busts_the_cache(): void
    {
        $slot = $this->makeSlot();
        $this->getJson('/api/v1/ads/slots')->assertOk();

        $this->postJson("/api/v1/admin/ad-slots/update/{$slot->id}", [
            'manual_image_url' => 'https://example.test/updated.png',
        ])->assertOk();

        $this->getJson('/api/v1/ads/slots')
            ->assertOk()
            ->assertJsonPath('data.home_top.manual_image_url', 'https://example.test/updated.png');
    }

    public function test_deactivating_a_slot_busts_the_cache(): void
    {
        $slot = $this->makeSlot();
        $this->assertSame(['home_top'], $this->slotKeys());

        $this->postJson("/api/v1/admin/ad-slots/update/{$slot->id}", [
            'is_active' => false,
        ])->assertOk();

        $this->assertSame([], $this->slotKeys(), 'A deactivated slot must disappear from the public endpoint.');
    }
}
