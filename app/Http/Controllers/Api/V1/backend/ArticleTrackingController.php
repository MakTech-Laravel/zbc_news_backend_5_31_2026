<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v1\TrackArticleRequest;
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
    // public function track(TrackArticleRequest $request): JsonResponse
    // {
    //     Log::info('Track request received', $request->validated());

    //     $result = $this->trackingService->track(
    //         request: $request,
    //         data:    $request->validated()
    //     );
    //     Log::info('Track result', $result);
    //     return sendResponse(
    //         true,
    //         'Article tracked successfully',
    //         $result,
    //         HttpStatus::HTTP_OK,
    //     );
    // }


    public function track(TrackArticleRequest $request): JsonResponse
    {
        Log::info('Article Tracking Request', [
            'user_id'      => $request->input('user_id'),
            'article_id'   => $request->input('article_id'),
            'session_id'   => $request->input('session_id'),
            'time_spent'   => $request->input('time_spent'),
            'scroll_depth' => $request->input('scroll_depth'),

            // Extra Debug Information
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),

            // Full Request Payload
            'payload'      => $request->all(),
        ]);

        $result = $this->trackingService->track(
            request: $request,
            data: $request->validated()
        );

        Log::info('Article Tracking Result', [
            'result' => $result,
        ]);

        return sendResponse(
            true,
            'Article tracked successfully',
            $result,
            HttpStatus::HTTP_OK
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
            userId: $request->input('user_id'),
            perPage: $request->input('per_page', 15)
        );

        return response()->json($history);
    }
}
