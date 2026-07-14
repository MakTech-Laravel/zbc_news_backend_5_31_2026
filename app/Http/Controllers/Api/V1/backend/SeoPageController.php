<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SeoPageUpdateRequest;
use App\Http\Resources\Api\V1\ResolvedSeoResource;
use App\Http\Resources\Api\V1\SeoPageResource;
use App\Services\SeoPageService;
use App\Services\SeoResolverService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SeoPageController extends Controller
{
    public function __construct(
        private readonly SeoPageService $seoPageService,
        private readonly SeoResolverService $seoResolverService,
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
        $resolved = $this->seoResolverService->resolve($path);

        return sendResponse(
            true,
            'SEO resolved successfully',
            new ResolvedSeoResource($resolved),
            HttpStatus::HTTP_OK,
        );
    }
}
