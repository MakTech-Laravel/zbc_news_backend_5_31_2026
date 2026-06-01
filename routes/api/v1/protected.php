<?php 
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CategoryController;




Route::controller(CategoryController::class)->prefix('categories')->group(function () {
    Route::get('/', 'index')->name('api.v1.categories.index');
    Route::post('/store', 'store')->name('api.v1.categories.store');
    Route::get('/show/{slug}', 'show')->name('api.v1.categories.show');
    Route::post('/update/{slug}', 'update')->name('api.v1.categories.update');
    Route::delete('/delete/{slug}', 'destroy')->name('api.v1.categories.destroy');
    Route::post('/restore/{slug}', 'restore')->name('api.v1.categories.restore');
    Route::delete('/force/{slug}', 'forceDelete')->name('api.v1.categories.forceDelete');

});