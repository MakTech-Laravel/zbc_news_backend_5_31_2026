<?php

use App\Http\Controllers\ArticleSharePreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/share/articles/{slug}', [ArticleSharePreviewController::class, 'show'])
    ->name('share.article');
