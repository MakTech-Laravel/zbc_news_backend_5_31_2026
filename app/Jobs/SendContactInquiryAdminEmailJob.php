<?php

namespace App\Jobs;

use App\Models\ContactInquiry;
use App\Services\ContactInquiryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendContactInquiryAdminEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $inquiryId,
    ) {}

    public function handle(ContactInquiryService $contactInquiryService): void
    {
        $inquiry = ContactInquiry::query()->find($this->inquiryId);

        if (! $inquiry) {
            return;
        }

        try {
            $contactInquiryService->sendAdminEmail($inquiry);
        } catch (\Throwable $exception) {
            Log::warning('Contact inquiry admin email failed.', [
                'inquiry_id' => $this->inquiryId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
