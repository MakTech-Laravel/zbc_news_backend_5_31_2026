<?php

use App\Enums\PermissionEnum;
use App\Http\Controllers\Api\V1\backend\ArticleController;
use App\Http\Controllers\Api\V1\backend\ArticleTrackingController;
use App\Http\Controllers\Api\V1\backend\CategoryController;
use App\Http\Controllers\Api\V1\backend\MediaController;
use App\Http\Controllers\Api\V1\backend\MembershipPlanController;
use App\Http\Controllers\Api\V1\backend\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\backend\PermissionController;
use App\Http\Controllers\Api\V1\backend\RoleController;
use App\Http\Controllers\Api\V1\backend\SaveArticleController;
use App\Http\Controllers\Api\V1\backend\SiteSettingsController;
use App\Http\Controllers\Api\V1\backend\TagController;
use App\Http\Controllers\Api\V1\backend\UserController;
use Illuminate\Support\Facades\Route;

Route::controller(CategoryController::class)->prefix('categories')->group(function () {
    Route::get('/', 'index')->name('api.v1.categories.index')
        ->middleware('permission:' . PermissionEnum::CATEGORIES_LIST->value);
    Route::post('/store', 'store')->name('api.v1.categories.store')
        ->middleware('permission:' . PermissionEnum::CATEGORIES_CREATE->value);
    Route::get('/show/{slug}', 'show')->name('api.v1.categories.show')
        ->middleware('permission:' . PermissionEnum::CATEGORIES_SHOW->value);
    Route::post('/update/{slug}', 'update')->name('api.v1.categories.update')
        ->middleware('permission:' . PermissionEnum::CATEGORIES_UPDATE->value);
    Route::delete('/delete/{slug}', 'destroy')->name('api.v1.categories.destroy')
        ->middleware('permission:' . PermissionEnum::CATEGORIES_DELETE->value);
    Route::post('/restore/{slug}', 'restore')->name('api.v1.categories.restore')
        ->middleware('permission:' . PermissionEnum::CATEGORIES_RESTORE->value);
    Route::delete('/force/{slug}', 'forceDelete')->name('api.v1.categories.forceDelete')
        ->middleware('permission:' . PermissionEnum::CATEGORIES_FORCE_DELETE->value);
});

Route::controller(RoleController::class)->prefix('roles')->group(function () {
    Route::get('/', 'index')->name('api.v1.roles.index')
        ->middleware('permission:' . PermissionEnum::ROLES_LIST->value);
    Route::post('/store', 'store')->name('api.v1.roles.store')
        ->middleware('permission:' . PermissionEnum::ROLES_CREATE->value);
    Route::get('/show/{id}', 'show')->name('api.v1.roles.show')
        ->middleware('permission:' . PermissionEnum::ROLES_LIST->value);
    Route::post('/update/{id}', 'update')->name('api.v1.roles.update')
        ->middleware('permission:' . PermissionEnum::ROLES_UPDATE->value);
    Route::delete('/delete/{id}', 'destroy')->name('api.v1.roles.destroy')
        ->middleware('permission:' . PermissionEnum::ROLES_DELETE->value);
    Route::post('/restore/{id}', 'restore')->name('api.v1.roles.restore')
        ->middleware('permission:' . PermissionEnum::ROLES_RESTORE->value);
    Route::delete('/force/{id}', 'forceDelete')->name('api.v1.roles.forceDelete')
        ->middleware('permission:' . PermissionEnum::ROLES_FORCE_DELETE->value);
});

