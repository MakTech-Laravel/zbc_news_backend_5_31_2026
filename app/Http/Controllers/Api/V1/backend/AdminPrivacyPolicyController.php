<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdatePrivacyPolicyRequest;
use App\Http\Resources\Api\V1\PrivacyPolicyResource;
use App\Services\PrivacyPolicyService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminPrivacyPolicyController extends Controller
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

    public function update(UpdatePrivacyPolicyRequest $request): JsonResponse
    {
        $settings = $this->privacyPolicyService->update($request->validated());

        return sendResponse(
            true,
            'Privacy policy updated successfully.',
            new PrivacyPolicyResource($settings),
            HttpStatus::HTTP_OK,
        );
    }
}
