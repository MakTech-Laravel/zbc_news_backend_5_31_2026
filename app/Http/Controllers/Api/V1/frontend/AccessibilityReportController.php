<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAccessibilityReportRequest;
use App\Http\Resources\Api\V1\AccessibilityReportResource;
use App\Services\AccessibilityReportService;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AccessibilityReportController extends Controller
{
    public function __construct(
        private readonly AccessibilityReportService $accessibilityReportService,
    ) {}

    public function store(StoreAccessibilityReportRequest $request)
    {
        try {
            $report = $this->accessibilityReportService->store(
                $request->validated(),
                $request,
            );
        } catch (QueryException $exception) {
            report($exception);

            return sendResponse(
                false,
                'Accessibility report storage is not ready. Please run database migrations on the server.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return sendResponse(
                false,
                'Unable to submit your report right now. Please try again later.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return sendResponse(
            true,
            'Thank you. Your accessibility report has been submitted.',
            new AccessibilityReportResource($report),
            HttpStatus::HTTP_CREATED,
        );
    }
}
