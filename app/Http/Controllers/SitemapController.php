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
        return response($this->sitemapService->robotsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
