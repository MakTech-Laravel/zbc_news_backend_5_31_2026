<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Services\AdminSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdminSearchController extends Controller
{
    public function __construct(
        private readonly AdminSearchService $searchService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $results = $this->searchService->search(
            $request->user(),
            $validated['q'],
            (int) ($validated['limit'] ?? 5),
        );

        $total = collect($results)->flatten(1)->count();

        return sendResponse(
            true,
            'Admin search results fetched successfully.',
            $results,
            HttpStatus::HTTP_OK,
            ['total' => $total],
        );
    }
}
