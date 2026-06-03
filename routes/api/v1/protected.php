<?php

use App\Enums\PermissionEnum;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;

Route::controller(CategoryController::class)->prefix('categories')->group(function () {
    Route::get('/', 'index')->name('api.v1.categories.index');
    Route::post('/store', 'store')->name('api.v1.categories.store');
    Route::get('/show/{slug}', 'show')->name('api.v1.categories.show');
    Route::post('/update/{slug}', 'update')->name('api.v1.categories.update');
    Route::delete('/delete/{slug}', 'destroy')->name('api.v1.categories.destroy');
    Route::post('/restore/{slug}', 'restore')->name('api.v1.categories.restore');
    Route::delete('/force/{slug}', 'forceDelete')->name('api.v1.categories.forceDelete');
});

Route::controller(RoleController::class)->prefix('roles')->group(function () {
    Route::get('/', 'index')->name('api.v1.roles.index')->middleware('permission:' . PermissionEnum::ROLES_VIEW->value);
    Route::post('/store', 'store')->name('api.v1.roles.store')->middleware('permission:' . PermissionEnum::ROLES_CREATE->value);
    Route::get('/show/{id}', 'show')->name('api.v1.roles.show')->middleware('permission:' . PermissionEnum::ROLES_VIEW->value);
    Route::post('/update/{id}', 'update')->name('api.v1.roles.update')->middleware('permission:' . PermissionEnum::ROLES_UPDATE->value);
    Route::delete('/delete/{id}', 'destroy')->name('api.v1.roles.destroy')->middleware('permission:' . PermissionEnum::ROLES_DELETE->value);
    Route::post('/restore/{id}', 'restore')->name('api.v1.roles.restore')->middleware('permission:' . PermissionEnum::ROLES_RESTORE->value);
    Route::delete('/force/{id}', 'forceDelete')->name('api.v1.roles.forceDelete');
});


Route::controller(UserController::class)->prefix('users')->group(function () {
    Route::get('/', 'index')->name('api.v1.users.index');
    Route::post('/store', 'store')->name('api.v1.users.store');
    Route::get('/show/{id}', 'show')->name('api.v1.users.show');
    Route::post('/update/{id}', 'update')->name('api.v1.users.update');
    Route::delete('/delete/{id}', 'destroy')->name('api.v1.users.destroy');
    Route::post('/restore/{id}', 'restore')->name('api.v1.users.restore');
    Route::delete('/force/{id}', 'forceDelete')->name('api.v1.users.forceDelete');
    
    Route::post('/two-factor-enable', 'twoFactorEnable')->name('api.v1.users.two-factor-enable');
});