Route::controller(ArticleController::class)->prefix('articles')->group(function () {
    Route::get('/', 'index')->name('api.v1.articles.index')
        ->middleware('permission:' . PermissionEnum::ARTICLES_LIST->value);
    Route::get('/trashed', 'trashed')->name('api.v1.articles.trashed')
        ->middleware('permission:' . PermissionEnum::ARTICLES_TRASHED->value);
    Route::post('/store', 'store')->name('api.v1.articles.store')
        ->middleware('permission:' . PermissionEnum::ARTICLES_CREATE->value);
    Route::get('/show/{slug}', 'show')->name('api.v1.articles.show')
        ->middleware('permission:' . PermissionEnum::ARTICLES_SHOW->value);
    Route::post('/update/{slug}', 'update')->name('api.v1.articles.update')
        ->middleware('permission:' . PermissionEnum::ARTICLES_UPDATE->value);
    Route::delete('/delete/{slug}', 'destroy')->name('api.v1.articles.destroy')
        ->middleware('permission:' . PermissionEnum::ARTICLES_DELETE->value);
    Route::post('/restore/{slug}', 'restore')->name('api.v1.articles.restore')
        ->middleware('permission:' . PermissionEnum::ARTICLES_RESTORE->value);
    Route::delete('/force/{slug}', 'forceDelete')->name('api.v1.articles.forceDelete')
        ->middleware('permission:' . PermissionEnum::ARTICLES_FORCE_DELETE->value);
    Route::get('/{slug}/activities', 'activities')->name('api.v1.articles.activities')
        ->middleware('permission:' . PermissionEnum::ARTICLES_ACTIVITIES->value);
    Route::get('/{id}/stats', [ArticleTrackingController::class, 'stats'])->name('api.v1.articles.stats')
        ->middleware('permission:' . PermissionEnum::ARTICLES_STATS->value);
    Route::get('/{tagSlug}/articles', 'articlesByTag')->name('api.v1.articles.articles-by-tag');
});

Route::get('/user/read-history', [ArticleTrackingController::class, 'userHistory'])
    ->name('api.v1.user.read-history')
    ->middleware('permission:' . PermissionEnum::USER_READ_HISTORY->value);

Route::controller(TagController::class)->prefix('tags')->group(function () {
    Route::get('/', 'index')->name('api.v1.tags.index')
        ->middleware('permission:' . PermissionEnum::TAGS_LIST->value);
    Route::post('/store', 'store')->name('api.v1.tags.store')
        ->middleware('permission:' . PermissionEnum::TAGS_CREATE->value);
    Route::get('/show/{id}', 'show')->name('api.v1.tags.show')
        ->middleware('permission:' . PermissionEnum::TAGS_SHOW->value);
    Route::post('/update/{id}', 'update')->name('api.v1.tags.update')
        ->middleware('permission:' . PermissionEnum::TAGS_UPDATE->value);
    Route::delete('/delete/{id}', 'destroy')->name('api.v1.tags.destroy')
        ->middleware('permission:' . PermissionEnum::TAGS_DELETE->value);
    Route::post('/restore/{id}', 'restore')->name('api.v1.tags.restore')
        ->middleware('permission:' . PermissionEnum::TAGS_RESTORE->value);
    Route::delete('/force/{id}', 'forceDelete')->name('api.v1.tags.forceDelete')
        ->middleware('permission:' . PermissionEnum::TAGS_FORCE_DELETE->value);
});

Route::controller(SaveArticleController::class)->prefix('save-articles')->group(function () {
    Route::get('/', 'index')->name('api.v1.save-articles.index')
        ->middleware('permission:' . PermissionEnum::SAVE_ARTICLES_LIST->value);
    Route::post('/toggle', 'toggle')->name('api.v1.save-articles.toggle')
        ->middleware('permission:' . PermissionEnum::SAVE_ARTICLES_TOGGLE->value);
});

Route::controller(SiteSettingsController::class)->prefix('site-settings')->group(function () {
    Route::get('/', 'index')->name('api.v1.site-settings.index')
        ->middleware('permission:' . PermissionEnum::SITE_SETTINGS_LIST->value);
    Route::post('/update', 'createOrUpdate')->name('api.v1.site-settings.update')
        ->middleware('permission:' . PermissionEnum::SITE_SETTINGS_UPDATE->value);
});

Route::controller(MembershipPlanController::class)->prefix('plans')->group(function () {
    Route::get('/', 'index')->name('api.v1.plans.index')
        ->middleware('permission:' . PermissionEnum::PLANS_LIST->value);
    Route::get('/trashed', 'trashed')->name('api.v1.plans.trashed')
        ->middleware('permission:' . PermissionEnum::PLANS_TRASHED->value);
    Route::post('/store', 'store')->name('api.v1.plans.store')
        ->middleware('permission:' . PermissionEnum::PLANS_CREATE->value);
    Route::get('/show/{id}', 'show')->name('api.v1.plans.show')
        ->middleware('permission:' . PermissionEnum::PLANS_SHOW->value);
    Route::post('/update/{id}', 'update')->name('api.v1.plans.update')
        ->middleware('permission:' . PermissionEnum::PLANS_UPDATE->value);
    Route::delete('/delete/{id}', 'destroy')->name('api.v1.plans.destroy')
        ->middleware('permission:' . PermissionEnum::PLANS_DELETE->value);
    Route::post('/restore/{id}', 'restore')->name('api.v1.plans.restore')
        ->middleware('permission:' . PermissionEnum::PLANS_RESTORE->value);
    Route::delete('/force/{id}', 'forceDelete')->name('api.v1.plans.forceDelete')
        ->middleware('permission:' . PermissionEnum::PLANS_FORCE_DELETE->value);
});

