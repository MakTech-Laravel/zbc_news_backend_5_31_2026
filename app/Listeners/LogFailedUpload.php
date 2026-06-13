<?php

namespace App\Listeners;

use App\Events\MediaUploadFailed;
use Illuminate\Support\Facades\Log;

class LogFailedUpload
{
    public function handle(MediaUploadFailed $event): void
    {
        Log::warning('Media upload failed', [
            'media_uuid' => $event->media->uuid,
            'uploaded_by' => $event->media->uploaded_by,
            'reason' => $event->reason,
        ]);
    }
}
