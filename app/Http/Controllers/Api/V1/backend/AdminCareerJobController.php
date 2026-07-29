<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CareerJobRequest;
use App\Http\Resources\Api\V1\CareerJobResource;
use App\Services\CareerJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminCareerJobController extends Controller
{
    public function __construct(
        private readonly CareerJobService $jobService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->jobService->adminList(
            $request->query('status'),
            $request->query('department'),
            $request->query('search'),
            $request->boolean('trashed'),
            max(1, min((int) $request->query('per_page', 15), 50)),
        );

        return sendResponse(
            true,
            'Career jobs retrieved successfully.',
            CareerJobResource::collection($paginator),
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

    public function store(CareerJobRequest $request): JsonResponse
    {
        $job = $this->jobService->create($request->validated());

        return sendResponse(
            true,
            'Career job created successfully.',
            new CareerJobResource($job),
            HttpStatus::HTTP_CREATED,
        );
    }

    public function show(int $id): JsonResponse
    {
        return sendResponse(
            true,
            'Career job retrieved successfully.',
            new CareerJobResource($this->jobService->findOrFail($id, true)->loadCount('applications')),
            HttpStatus::HTTP_OK,
        );
    }

    public function update(CareerJobRequest $request, int $id): JsonResponse
    {
        $job = $this->jobService->update(
            $this->jobService->findOrFail($id),
            $request->validated(),
        );

        return sendResponse(
            true,
            'Career job updated successfully.',
            new CareerJobResource($job),
            HttpStatus::HTTP_OK,
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->jobService->delete($this->jobService->findOrFail($id));

        return sendResponse(
            true,
            'Career job deleted successfully.',
            null,
            HttpStatus::HTTP_OK,
        );
    }

    public function restore(int $id): JsonResponse
    {
        return sendResponse(
            true,
            'Career job restored successfully.',
            new CareerJobResource($this->jobService->restore($id)),
            HttpStatus::HTTP_OK,
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $this->jobService->forceDelete($id);

        return sendResponse(
            true,
            'Career job permanently deleted.',
            null,
            HttpStatus::HTTP_OK,
        );
    }
}
