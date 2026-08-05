<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendScheduledTaskFailedAdminEmailJob;
use App\Models\ScheduledTaskFailure;
use App\Models\User;
use App\Services\ScheduledTaskFailureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduledTaskFailureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'super_admin'] as $role) {
            Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }

        foreach (['scheduled-tasks.list', 'scheduled-tasks.rerun'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'api'],
                ['group_name' => 'Scheduled Tasks'],
            );
        }

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('super_admin');
        $this->admin->givePermissionTo(['scheduled-tasks.list', 'scheduled-tasks.rerun']);
    }

    public function test_records_failure_and_dispatches_admin_email_job(): void
    {
        Bus::fake([SendScheduledTaskFailedAdminEmailJob::class]);

        $failure = app(ScheduledTaskFailureService::class)->recordFailure(
            'publish-scheduled-articles',
            new \RuntimeException('Simulated schedule failure'),
        );

        $this->assertNotNull($failure);
        $this->assertDatabaseHas('scheduled_task_failures', [
            'task_key' => 'publish-scheduled-articles',
            'status' => 'failed',
        ]);

        Bus::assertDispatched(SendScheduledTaskFailedAdminEmailJob::class);
    }

    public function test_admin_can_list_and_resolve_failures(): void
    {
        ScheduledTaskFailure::query()->create([
            'task_key' => 'sitemap-refresh',
            'task_name' => 'Sitemap refresh',
            'task_type' => 'command',
            'exception_message' => 'boom',
            'status' => 'failed',
            'occurrence_count' => 1,
            'failed_at' => now(),
            'last_notified_at' => now(),
        ]);

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/scheduled-task-failures?status=failed')
            ->assertOk()
            ->assertJsonPath('success', true);

        $id = ScheduledTaskFailure::query()->value('id');

        $this->postJson('/api/v1/admin/scheduled-task-failures/'.$id.'/resolve')
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
    }
}
