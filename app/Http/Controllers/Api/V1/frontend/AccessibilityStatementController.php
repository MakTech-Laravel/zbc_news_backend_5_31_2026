<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AccessibilityStatementResource;
use App\Services\AccessibilityStatementService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AccessibilityStatementController extends Controller
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
}
