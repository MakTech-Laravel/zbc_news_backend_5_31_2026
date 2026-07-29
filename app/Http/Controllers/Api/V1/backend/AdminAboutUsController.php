<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAboutUsRequest;
use App\Http\Resources\Api\V1\AboutUsResource;
use App\Services\AboutUsService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminAboutUsController extends Controller
{
    public function __construct(
        private readonly AboutUsService $aboutUsService,
    ) {}

    public function show(): JsonResponse
    {
        return sendResponse(
            true,
            'About us retrieved successfully.',
            new AboutUsResource($this->aboutUsService->getOrCreate()),
            HttpStatus::HTTP_OK,
        );
    }

    public function update(UpdateAboutUsRequest $request): JsonResponse
    {
        $settings = $this->aboutUsService->update($request->validated());

        return sendResponse(
            true,
            'About us updated successfully.',
            new AboutUsResource($settings),
            HttpStatus::HTTP_OK,
        );
    }
}
