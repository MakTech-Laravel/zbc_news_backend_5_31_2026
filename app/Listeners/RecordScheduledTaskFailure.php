<?php

namespace App\Listeners;

use App\Services\ScheduledTaskFailureService;
use Illuminate\Console\Events\ScheduledTaskFailed;

class RecordScheduledTaskFailure
{
    public function __construct(
        private readonly ScheduledTaskFailureService $scheduledTaskFailureService,
    ) {}

    public function handle(ScheduledTaskFailed $event): void
    {
        $name = $event->task->description
            ?: $event->task->getSummaryForDisplay();

        $this->scheduledTaskFailureService->recordFailureFromScheduleName(
            is_string($name) ? $name : null,
            $event->exception,
        );
    }
}
