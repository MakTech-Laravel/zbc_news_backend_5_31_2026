<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $service
    ) {}

    /**
     * GET /api/v1/admin/dashboard/overview
     */
    public function overview(): JsonResponse
    {
        $data = $this->service->getOverview();

        return sendResponse(
            true,
            'Dashboard overview fetched successfully',
            $data,
            HttpStatus::HTTP_OK
        );
    }
}
