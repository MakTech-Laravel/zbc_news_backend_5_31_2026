<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCookiePolicyRequest;
use App\Http\Resources\Api\V1\CookiePolicyResource;
use App\Services\CookiePolicyService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminCookiePolicyController extends Controller
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

    public function update(UpdateCookiePolicyRequest $request): JsonResponse
    {
        $settings = $this->cookiePolicyService->update($request->validated());

        return sendResponse(
            true,
            'Cookie policy updated successfully.',
            new CookiePolicyResource($settings),
            HttpStatus::HTTP_OK,
        );
    }
}
