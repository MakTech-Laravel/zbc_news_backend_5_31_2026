<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCareersPageRequest;
use App\Http\Resources\Api\V1\CareersPageResource;
use App\Services\CareersPageService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminCareersPageController extends Controller
{
    public function __construct(
        private readonly CareersPageService $pageService,
    ) {}

    public function show(): JsonResponse
    {
        return sendResponse(
            true,
            'Careers page retrieved successfully.',
            new CareersPageResource($this->pageService->getOrCreate()),
            HttpStatus::HTTP_OK,
        );
    }

    public function update(UpdateCareersPageRequest $request): JsonResponse
    {
        $settings = $this->pageService->update($request->validated());

        return sendResponse(
            true,
            'Careers page updated successfully.',
            new CareersPageResource($settings),
            HttpStatus::HTTP_OK,
        );
    }
}
