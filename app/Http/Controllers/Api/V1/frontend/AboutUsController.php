<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AboutUsResource;
use App\Services\AboutUsService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AboutUsController extends Controller
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
}
