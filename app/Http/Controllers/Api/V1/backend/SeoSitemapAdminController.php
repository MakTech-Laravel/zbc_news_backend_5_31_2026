<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Services\SitemapService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * Admin-only convenience controls for the sitemap/robots endpoints.
 *
 * These do NOT replace the live public endpoints (Google Search Console is given
 * the live URL, not a file) — they exist for inspection and manual force-refresh.
 * All output is produced by the same SitemapService methods the public routes use,
 * so an admin download is byte-identical to the public endpoint for the same cache
 * state.
 */
class SeoSitemapAdminController extends Controller
{
    public function __construct(
        private readonly SitemapService $sitemapService,
    ) {}

    public function refresh()
    {
        $this->sitemapService->refresh();

        return sendResponse(
            true,
            'Sitemap cache refreshed successfully',
            null,
            HttpStatus::HTTP_OK,
        );
    }

    public function downloadSitemap(Request $request)
    {
        $type = $request->query('type') === 'news' ? 'news' : 'general';

        [$xml, $filename] = $type === 'news'
            ? [$this->sitemapService->newsXml(), 'news-sitemap.xml']
            : [$this->sitemapService->generalXml(), 'sitemap.xml'];

        return response($xml, HttpStatus::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function downloadRobots()
    {
        return response($this->sitemapService->robotsTxt(), HttpStatus::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="robots.txt"',
        ]);
    }
}
