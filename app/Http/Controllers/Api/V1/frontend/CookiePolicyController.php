<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CookiePolicyResource;
use App\Services\CookiePolicyService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class CookiePolicyController extends Controller
{
    public function __construct(
        private readonly CookiePolicyService $cookiePolicyService,
    ) {}

    public function show(): JsonResponse
    {
        return sendResponse(
            true,
            'Cookie policy retrieved successfully.',
            new CookiePolicyResource($this->cookiePolicyService->getOrCreate()),
            HttpStatus::HTTP_OK,
        );
    }
}
