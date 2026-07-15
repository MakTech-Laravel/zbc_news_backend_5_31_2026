<?php

namespace Tests\Feature\Navigation;

use App\Models\NavigationLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The public quick-links endpoint is cached on a long TTL because it is admin-edited and
 * changes rarely. Every NavigationLink write path must bust it — store, update and destroy.
 */
class QuickLinksCacheTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['navigation.list', 'navigation.create', 'navigation.update', 'navigation.delete'] as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['group_name' => 'Navigation'],
            );
        }

        $role = Role::query()->firstOrCreate(['name' => 'editor', 'guard_name' => 'api']);
        $role->givePermissionTo(['navigation.list', 'navigation.create', 'navigation.update', 'navigation.delete']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('editor');
        $this->admin->givePermissionTo(['navigation.list', 'navigation.create', 'navigation.update', 'navigation.delete']);

        Passport::actingAs($this->admin);
    }

    private function makeLink(array $overrides = []): NavigationLink
    {
        return NavigationLink::query()->create(array_merge([
            'location' => 'home_quick_links',
            'label' => 'Politics',
            'url' => '/politics',
            'sort_order' => 1,
            'is_active' => true,
        ], $overrides));
    }

    private function quickLinkLabels(): array
    {
        $response = $this->getJson('/api/v1/navigation/quick-links')->assertOk();

        return collect($response->json('data'))->pluck('label')->all();
    }

    public function test_repeat_request_is_served_from_cache(): void
    {
        $this->makeLink();

        $this->getJson('/api/v1/navigation/quick-links')->assertOk();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->getJson('/api/v1/navigation/quick-links')->assertOk();

        $this->assertSame(0, $queries, 'Warm cache must not re-query the database.');
    }

    public function test_store_busts_the_cache(): void
    {
        $this->makeLink();
        $this->assertSame(['Politics'], $this->quickLinkLabels());

        $this->postJson('/api/v1/admin/navigation-links/store', [
            'location' => 'home_quick_links',
            'label' => 'Sport',
            'url' => '/sport',
            'sort_order' => 2,
            'is_active' => true,
        ])->assertCreated();

        $this->assertSame(['Politics', 'Sport'], $this->quickLinkLabels());
    }

    public function test_update_busts_the_cache(): void
    {
        $link = $this->makeLink();
        $this->assertSame(['Politics'], $this->quickLinkLabels());

        $this->postJson("/api/v1/admin/navigation-links/update/{$link->id}", [
            'label' => 'World',
        ])->assertOk();

        $this->assertSame(['World'], $this->quickLinkLabels());
    }

    public function test_deactivating_a_link_busts_the_cache(): void
    {
        $link = $this->makeLink();
        $this->assertSame(['Politics'], $this->quickLinkLabels());

        $this->postJson("/api/v1/admin/navigation-links/update/{$link->id}", [
            'is_active' => false,
        ])->assertOk();

        $this->assertSame([], $this->quickLinkLabels(), 'A deactivated link must disappear from the public endpoint.');
    }

    public function test_destroy_busts_the_cache(): void
    {
        $link = $this->makeLink();
        $this->assertSame(['Politics'], $this->quickLinkLabels());

        $this->deleteJson("/api/v1/admin/navigation-links/delete/{$link->id}")->assertOk();

        $this->assertSame([], $this->quickLinkLabels());
    }
}
