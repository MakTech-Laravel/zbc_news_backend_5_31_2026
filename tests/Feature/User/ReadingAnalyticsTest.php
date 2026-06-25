<?php

namespace Tests\Feature\User;

use App\Models\User;
use App\Services\ArticleTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReadingAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_analytics_requires_users_reading_analytics_permission(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->getJson('/api/v1/admin/users/reading-analytics')->assertForbidden();
    }

    public function test_user_with_permission_can_access_reading_analytics_route(): void
    {
        Role::query()->firstOrCreate(['name' => 'user', 'guard_name' => 'api']);

        Permission::query()->firstOrCreate(
            ['name' => 'users.reading-analytics', 'guard_name' => 'api'],
            ['group_name' => 'Users'],
        );

        Role::findByName('user', 'api')->givePermissionTo('users.reading-analytics');

        $user = User::factory()->create();
        $user->assignRole('user');
        Passport::actingAs($user);

        $this->mock(ArticleTrackingService::class, function ($mock): void {
            $mock->shouldReceive('getUserReadingAnalytics')->once()->andReturn([
                'stats' => [
                    'articles_this_week' => 0,
                    'reading_time_this_week' => 0,
                    'avg_per_day' => 0,
                    'completion_rate' => 0,
                ],
                'weekly_activity' => [],
                'by_category' => [],
                'monthly_trend' => [],
                'most_engaged' => [],
            ]);
        });

        $this->getJson('/api/v1/admin/users/reading-analytics')
            ->assertSuccessful()
            ->assertJsonPath('success', true);
    }
}
