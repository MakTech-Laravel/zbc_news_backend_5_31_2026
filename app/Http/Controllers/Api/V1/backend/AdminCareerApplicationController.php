<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkCareerApplicationRequest;
use App\Http\Requests\Api\V1\UpdateCareerApplicationStatusRequest;
use App\Http\Resources\Api\V1\CareerApplicationResource;
use App\Models\CareerApplication;
use App\Services\CareerApplicationExportService;
use App\Services\CareerApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCareerApplicationController extends Controller
{
    public function __construct(
        private readonly CareerApplicationService $applicationService,
        private readonly CareerApplicationExportService $exportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $jobId = $request->query('career_job_id');
        $paginator = $this->applicationService->adminList(
            $request->query('status'),
            $request->query('search'),
            $jobId !== null && $jobId !== '' ? (int) $jobId : null,
            max(1, min((int) $request->query('per_page', 15), 50)),
        );

        return sendResponse(
            true,
            'Career applications retrieved successfully.',
            CareerApplicationResource::collection($paginator),
            HttpStatus::HTTP_OK,
            [
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        );
    }

    public function show(int $id): JsonResponse
    {
        $application = $this->applicationService->showAndMarkReviewed($id);

        return sendResponse(
            true,
            'Career application retrieved successfully.',
            new CareerApplicationResource($application),
            HttpStatus::HTTP_OK,
        );
    }

    public function updateStatus(UpdateCareerApplicationStatusRequest $request, int $id): JsonResponse
    {
        $application = CareerApplication::query()->findOrFail($id);

        try {
            $application = $this->applicationService->updateStatus(
                $application,
                $request->validated('status'),
            );
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return sendResponse(
            true,
            'Application status updated successfully.',
            new CareerApplicationResource($application),
            HttpStatus::HTTP_OK,
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $application = CareerApplication::query()->findOrFail($id);
        $this->applicationService->delete($application);

        return sendResponse(
            true,
            'Career application deleted successfully.',
            null,
            HttpStatus::HTTP_OK,
        );
    }

    public function bulk(BulkCareerApplicationRequest $request): JsonResponse
    {
        try {
            $count = $this->applicationService->bulkAction(
                $request->validated('action'),
                $request->validated('ids'),
            );
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return sendResponse(
            true,
            "{$count} application(s) updated successfully.",
            ['count' => $count],
            HttpStatus::HTTP_OK,
        );
    }

    public function export(Request $request)
    {
        $jobId = $request->query('career_job_id');
        $applications = $this->applicationService->exportList(
            $request->query('status'),
            $request->query('search'),
            $jobId !== null && $jobId !== '' ? (int) $jobId : null,
        );

        return $this->exportService->toCsv($applications);
    }

    public function downloadResume(int $id): StreamedResponse
    {
        $application = CareerApplication::query()->findOrFail($id);

        return $this->applicationService->downloadResume($application);
    }
}
