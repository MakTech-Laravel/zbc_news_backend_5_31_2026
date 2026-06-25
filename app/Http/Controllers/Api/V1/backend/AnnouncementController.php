<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AnnouncementRequest;
use App\Http\Resources\Api\V1\AnnouncementResource;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $service,
    ) {}

    public function index(): JsonResponse
    {
        return sendResponse(
            true,
            'Announcements retrieved successfully.',
            AnnouncementResource::collection($this->service->list()),
            HttpStatus::HTTP_OK,
        );
    }

    public function store(AnnouncementRequest $request): JsonResponse
    {
        $announcement = $this->service->create(
            $request->user(),
            $request->validated(),
        );

        return sendResponse(
            true,
            'Announcement created successfully.',
            new AnnouncementResource($announcement),
            HttpStatus::HTTP_CREATED,
        );
    }

    public function show(int $id): JsonResponse
    {
        return sendResponse(
            true,
            'Announcement retrieved successfully.',
            new AnnouncementResource($this->service->findOrFail($id)),
            HttpStatus::HTTP_OK,
        );
    }

    public function update(AnnouncementRequest $request, int $id): JsonResponse
    {
        $announcement = $this->service->findOrFail($id);

        if ($announcement->status === 'published') {
            return sendResponse(
                false,
                'Published announcements cannot be edited.',
                null,
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $updated = $this->service->update($announcement, $request->validated());

        return sendResponse(
            true,
            'Announcement updated successfully.',
            new AnnouncementResource($updated),
            HttpStatus::HTTP_OK,
        );
    }

    public function publish(int $id): JsonResponse
    {
        $announcement = $this->service->findOrFail($id);

        if ($announcement->status === 'published') {
            return sendResponse(
                false,
                'Announcement is already published.',
                new AnnouncementResource($announcement),
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $published = $this->service->publish($announcement);

        return sendResponse(
            true,
            'Announcement published successfully.',
            new AnnouncementResource($published),
            HttpStatus::HTTP_OK,
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $announcement = $this->service->findOrFail($id);
        $this->service->delete($announcement);

        return sendResponse(
            true,
            'Announcement deleted successfully.',
            null,
            HttpStatus::HTTP_OK,
        );
    }
}
