<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService
    ) {}

    public function index()
    {
        $permissions = $this->permissionService->getAllPermissions();
        return sendResponse(
            true,
            'Permissions retrieved successfully',
            $permissions,
            HttpStatus::HTTP_OK,
        );
    }
}
