<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __construct(
        private readonly SitemapService $sitemapService,
    ) {}

    public function general(): Response
    {
        return response($this->sitemapService->generalXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function news(): Response
    {
        return response($this->sitemapService->newsXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(): Response
    {
        return response($this->robotsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * robots.txt. Disallow list mirrors the non-public routes in the frontend
     * router (authRoutes, ProtectedRoute-gated /user & /admin, and the
     * client-only utility routes); everything else under the public tree is
     * allowed. Sitemap URLs use the public frontend origin.
     */
    private function robotsTxt(): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        $disallow = [
            '/admin/',
            '/user/',
            '/login',
            '/login/',
            '/register',
            '/forget-password',
            '/otp-verification',
            '/reset-password',
            '/unauthorized',
            '/dashboard',
            '/ws-test',
            '/demo/',
            '/newsletter/verify',
            '/newsletter/unsubscribe',
            '/newsletter/preferences',
        ];

        $lines = ['User-agent: *', 'Allow: /'];
        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.$base.'/sitemap.xml';
        $lines[] = 'Sitemap: '.$base.'/news-sitemap.xml';

        return implode("\n", $lines)."\n";
    }
}
