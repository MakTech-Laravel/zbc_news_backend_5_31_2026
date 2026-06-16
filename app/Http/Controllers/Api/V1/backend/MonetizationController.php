<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Services\MonetizationAnalyticsService;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class MonetizationController extends Controller
{
    public function __construct(
        private readonly MonetizationAnalyticsService $analyticsService,
    ) {}

    public function overview()
    {
        $data = $this->analyticsService->getOverview();

        return sendResponse(
            true,
            'Monetization overview retrieved successfully',
            $data,
            HttpStatus::HTTP_OK,
        );
    }
}
