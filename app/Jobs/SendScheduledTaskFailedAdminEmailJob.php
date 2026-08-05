<?php

namespace App\Jobs;

use App\Models\ScheduledTaskFailure;
use App\Services\ScheduledTaskFailureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendScheduledTaskFailedAdminEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $failureId,
    ) {}

    public function handle(ScheduledTaskFailureService $service): void
    {
        $failure = ScheduledTaskFailure::query()->find($this->failureId);

        if (! $failure) {
            return;
        }

        try {
            $service->sendAdminEmail($failure);
        } catch (\Throwable $exception) {
            Log::warning('Scheduled task failed admin email failed.', [
                'failure_id' => $this->failureId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
