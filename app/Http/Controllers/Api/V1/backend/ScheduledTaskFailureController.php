<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Models\ScheduledTaskFailure;
use App\Services\ScheduledTaskFailureService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ScheduledTaskFailureController extends Controller
{
    public function __construct(
        private readonly ScheduledTaskFailureService $scheduledTaskFailureService,
    ) {}

    public function index(Request $request)
    {
        $status = $request->query('status');
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        $paginator = $this->scheduledTaskFailureService->list(
            is_string($status) ? $status : null,
            $perPage,
        );

        $data = collect($paginator->items())->map(fn (ScheduledTaskFailure $failure) => [
            'id' => $failure->id,
            'task_key' => $failure->task_key,
            'task_name' => $failure->task_name,
            'task_type' => $failure->task_type,
            'exception_message' => $failure->exception_message,
            'status' => $failure->status,
            'occurrence_count' => $failure->occurrence_count,
            'failed_at' => optional($failure->failed_at)?->toIso8601String(),
            'resolved_at' => optional($failure->resolved_at)?->toIso8601String(),
            'can_rerun' => $this->scheduledTaskFailureService->canRerun($failure),
        ])->values();

        return sendResponse(true, 'Scheduled task failures retrieved successfully.', [
            'items' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], HttpStatus::HTTP_OK);
    }

    public function rerun(Request $request, int $id)
    {
        $failure = ScheduledTaskFailure::query()->findOrFail($id);

        try {
            $result = $this->scheduledTaskFailureService->rerun($failure, $request->user());
        } catch (\InvalidArgumentException $exception) {
            return sendResponse(false, $exception->getMessage(), null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            report($exception);

            return sendResponse(
                false,
                'Re-run failed: '.$exception->getMessage(),
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return sendResponse(true, 'Scheduled task re-run completed.', [
            'id' => $result['failure']->id,
            'status' => $result['failure']->status,
            'output' => $result['output'],
        ], HttpStatus::HTTP_OK);
    }

    public function resolve(Request $request, int $id)
    {
        $failure = ScheduledTaskFailure::query()->findOrFail($id);
        $resolved = $this->scheduledTaskFailureService->markResolved($failure, $request->user());

        return sendResponse(true, 'Scheduled task failure marked as resolved.', [
            'id' => $resolved->id,
            'status' => $resolved->status,
        ], HttpStatus::HTTP_OK);
    }
}
