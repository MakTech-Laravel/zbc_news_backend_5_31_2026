<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAccessibilityStatementRequest;
use App\Http\Resources\Api\V1\AccessibilityStatementResource;
use App\Services\AccessibilityStatementService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminAccessibilityStatementController extends Controller
{
    public function __construct(
        private readonly AccessibilityStatementService $accessibilityStatementService,
    ) {}

    public function show(): JsonResponse
    {
        return sendResponse(
            true,
            'Accessibility statement retrieved successfully.',
            new AccessibilityStatementResource($this->accessibilityStatementService->getOrCreate()),
            HttpStatus::HTTP_OK,
        );
    }

    public function update(UpdateAccessibilityStatementRequest $request): JsonResponse
    {
        $settings = $this->accessibilityStatementService->update($request->validated());

        return sendResponse(
            true,
            'Accessibility statement updated successfully.',
            new AccessibilityStatementResource($settings),
            HttpStatus::HTTP_OK,
        );
    }
}
