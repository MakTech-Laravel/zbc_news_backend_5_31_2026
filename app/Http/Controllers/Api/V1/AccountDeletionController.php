<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AccountDeletionController extends Controller
{
    public function __construct(
        private readonly AccountDeletionService $accountDeletionService,
    ) {}

    public function requestDeletion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'confirm' => ['required', 'accepted'],
        ], [
            'confirm.accepted' => 'You must confirm that you understand the account will be permanently deleted after the grace period.',
        ]);

        try {
            $result = $this->accountDeletionService->requestDeletion(
                $request->user(),
                (string) $validated['password'],
            );
        } catch (\InvalidArgumentException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'password')
                ? HttpStatus::HTTP_UNPROCESSABLE_ENTITY
                : HttpStatus::HTTP_FORBIDDEN;

            return sendResponse(false, $exception->getMessage(), null, $status);
        }

        return sendResponse(
            true,
            'Account deletion requested. You have been signed out. Check your email for cancellation instructions.',
            [
                'scheduled_permanent_deletion_at' => $result['scheduled_permanent_deletion_at'],
                'grace_days' => AccountDeletionService::GRACE_DAYS,
            ],
            HttpStatus::HTTP_OK,
        );
    }

    public function cancelDeletion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:32', 'max:128'],
        ]);

        try {
            $user = $this->accountDeletionService->requestCancelDeletion((string) $validated['token']);
        } catch (\InvalidArgumentException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                null,
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return sendResponse(
            true,
            'Your cancellation request was sent to an administrator for review. Your account stays disabled until an admin restores it.',
            [
                'email' => $user->email,
                'deletion_cancel_requested_at' => optional($user->deletion_cancel_requested_at)?->toIso8601String(),
            ],
            HttpStatus::HTTP_OK,
        );
    }
}
