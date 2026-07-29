<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCareerApplicationRequest;
use App\Http\Resources\Api\V1\CareerApplicationResource;
use App\Http\Resources\Api\V1\CareerJobResource;
use App\Http\Resources\Api\V1\CareersPageResource;
use App\Services\CareerApplicationService;
use App\Services\CareerJobService;
use App\Services\CareersPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class CareersController extends Controller
{
    public function __construct(
        private readonly CareersPageService $pageService,
        private readonly CareerJobService $jobService,
        private readonly CareerApplicationService $applicationService,
    ) {}

    public function page(): JsonResponse
    {
        return sendResponse(
            true,
            'Careers page retrieved successfully.',
            new CareersPageResource($this->pageService->getOrCreate()),
            HttpStatus::HTTP_OK,
        );
    }

    public function jobs(Request $request): JsonResponse
    {
        $jobs = $this->jobService->publicList(
            $request->query('q'),
            $request->query('department'),
            $request->query('type'),
        );

        return sendResponse(
            true,
            'Career jobs retrieved successfully.',
            CareerJobResource::collection($jobs),
            HttpStatus::HTTP_OK,
        );
    }

    public function apply(StoreCareerApplicationRequest $request): JsonResponse
    {
        try {
            $application = $this->applicationService->store(
                $request->validated(),
                $request->file('resume'),
                $request,
            );
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return sendResponse(
                false,
                'Unable to submit your application right now. Please try again later.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return sendResponse(
            true,
            'Your application has been submitted successfully.',
            new CareerApplicationResource($application->load('job')),
            HttpStatus::HTTP_CREATED,
        );
    }
}