Route::controller(NotificationPreferenceController::class)->prefix('notification-preferences')->group(function () {
    Route::get('/', 'show')->name('api.v1.notification-preferences.show')
        ->middleware('permission:' . PermissionEnum::NOTIFICATION_PREFERENCES_SHOW->value);
    Route::put('/update', 'update')->name('api.v1.notification-preferences.update')
        ->middleware('permission:' . PermissionEnum::NOTIFICATION_PREFERENCES_UPDATE->value);
});

Route::controller(PermissionController::class)->prefix('permissions')->group(function () {
    Route::get('/', 'index')->name('api.v1.permissions.index')
        ->middleware('permission:' . PermissionEnum::PERMISSIONS_LIST->value);
});

Route::controller(MediaController::class)
    ->prefix('media')
    ->middleware(\App\Http\Middleware\Api\V1\ValidateUploadSize::class)
    ->group(function () {
        Route::get('/', 'index')->name('api.v1.media.index')
            ->middleware('permission:' . PermissionEnum::MEDIA_LIST->value);
        Route::post('/store', 'store')->name('api.v1.media.store')
            ->middleware('permission:' . PermissionEnum::MEDIA_CREATE->value);
        Route::get('/signed-params', 'signedParams')->name('api.v1.media.signed-params')
            ->middleware('permission:' . PermissionEnum::MEDIA_SIGNED_PARAMS->value);
        Route::delete('/bulk', 'bulkDestroy')->name('api.v1.media.bulk-destroy')
            ->middleware('permission:' . PermissionEnum::MEDIA_BULK_DELETE->value);
        Route::get('/show/{uuid}', 'show')->name('api.v1.media.show')
            ->middleware('permission:' . PermissionEnum::MEDIA_SHOW->value);
        Route::delete('/delete/{uuid}', 'destroy')->name('api.v1.media.destroy')
            ->middleware('permission:' . PermissionEnum::MEDIA_DELETE->value);
        Route::post('/transform/{uuid}', 'transform')->name('api.v1.media.transform')
            ->middleware('permission:' . PermissionEnum::MEDIA_TRANSFORM->value);
    });

Route::controller(UserController::class)->prefix('users')->group(function () {
    Route::get('/', 'index')->name('api.v1.users.index')
        ->middleware('permission:' . PermissionEnum::USERS_LIST->value);
    Route::post('/store', 'store')->name('api.v1.users.store')
        ->middleware('permission:' . PermissionEnum::USERS_CREATE->value);
    Route::get('/profile', 'profile')->name('api.v1.users.profile')
        ->middleware('permission:' . PermissionEnum::USERS_PROFILE->value);
    Route::put('/profile/update', 'profileUpdate')->name('api.v1.users.profile-update')
        ->middleware('permission:' . PermissionEnum::USERS_PROFILE_UPDATE->value);
    Route::get('/show/{id}', 'show')->name('api.v1.users.show')
        ->middleware('permission:' . PermissionEnum::USERS_SHOW->value);
    Route::post('/update/{id}', 'update')->name('api.v1.users.update')
        ->middleware('permission:' . PermissionEnum::USERS_UPDATE->value);
    Route::delete('/delete/{id}', 'destroy')->name('api.v1.users.destroy')
        ->middleware('permission:' . PermissionEnum::USERS_DELETE->value);
    Route::get('/{userId}/article-activities', 'articleActivities')->name('api.v1.users.article-activities')
        ->middleware('permission:' . PermissionEnum::USERS_ARTICLE_ACTIVITIES->value);
    Route::post('/two-factor-enable', 'twoFactorEnable')->name('api.v1.users.two-factor-enable')
        ->middleware('permission:' . PermissionEnum::USERS_TWO_FACTOR_ENABLE->value);
    Route::get('/reading-analytics', [ArticleTrackingController::class, 'readingAnalytics'])
        ->name('api.v1.users.reading-analytics')
        ->middleware('permission:' . PermissionEnum::USERS_READING_ANALYTICS->value);
});
