<?php

namespace App\Services;

use App\Jobs\SendFailedLoginAdminEmailJob;
use App\Models\User;
use App\Support\MailSender;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class LoginSecurityAlertService
{
    public const MAX_FAILED_ATTEMPTS = 10;

    public const DECAY_SECONDS = 300;

    public function __construct(
        private readonly UserNotificationService $userNotificationService,
    ) {}

    public function recordFailure(string $email, ?string $ipAddress, ?string $userAgent): int
    {
        $email = strtolower(trim($email));
        $key = $this->key($email);

        RateLimiter::hit($key, self::DECAY_SECONDS);
        $attempts = RateLimiter::attempts($key);

        if ($attempts === self::MAX_FAILED_ATTEMPTS) {
            $alertKey = hash('sha256', $email.'|'.now()->timestamp);

            $this->userNotificationService->dispatchFailedLoginAdminNotifications(
                $email,
                $ipAddress,
                $attempts,
                $alertKey,
            );

            SendFailedLoginAdminEmailJob::dispatch(
                $email,
                $ipAddress,
                $userAgent,
                $attempts,
            );
        }

        return $attempts;
    }

    public function clear(string $email): void
    {
        RateLimiter::clear($this->key(strtolower(trim($email))));
    }

    public function sendAdminEmail(
        string $email,
        ?string $ipAddress,
        ?string $userAgent,
        int $attempts,
    ): void {
        $admins = User::query()
            ->role(['admin', 'super_admin'])
            ->get(['id', 'email', 'name']);

        if ($admins->isEmpty()) {
            return;
        }

        $siteName = MailSender::name();
        $subject = "Security alert — {$attempts} failed login attempts";
        $html = view('emails.failed-login-admin', [
            'subjectLine' => $subject,
            'siteName' => $siteName,
            'attemptedEmail' => $email,
            'ipAddress' => $ipAddress ?: 'Unknown',
            'userAgent' => $userAgent ?: 'Unknown',
            'attempts' => $attempts,
            'attemptedAt' => now()->timezone(config('app.timezone'))->toDayDateTimeString(),
        ])->render();

        foreach ($admins as $admin) {
            try {
                Mail::html($html, function ($message) use ($admin, $subject, $siteName): void {
                    $message->to((string) $admin->email, (string) $admin->name)
                        ->subject($subject)
                        ->from(MailSender::address(), $siteName);
                });
            } catch (\Throwable) {
                // Login responses must not depend on the mail transport.
            }
        }
    }

    private function key(string $email): string
    {
        return 'security:failed-login:'.hash('sha256', $email);
    }
}
