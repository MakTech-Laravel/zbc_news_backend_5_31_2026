<?php

use App\Http\Controllers\Api\V1\AuthenticableController;
use App\Http\Controllers\Api\V1\backend\ArticleTrackingController;
use App\Http\Controllers\Api\V1\backend\SaveArticleController;
use App\Http\Controllers\Api\V1\frontend\CategoryController;
use App\Http\Controllers\Api\V1\frontend\ArticleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\backend\TagController;

Route::controller(AuthenticableController::class)->prefix('auth')->group(function () {

    Route::post('/login', 'login')->name('api.v1.auth.login')->middleware('request_limitter');
    Route::post('/register', 'register')->name('api.v1.auth.register')->middleware('request_limitter');
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
    // Route::post('/view/{slug}', 'recordView')->name('api.v1.articles.view')->middleware('request_limitter');
    Route::get('/category/{slug}', 'byCategory')->name('api.v1.articles.by-category');
    Route::get('/most-read', 'mostRead')->name('api.v1.articles.most-read');
    Route::get('/grid', 'gridArticles')->name('api.v1.articles.grid');
});

Route::controller(SaveArticleController::class)->prefix('save-articles')->group(function () {
    Route::get('/check/{articleId}', 'checkSaved')->name('api.v1.save-articles.check');
});


Route::post('/articles/track-read', [ArticleTrackingController::class, 'track'])->name('api.v1.articles.track-read');
Route::get('/trending-tags', [TagController::class, 'trendingTags'])->name('api.v1.tags.trending-tags');