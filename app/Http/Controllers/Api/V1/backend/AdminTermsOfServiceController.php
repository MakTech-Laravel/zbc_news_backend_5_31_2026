<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTermsOfServiceRequest;
use App\Http\Resources\Api\V1\TermsOfServiceResource;
use App\Services\TermsOfServiceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminTermsOfServiceController extends Controller
{
    public function __construct(
        private readonly TermsOfServiceService $termsOfServiceService,
    ) {}

    public function show(): JsonResponse
    {
        return sendResponse(
            true,
            'Terms of service retrieved successfully.',
            new TermsOfServiceResource($this->termsOfServiceService->getOrCreate()),
            HttpStatus::HTTP_OK,
        );
    }

    public function update(UpdateTermsOfServiceRequest $request): JsonResponse
    {
        $settings = $this->termsOfServiceService->update($request->validated());

        return sendResponse(
            true,
            'Terms of service updated successfully.',
            new TermsOfServiceResource($settings),
            HttpStatus::HTTP_OK,
        );
    }
}
