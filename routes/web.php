<?php

use App\Http\Controllers\ArticleSharePreviewController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/share/articles/{slug}', [ArticleSharePreviewController::class, 'show'])
    ->name('share.article');

// SEO: robots.txt + sitemaps. In production the reverse proxy must route these
// three paths (and /share/) to the backend, everything else to the SSR frontend.
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'general'])->name('sitemap.general');
Route::get('/news-sitemap.xml', [SitemapController::class, 'news'])->name('sitemap.news');
