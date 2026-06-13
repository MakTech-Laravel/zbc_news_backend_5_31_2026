<?php

namespace App\Jobs;

use App\Events\MediaUploadCompleted;
use App\Events\MediaUploadFailed;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadMediaToCloudinary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [30, 90, 270];

    public function __construct(
        protected int $mediaId,
        protected string $tempPath,
        protected array $options = []
    ) {
        $this->onQueue('uploads');
    }

    public function handle(MediaService $mediaService): void
    {
        $media = Media::findOrFail($this->mediaId);
        $media->update(['status' => 'uploading']);

        $fullPath = Storage::disk('local')->path($this->tempPath);

        try {
            $file = new UploadedFile(
                $fullPath,
                $media->original_filename,
                $media->mime_type,
                null,
                true
            );

            $mediaService->uploadSync($media, $file, $this->options);

            event(new MediaUploadCompleted($media->fresh()));
        } catch (\Throwable $e) {
            $media->increment('upload_attempts');
            $media->update(['status' => 'failed', 'error_message' => $e->getMessage()]);

            Log::error('CloudinaryJob failed', [
                'media_id' => $this->mediaId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            event(new MediaUploadFailed($media, $e->getMessage()));

            if ($this->attempts() >= $this->tries) {
                $this->fail($e);

                return;
            }

            throw $e;
        } finally {
            Storage::disk('local')->delete($this->tempPath);
        }
    }
}
