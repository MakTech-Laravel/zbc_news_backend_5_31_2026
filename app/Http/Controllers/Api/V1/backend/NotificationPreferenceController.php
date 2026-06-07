<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\NotificationPreferenceRequest;
use App\Http\Resources\Api\V1\NotificationPreferenceResource;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;


class NotificationPreferenceController extends Controller
{
   public function __construct(
        private readonly NotificationPreferenceService $service
    ) {}



    public function show(): JsonResponse
    {
        $preference = $this->service->getOrCreate(request()->user());

        return sendResponse(
            true,
            'Preference retrieved successfully.',
            new NotificationPreferenceResource($preference),
            HttpStatus::HTTP_OK,
        );
    }


    public function update(NotificationPreferenceRequest $request): JsonResponse
    {
        $preference = $this->service->update(
            $request->user(),
            $request->validated()
        );

        return sendResponse(
            true,
            'Notification preferences updated successfully.',
            new NotificationPreferenceResource($preference),
            HttpStatus::HTTP_OK,
        );
    }
}
