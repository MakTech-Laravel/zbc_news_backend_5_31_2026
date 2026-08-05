<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendAccountRestoredEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $userId,
    ) {}

    public function handle(AccountDeletionService $accountDeletionService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user || $user->isPendingDeletion() || $user->isPermanentlyDeleted()) {
            return;
        }

        try {
            $accountDeletionService->sendAccountRestoredEmail($user);
        } catch (\Throwable $exception) {
            Log::warning('Account restored email failed.', [
                'user_id' => $this->userId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
