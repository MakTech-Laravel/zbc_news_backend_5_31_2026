<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserNotificationResource;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class UserNotificationController extends Controller
{
    public function __construct(
        private readonly UserNotificationService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $notifications = $this->service->listForUser($request->user(), is_string($category) ? $category : null);

        return sendResponse(
            true,
            'Notifications retrieved successfully.',
            UserNotificationResource::collection($notifications),
            HttpStatus::HTTP_OK,
            [
                'unread_count' => $this->service->unreadCount($request->user()),
            ],
        );
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = $this->service->markAsRead($request->user(), $id);

        if (! $notification) {
            return sendResponse(false, 'Notification not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(
            true,
            'Notification marked as read.',
            new UserNotificationResource($notification),
            HttpStatus::HTTP_OK,
            [
                'unread_count' => $this->service->unreadCount($request->user()),
            ],
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->service->markAllAsRead($request->user());

        return sendResponse(
            true,
            'All notifications marked as read.',
            ['updated' => $updated],
            HttpStatus::HTTP_OK,
            [
                'unread_count' => 0,
            ],
        );
    }
}
