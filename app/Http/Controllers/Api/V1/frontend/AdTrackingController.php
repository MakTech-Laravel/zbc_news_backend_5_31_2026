<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Services\MonetizationAnalyticsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdTrackingController extends Controller
{
    public function __construct(
        private readonly MonetizationAnalyticsService $analyticsService,
    ) {}

    public function track(Request $request)
    {
        $validated = $request->validate([
            'slot_key' => ['required', 'string', 'max:100'],
            'event_type' => ['required', 'in:impression,click'],
            'session_id' => ['nullable', 'string', 'max:64'],
        ]);

        $tracked = $this->analyticsService->track(
            $validated['slot_key'],
            $validated['event_type'],
            $request,
        );

        return sendResponse(
            true,
            $tracked ? 'Ad event tracked' : 'Ad event skipped',
            ['tracked' => $tracked],
            HttpStatus::HTTP_OK,
        );
    }
}
