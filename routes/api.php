<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::middleware('auth:api')->prefix('/v1/admin')->name('admin.api.v1.')->group(base_path('routes/api/v1/protected.php'));
Route::prefix('/v1')->name('api.v1.')->group(base_path('routes/api/v1/public.php'));
