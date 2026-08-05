<?php

namespace App\Listeners;

use App\Services\ScheduledTaskFailureService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordFailedQueueJob
{
    public function __construct(
        private readonly ScheduledTaskFailureService $scheduledTaskFailureService,
    ) {}

    public function handle(JobFailed $event): void
    {
        try {
            $this->scheduledTaskFailureService->recordQueueJobFailure(
                $event->job->resolveName(),
                $event->exception,
                $event->job->uuid(),
                $event->connectionName,
            );
        } catch (Throwable $exception) {
            // A broken failure recorder must never mask the original job failure.
            Log::error('Failed to record queue job failure', [
                'job' => $event->job->resolveName(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
