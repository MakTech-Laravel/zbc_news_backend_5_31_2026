<?php 

use App\Http\Controllers\Category\CategoryController;
use Illuminate\Support\Facades\Route;




Route::controller(CategoryController::class)->prefix('categories')->group(function () {
    Route::get('/', 'index')->name('api.v1.categories.index');
    Route::post('/', 'store')->name('api.v1.categories.store');
    Route::get('/{id}', 'show')->name('api.v1.categories.show');
    Route::put('/{id}', 'update')->name('api.v1.categories.update');
    Route::delete('/{id}', 'destroy')->name('api.v1.categories.destroy');

});