<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAccessibilityReportStatusRequest;
use App\Http\Resources\Api\V1\AccessibilityReportResource;
use App\Models\AccessibilityReport;
use App\Services\AccessibilityReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminAccessibilityReportController extends Controller
{
    public function __construct(
        private readonly AccessibilityReportService $accessibilityReportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->accessibilityReportService->adminList(
            $request->query('status'),
            $request->query('search'),
            max(1, min((int) $request->query('per_page', 15), 50)),
        );

        return sendResponse(
            true,
            'Accessibility reports retrieved successfully.',
            AccessibilityReportResource::collection($paginator),
            HttpStatus::HTTP_OK,
            [
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        );
    }

    public function updateStatus(UpdateAccessibilityReportStatusRequest $request, int $id): JsonResponse
    {
        $report = AccessibilityReport::query()->findOrFail($id);
        $updated = $this->accessibilityReportService->updateStatus(
            $report,
            (string) $request->validated('status'),
        );

        return sendResponse(
            true,
            'Accessibility report status updated.',
            new AccessibilityReportResource($updated),
            HttpStatus::HTTP_OK,
        );
    }
}
