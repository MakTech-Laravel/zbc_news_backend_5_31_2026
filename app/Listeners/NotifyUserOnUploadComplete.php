<?php

namespace App\Listeners;

use App\Events\MediaUploadCompleted;
use Illuminate\Support\Facades\Log;

class NotifyUserOnUploadComplete
{
    public function handle(MediaUploadCompleted $event): void
    {
        Log::info('Media upload completed', [
            'media_uuid' => $event->media->uuid,
            'uploaded_by' => $event->media->uploaded_by,
        ]);
    }
}
