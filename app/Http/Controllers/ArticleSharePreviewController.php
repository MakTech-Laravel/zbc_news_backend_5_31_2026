<?php

namespace App\Http\Controllers;

use App\Services\ArticleService;
use App\Services\SeoMetaService;
use App\Services\SiteSettingsService;
use Illuminate\View\View;

class ArticleSharePreviewController extends Controller
{
    public function __construct(
        private readonly ArticleService $articleService,
        private readonly SeoMetaService $seoMetaService,
        private readonly SiteSettingsService $siteSettingsService,
    ) {}

    public function show(string $slug): View
    {
        $article = $this->articleService->getPublishedBySlug($slug);
        $seo = $this->seoMetaService->resolveArticleMeta($article);
        $resolved = $seo['resolved'];

        $frontendBase = rtrim((string) config('app.frontend_url'), '/');
        $canonicalUrl = "{$frontendBase}/news-details/{$article->slug}";

        $imagePath = $article->open_graph_image ?: $article->featured_image;
        $imageUrl = $this->absoluteMediaUrl($imagePath);

        $siteName = $this->siteSettingsService->getOrDefault()->site_name ?: 'ZBC News';
        $title = (string) ($resolved['meta_title'] ?: $article->title);
        $description = (string) ($resolved['meta_description'] ?: $article->excerpt ?: $article->title);

        return view('share.article', [
            'title' => $title,
            'description' => $description,
            'image' => $imageUrl,
            'imageAlt' => $article->title,
            'canonicalUrl' => $canonicalUrl,
            'redirectUrl' => $canonicalUrl,
            'siteName' => $siteName,
            'publishedAt' => $article->published_at?->toIso8601String(),
        ]);
    }

    private function absoluteMediaUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        return url(str_starts_with($path, '/') ? $path : "/{$path}");
    }
}
