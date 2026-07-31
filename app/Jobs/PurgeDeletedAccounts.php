<?php

namespace App\Jobs;

use App\Services\AccountDeletionService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PurgeDeletedAccounts
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AccountDeletionService $accountDeletionService): void
    {
        $count = $accountDeletionService->purgeDueAccounts();

        if ($count > 0) {
            Log::info('Purged accounts past deletion grace period.', [
                'count' => $count,
            ]);
        }
    }
}
