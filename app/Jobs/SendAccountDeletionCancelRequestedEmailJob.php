<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendAccountDeletionCancelRequestedEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $userId,
    ) {}

    public function handle(AccountDeletionService $accountDeletionService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user || ! $user->hasDeletionCancelRequest()) {
            return;
        }

        try {
            $accountDeletionService->sendDeletionCancelRequestedEmail($user);
        } catch (\Throwable $exception) {
            Log::warning('Account deletion cancel confirmation email failed.', [
                'user_id' => $this->userId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
