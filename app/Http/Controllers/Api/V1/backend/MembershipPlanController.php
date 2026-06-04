<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MembershipPlanRequest;
use App\Http\Resources\Api\V1\MembershipPlanResource;
use App\Services\MembershipPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class MembershipPlanController extends Controller
{
    public function __construct(
        private MembershipPlanService $membershipPlanService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $plans = $this->membershipPlanService->getAll();

        return sendResponse(
            true,
            'Plans retrieved successfully.',
            MembershipPlanResource::collection($plans),
            HttpStatus::HTTP_OK,
        );
    }

    public function trashed(): JsonResponse
    {
        $plans = $this->membershipPlanService->getTrashed();

        return sendResponse(
            true,
            'Trashed plans retrieved successfully.',
            MembershipPlanResource::collection($plans),
            HttpStatus::HTTP_OK,
        );
    }

    public function show(int $id): JsonResponse
    {
        try {
            $plan = $this->membershipPlanService->findById($id);

            return sendResponse(
                true,
                'Plan retrieved successfully.',
                new MembershipPlanResource($plan),
                HttpStatus::HTTP_OK,
            );
        } catch (\Exception $e) {
            return sendResponse(
                false,
                'Plan not found.',
                null,
                HttpStatus::HTTP_NOT_FOUND,
            );
        }
    }

    public function store(MembershipPlanRequest $request): JsonResponse
    {
        try {
            $plan = $this->membershipPlanService->create($request->validated());

            return sendResponse(
                true,
                'Plan created successfully.',
                new MembershipPlanResource($plan),
                HttpStatus::HTTP_CREATED,
            );
        } catch (\Exception $e) {
            return sendResponse(
                false,
                'Plan creation failed.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    public function update(MembershipPlanRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $plan = $this->membershipPlanService->update($validatedData, $id);

            return sendResponse(
                true,
                'Plan updated successfully.',
                new MembershipPlanResource($plan),
                HttpStatus::HTTP_OK,
            );
        } catch (\Exception $e) {
            return sendResponse(
                false,
                'Plan update failed.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->membershipPlanService->delete($id);

            return sendResponse(
                true,
                'Plan deleted successfully.',
                null,
                HttpStatus::HTTP_OK,
            );
        } catch (\Exception $e) {
            return sendResponse(
                false,
                $e->getMessage(),
                null,
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    public function restore(int $id): JsonResponse
    {
        try {
            $plan = $this->membershipPlanService->restore($id);

            return sendResponse(
                true,
                'Plan restored successfully.',
                new MembershipPlanResource($plan),
                HttpStatus::HTTP_OK,
            );
        } catch (\Exception $e) {
            return sendResponse(
                false,
                'Plan restore failed.',
                null,
                HttpStatus::HTTP_NOT_FOUND,
            );
        }
    }

    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->membershipPlanService->permanentDelete($id);

            return sendResponse(
                true,
                'Plan permanently deleted.',
                null,
                HttpStatus::HTTP_OK,
            );
        } catch (\Exception $e) {
            return sendResponse(
                false,
                'Plan permanent delete failed.',
                null,
                HttpStatus::HTTP_NOT_FOUND,
            );
        }
    }
}