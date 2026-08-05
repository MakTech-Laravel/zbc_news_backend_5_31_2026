<?php

namespace App\Jobs;

use App\Services\LoginSecurityAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendFailedLoginAdminEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $email,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly int $attempts,
    ) {}

    public function handle(LoginSecurityAlertService $service): void
    {
        try {
            $service->sendAdminEmail(
                $this->email,
                $this->ipAddress,
                $this->userAgent,
                $this->attempts,
            );
        } catch (\Throwable $exception) {
            Log::warning('Failed login admin email failed.', [
                'attempted_email' => $this->email,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
