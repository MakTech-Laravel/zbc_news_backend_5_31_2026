<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SeoPageUpdateRequest;
use App\Http\Resources\Api\V1\SeoPageResource;
use App\Services\SeoPageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SeoPageController extends Controller
{
    public function __construct(
        private readonly SeoPageService $seoPageService
    ) {}

    public function index()
    {
        $pages = $this->seoPageService->getAll();

        return sendResponse(
            true,
            'SEO pages retrieved successfully',
            SeoPageResource::collection($pages),
            HttpStatus::HTTP_OK,
        );
    }

    public function show(string $pageKey)
    {
        $page = $this->seoPageService->getByPageKey($pageKey);

        return sendResponse(
            true,
            'SEO page retrieved successfully',
            new SeoPageResource($page),
            HttpStatus::HTTP_OK,
        );
    }

    public function update(SeoPageUpdateRequest $request, string $pageKey)
    {
        $page = $this->seoPageService->update($pageKey, $request->validated());

        return sendResponse(
            true,
            'SEO page updated successfully',
            new SeoPageResource($page),
            HttpStatus::HTTP_OK,
        );
    }

    public function resolve(Request $request)
    {
        $path = (string) $request->query('path', '/');
        $page = $this->seoPageService->resolveForPath($path);

        if (!$page) {
            return sendResponse(
                false,
                'SEO page not found',
                null,
                HttpStatus::HTTP_NOT_FOUND,
            );
        }

        return sendResponse(
            true,
            'SEO page resolved successfully',
            new SeoPageResource($page),
            HttpStatus::HTTP_OK,
        );
    }
}
