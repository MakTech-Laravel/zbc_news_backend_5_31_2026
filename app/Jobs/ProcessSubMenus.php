<?php

namespace App\Jobs;

use App\Services\SubMenuService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSubMenus
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SubMenuService $subMenuService): void
    {
        $subMenuService->processScheduledWindows();
    }
}