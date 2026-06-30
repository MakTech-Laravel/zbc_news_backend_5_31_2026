<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TrackArticleRequest;
use App\Services\ArticleTrackingService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ArticleTrackingController extends Controller
{
    public function __construct(
        private readonly ArticleTrackingService $trackingService
    ) {}

    /**
     * POST /api/articles/track-read
     * Guest + Auth  hit 
     */
    public function track(TrackArticleRequest $request): JsonResponse
    {
        Log::info('Track request received', $request->validated());

        $result = $this->trackingService->track(
            request: $request,
            data: $request->validated()
        );
        Log::info('Track result', $result);
        return sendResponse(
            true,
            'Article tracked successfully',
            $result,
            HttpStatus::HTTP_OK,
        );
    }
    /**
     * GET /api/articles/{id}/stats
     * Admin/Editor can see
     */
    public function stats(int $articleId): JsonResponse
    {
        $stats = $this->trackingService->getArticleStats($articleId);
        return response()->json(['data' => $stats]);
    }

    /**
     * GET /api/user/read-history
     * Authenticated user history
     */
    public function userHistory(Request $request): JsonResponse
    {
        $history = $this->trackingService->getUserReadHistory(
            userId: auth('api')->id(),
            perPage: $request->input('per_page', 15)
        );

        return sendResponse(
            true,
            'Read history fetched successfully',
            $history,
            HttpStatus::HTTP_OK
        );
    }

    public function readingAnalytics(Request $request): JsonResponse
    {
        $analytics = $this->trackingService->getUserReadingAnalytics(
            userId: auth('api')->id()
        );

        return sendResponse(
            true,
            'Reading analytics fetched successfully',
            $analytics,
            HttpStatus::HTTP_OK
        );
    }
}
