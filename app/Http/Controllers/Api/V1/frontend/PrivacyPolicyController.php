<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PrivacyPolicyResource;
use App\Services\PrivacyPolicyService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class PrivacyPolicyController extends Controller
{
    public function __construct(
        private readonly PrivacyPolicyService $privacyPolicyService,
    ) {}

    public function show(): JsonResponse
    {
        return sendResponse(
            true,
            'Privacy policy retrieved successfully.',
            new PrivacyPolicyResource($this->privacyPolicyService->getOrCreate()),
            HttpStatus::HTTP_OK,
        );
    }
}
