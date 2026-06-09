<?php

use App\Enums\PermissionEnum;
use App\Http\Controllers\Api\V1\backend\ArticleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\backend\CategoryController;
use App\Http\Controllers\Api\V1\backend\MembershipPlanController;
use App\Http\Controllers\Api\V1\backend\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\backend\PermissionController;
use App\Http\Controllers\Api\V1\backend\RoleController;
use App\Http\Controllers\Api\V1\backend\UserController;
use App\Http\Controllers\Api\V1\backend\SaveArticleController;
use App\Http\Controllers\Api\V1\backend\SiteSettingsController;
use App\Http\Controllers\Api\V1\backend\TagController;

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
Route::controller(ArticleController::class)->prefix('articles')->group(function () {
    Route::get('/', 'index')->name('api.v1.articles.index');
    Route::get('/trashed', 'trashed')->name('api.v1.articles.trashed');
    Route::post('/store', 'store')->name('api.v1.articles.store');
    Route::get('/show/{slug}', 'show')->name('api.v1.articles.show');
    Route::post('/update/{slug}', 'update')->name('api.v1.articles.update');
    Route::delete('/delete/{slug}', 'destroy')->name('api.v1.articles.destroy');
    Route::post('/restore/{slug}', 'restore')->name('api.v1.articles.restore');
    Route::delete('/force/{slug}', 'forceDelete')->name('api.v1.articles.forceDelete');
    Route::get('/{slug}/activities', 'activities')->name('api.v1.articles.activities');
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

Route::controller(MembershipPlanController::class)->prefix('plans')->group(function () {
    Route::get('/', 'index')->name('api.v1.plans.index');
    Route::get('/trashed', 'trashed')->name('api.v1.plans.trashed');
    Route::post('/store', 'store')->name('api.v1.plans.store');
    Route::get('/show/{id}', 'show')->name('api.v1.plans.show');
    Route::post('/update/{id}', 'update')->name('api.v1.plans.update');
    Route::delete('/delete/{id}', 'destroy')->name('api.v1.plans.destroy');
    Route::post('/restore/{id}', 'restore')->name('api.v1.plans.restore');
    Route::delete('/force/{id}', 'forceDelete')->name('api.v1.plans.forceDelete');
});

Route::controller(NotificationPreferenceController::class)->prefix('notification-preferences')->group(function () {
    Route::get('/', 'show')->name('api.v1.notification-preferences.show');
    Route::put('/update', 'update')->name('api.v1.notification-preferences.update');
});

Route::controller(PermissionController::class)->prefix('permissions')->group(function () {
    Route::get('/', 'index')->name('api.v1.permissions.index');
});