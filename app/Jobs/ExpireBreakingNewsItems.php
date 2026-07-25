<?php

namespace App\Jobs;

use App\Services\BreakingNewsService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireBreakingNewsItems
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BreakingNewsService $breakingNewsService): void
    {
        $breakingNewsService->expireDueItems();
    }
}
