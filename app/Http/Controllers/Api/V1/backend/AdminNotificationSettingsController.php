<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminNotificationSettingsRequest;
use App\Services\AdminNotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminNotificationSettingsController extends Controller
{
    public function __construct(
        private readonly AdminNotificationPreferenceService $preferences,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->hasAnyRole(['admin', 'super_admin']),
            HttpStatus::HTTP_FORBIDDEN,
        );

        return sendResponse(
            true,
            'Admin notification settings retrieved successfully.',
            ['settings' => $this->preferences->all()],
            HttpStatus::HTTP_OK,
        );
    }

    public function update(AdminNotificationSettingsRequest $request): JsonResponse
    {
        $settings = $this->preferences->update($request->validated('settings'));

        return sendResponse(
            true,
            'Admin notification settings updated successfully.',
            ['settings' => $settings],
            HttpStatus::HTTP_OK,
        );
    }
}
