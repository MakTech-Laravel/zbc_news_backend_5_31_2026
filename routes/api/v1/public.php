<?php

use App\Http\Controllers\Api\V1\AuthenticableController;
use App\Http\Controllers\Api\V1\frontend\CategoryController;
use Illuminate\Support\Facades\Route;


Route::controller(AuthenticableController::class)->prefix('auth')->group(function () {

    Route::post('/login', 'login')->name('api.v1.auth.login')->middleware('request_limitter');
    Route::post('/register', 'register')->name('api.v1.auth.register')->middleware('request_limitter');
    Route::post('/two-factor-challenge', 'twoFactorChallenge')->name('api.v1.auth.two-factor-challenge')->middleware('request_limitter');
    Route::post('/logout', 'logout')->name('api.v1.auth.logout')->middleware('auth:api');;
    
});

Route::controller(CategoryController::class)->prefix('categories')->group(function () {
    Route::get('/', 'index')->name('api.v1.categories.index');
});
