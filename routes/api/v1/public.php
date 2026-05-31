<?php

use App\Models\Api\V1\AuthenticableController;
use Illuminate\Support\Facades\Route;


Route::controller(AuthenticableController::class)->prefix('auth')->group(function () {

    Route::post('/login', 'login')->name('api.v1.auth.login');
    Route::post('/register', 'register')->name('api.v1.auth.register');
    Route::post('/logout', 'logout')->name('api.v1.auth.logout')->middleware('auth:api');;
    
});