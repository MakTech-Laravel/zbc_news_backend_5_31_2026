<?php

namespace Tests\Unit;

use App\Jobs\SendFailedLoginAdminEmailJob;
use App\Services\AdminNotificationPreferenceService;
use App\Services\LoginSecurityAlertService;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class LoginSecurityAlertServiceTest extends TestCase
{
    public function test_it_alerts_admins_only_when_tenth_failed_attempt_is_reached(): void
    {
        Queue::fake();

        $notifications = Mockery::mock(UserNotificationService::class);
        $notifications->shouldReceive('dispatchFailedLoginAdminNotifications')
            ->once()
            ->with(
                'person@example.com',
                '203.0.113.10',
                LoginSecurityAlertService::MAX_FAILED_ATTEMPTS,
                Mockery::type('string'),
            )
            ->andReturn(1);

        $preferences = Mockery::mock(AdminNotificationPreferenceService::class);
        $service = new LoginSecurityAlertService($preferences, $notifications);
        $service->clear('person@example.com');

        for ($attempt = 1; $attempt <= LoginSecurityAlertService::MAX_FAILED_ATTEMPTS; $attempt++) {
            $this->assertSame(
                $attempt,
                $service->recordFailure(
                    ' Person@Example.com ',
                    '203.0.113.10',
                    'Test browser',
                ),
            );
        }

        Queue::assertPushed(SendFailedLoginAdminEmailJob::class, 1);
    }

    public function test_successful_login_clear_restarts_the_counter(): void
    {
        Queue::fake();

        $notifications = Mockery::mock(UserNotificationService::class);
        $notifications->shouldNotReceive('dispatchFailedLoginAdminNotifications');

        $preferences = Mockery::mock(AdminNotificationPreferenceService::class);
        $service = new LoginSecurityAlertService($preferences, $notifications);
        $service->clear('person@example.com');

        $this->assertSame(1, $service->recordFailure('person@example.com', null, null));
        $this->assertSame(2, $service->recordFailure('person@example.com', null, null));

        $service->clear('person@example.com');

        $this->assertSame(1, $service->recordFailure('person@example.com', null, null));
        Queue::assertNothingPushed();
    }
}
