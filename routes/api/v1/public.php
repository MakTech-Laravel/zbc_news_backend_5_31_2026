<?php

use App\Http\Controllers\Api\V1\AuthenticableController;
use App\Http\Controllers\Api\V1\backend\ArticleTrackingController;
use App\Http\Controllers\Api\V1\backend\SaveArticleController;
use App\Http\Controllers\Api\V1\backend\SeoPageController;
use App\Http\Controllers\Api\V1\frontend\CommentController;
use App\Http\Controllers\Api\V1\frontend\CategoryController;
use App\Http\Controllers\Api\V1\frontend\ArticleController;
use App\Http\Controllers\Api\V1\frontend\AdSlotController;
use App\Http\Controllers\Api\V1\frontend\AdTrackingController;
use App\Http\Controllers\Api\V1\frontend\NavigationController;
use App\Http\Controllers\Api\V1\frontend\NewsletterController;
use App\Http\Controllers\Api\V1\frontend\PublicSiteSettingsController;
use App\Http\Controllers\Api\V1\frontend\SearchController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\backend\TagController;

Route::controller(AuthenticableController::class)->prefix('auth')->group(function () {

    Route::post('/login', 'login')->name('api.v1.auth.login')->middleware('request_limitter');
    Route::post('/register', 'register')->name('api.v1.auth.register')->middleware('request_limitter');
    Route::post('/forgot-password', 'forgotPassword')->name('api.v1.auth.forgot-password')->middleware('request_limitter');
    Route::post('/reset-password', 'resetPassword')->name('api.v1.auth.reset-password')->middleware('request_limitter');
    Route::post('/otp/verify', 'verifyOtp')->name('api.v1.auth.otp.verify')->middleware('request_limitter');
    Route::post('/otp/resend', 'resendOtp')->name('api.v1.auth.otp.resend')->middleware('request_limitter');
    Route::post('/two-factor-challenge', 'twoFactorChallenge')->name('api.v1.auth.two-factor-challenge')->middleware('request_limitter');
    Route::post('/logout', 'logout')->name('api.v1.auth.logout')->middleware('auth:api');;
});

Route::controller(CategoryController::class)->prefix('categories')->group(function () {
    Route::get('/', 'index')->name('api.v1.categories.index');
});

Route::controller(ArticleController::class)->prefix('articles')->group(function () {
    Route::get('/', 'index')->name('api.v1.articles.index');
    Route::get('/latest', 'latest')->name('api.v1.articles.latest');
    Route::get('/latest-stories', 'latestStories')->name('api.v1.articles.latest-stories');
    Route::get('/show/{slug}', 'show')->name('api.v1.articles.show');
    Route::get('/related/{slug}', 'related')->name('api.v1.articles.related');
    Route::get('/by-tag/{tagSlug}', 'articlesByTag')->name('api.v1.articles.by-tag');
    // Route::post('/view/{slug}', 'recordView')->name('api.v1.articles.view')->middleware('request_limitter');
    Route::get('/category/{slug}', 'byCategory')->name('api.v1.articles.by-category');
    Route::get('/most-read', 'mostRead')->name('api.v1.articles.most-read');
    Route::get('/grid', 'gridArticles')->name('api.v1.articles.grid');
    Route::get('/search', [SearchController::class, 'search'])->name('api.v1.articles.search');
});

Route::controller(CommentController::class)->group(function () {
    Route::get('/articles/{slug}/comments', 'index')->name('api.v1.articles.comments.index');
    Route::post('/articles/{slug}/comments', 'store')
        ->middleware('request_limitter')
        ->name('api.v1.articles.comments.store');
});

Route::controller(PublicSiteSettingsController::class)->prefix('site-settings')->group(function () {
    Route::get('/', 'index')->name('api.v1.public.site-settings.index');
});

Route::controller(SeoPageController::class)->prefix('seo-pages')->group(function () {
    Route::get('/', 'index')->name('api.v1.public.seo-pages.index');
    Route::get('/resolve', 'resolve')->name('api.v1.public.seo-pages.resolve');
});

Route::controller(SaveArticleController::class)->prefix('save-articles')->group(function () {
    Route::get('/check/{articleId}', 'checkSaved')->name('api.v1.save-articles.check');
});


Route::post('/articles/track-read', [ArticleTrackingController::class, 'track'])->name('api.v1.articles.track-read');
Route::get('/trending-tags', [TagController::class, 'trendingTags'])->name('api.v1.tags.trending-tags');

Route::controller(SearchController::class)->prefix('search')->middleware('optional_api_auth')->group(function () {
    Route::get('/history', 'history')->name('api.v1.search.history');
    Route::post('/history', 'storeHistory')->name('api.v1.search.history.store');
    Route::delete('/history', 'clearHistory')->name('api.v1.search.history.clear');
});

Route::get('/navigation/quick-links', [NavigationController::class, 'quickLinks'])->name('api.v1.navigation.quick-links');
Route::get('/ads/slots', [AdSlotController::class, 'index'])->name('api.v1.ads.slots');
Route::post('/ads/track', [AdTrackingController::class, 'track'])
    ->middleware('request_limitter')
    ->name('api.v1.ads.track');

Route::prefix('newsletter')->controller(NewsletterController::class)->group(function (): void {
    Route::post('/subscribe', 'subscribe')->middleware('request_limitter')->name('api.v1.newsletter.subscribe');
    Route::get('/verify', 'verify')->name('api.v1.newsletter.verify');
    Route::get('/unsubscribe', 'unsubscribe')->name('api.v1.newsletter.unsubscribe');
    Route::get('/preferences', 'preferences')->name('api.v1.newsletter.preferences');
    Route::put('/preferences', 'updatePreferences')->name('api.v1.newsletter.preferences.update');
    Route::get('/categories', 'categories')->name('api.v1.newsletter.categories');
    Route::get('/track/open/{campaignId}/{subscriberId}/{signature}', 'trackOpen')->name('api.v1.newsletter.track.open');
    Route::get('/track/click/{campaignId}/{subscriberId}/{signature}', 'trackClick')->name('api.v1.newsletter.track.click');
});