<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Services\UserDashboardService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class UserDashboardController extends Controller
{
    public function __construct(
        private readonly UserDashboardService $service
    ) {}

    /**
     * GET /api/v1/admin/user/dashboard
     * Authenticated user dashboard data
     */
    public function index(): JsonResponse
    {
        $userId = auth('api')->id();

        $data = $this->service->getDashboard($userId);

        return sendResponse(
            true,
            'User dashboard fetched successfully',
            $data,
            HttpStatus::HTTP_OK
        );
    }
}
