<?php

namespace App\Jobs;

use App\Models\CareerApplication;
use App\Services\CareerApplicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendCareerApplicationAdminEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $applicationId,
    ) {}

    public function handle(CareerApplicationService $careerApplicationService): void
    {
        $application = CareerApplication::query()
            ->with('job')
            ->find($this->applicationId);

        if (! $application) {
            return;
        }

        try {
            $careerApplicationService->sendAdminEmail($application);
        } catch (\Throwable $exception) {
            Log::warning('Career application admin email failed.', [
                'application_id' => $this->applicationId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
