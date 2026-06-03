<?php

use App\Enums\PermissionEnum;
use App\Http\Controllers\Api\V1\ArticleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SaveArticleController;
use App\Http\Controllers\Api\V1\SiteSettingsController;
use App\Http\Controllers\Api\V1\TagController;

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

Route::controller(ArticleController::class)->prefix('articles')->group(function () {
    Route::get('/', 'index')->name('api.v1.articles.index');
    Route::post('/store', 'store')->name('api.v1.articles.store');
    Route::get('/show/{slug}', 'show')->name('api.v1.articles.show');
    Route::post('/update/{slug}', 'update')->name('api.v1.articles.update');
    Route::delete('/delete/{slug}', 'destroy')->name('api.v1.articles.destroy');
    Route::post('/restore/{slug}', 'restore')->name('api.v1.articles.restore');
    Route::delete('/force/{slug}', 'forceDelete')->name('api.v1.articles.forceDelete');
});

Route::controller(TagController::class)->prefix('tags')->group(function () {
    Route::get('/', 'index')->name('api.v1.tags.index');
    Route::post('/store', 'store')->name('api.v1.tags.store');
    Route::get('/show/{id}', 'show')->name('api.v1.tags.show');
    Route::post('/update/{id}', 'update')->name('api.v1.tags.update');
    Route::delete('/delete/{id}', 'destroy')->name('api.v1.tags.destroy');
    Route::post('/restore/{id}', 'restore')->name('api.v1.tags.restore');
    Route::delete('/force/{id}', 'forceDelete')->name('api.v1.tags.forceDelete');
});

Route::controller(SaveArticleController::class)->prefix('save-articles')->group(function () {
    Route::get('/', 'index')->name('api.v1.save-articles.index');
    Route::post('/store', 'store')->name('api.v1.save-articles.store');
    Route::delete('/delete/{id}', 'destroy')->name('api.v1.save-articles.destroy');
});

Route::controller(SiteSettingsController::class)->prefix('site-settings')->group(function () {
    Route::get('/', 'index')->name('api.v1.site-settings.index');
    Route::post('/update', 'createOrUpdate')->name('api.v1.site-settings.update');
});