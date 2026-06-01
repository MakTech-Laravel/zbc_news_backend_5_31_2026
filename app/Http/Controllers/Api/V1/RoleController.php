<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RoleRequest;
use App\Http\Resources\Api\V1\RoleResource;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService
    ) {}
    public function index()
    {

        $roles = $this->roleService->getAllRoles();

        return sendResponse(
            true,
            'Roles retrieved successfully',
            new RoleResource($roles),
            HttpStatus::HTTP_OK,
        );
    }

    public function store(RoleRequest $request)
    {
        $validate = $request->validated();
        $role = $this->roleService->create($validate);


        return sendResponse(
            true,
            'Role created successfully',
            new RoleResource($role),
            HttpStatus::HTTP_CREATED,
        );
    }

    public function update(RoleRequest $request, $id)
    {
         $role = $this->roleService->getById($id);

        $validated = $request->validated();

        $updated = $this->roleService->update($role, $validated);

        return sendResponse(
            true,
            'Role updated successfully',
            new RoleResource($updated),
            HttpStatus::HTTP_OK,
        );
    }

    public function show(int $id)
    {
        $role = $this->roleService->getById($id);

        return sendResponse(
            true,
            'Role retrieved successfully',
            new RoleResource($role),
            HttpStatus::HTTP_OK,
        );
    }
}
