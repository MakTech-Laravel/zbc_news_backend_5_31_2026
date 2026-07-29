<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TermsOfServiceResource;
use App\Services\TermsOfServiceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class TermsOfServiceController extends Controller
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
}
