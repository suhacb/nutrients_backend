<?php

use App\Http\Controllers\IngredientsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\NutrientsController;
use App\Http\Controllers\SearchController;

/**Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [LoginController::class, 'login'])->name('login');
    Route::get('validate-access-token', [LoginController::class, 'validateAccessToken'])->name('validate-access-token')->middleware('ensure.user.from.token');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('verify.frontend');
});

Route::prefix('nutrients')->name('nutrients.')->middleware('verify.frontend')->group(function() {
    Route::get('', [NutrientsController::class, 'index'])->name('index');
    Route::get('{nutrient}', [NutrientsController::class, 'show'])->name('show');
    Route::post('', [NutrientsController::class, 'store'])->name('store');
    Route::put('{nutrient}', [NutrientsController::class, 'update'])->name('update');
    Route::delete('{nutrient}', [NutrientsController::class, 'delete'])->name('delete');
    Route::post('search', [NutrientsController::class, 'search'])->name('search');
});

Route::prefix('ingredients')->name('ingredients.')->middleware('verify.frontend')->group(function() {
    Route::get('', [IngredientsController::class, 'index'])->name('index');
    Route::get('{ingredient}', [IngredientsController::class, 'show'])->name('show');
    Route::post('', [IngredientsController::class, 'store'])->name('store');
    Route::put('{ingredient}', [IngredientsController::class, 'update'])->name('update');
    Route::delete('{ingredient}', [IngredientsController::class, 'delete'])->name('delete');
    Route::post('search', [IngredientsController::class, 'search'])->name('search');
});

Route::prefix('search')->name('search')->middleware('verify.frontend')->group(function() {
    Route::post('', [SearchController::class, 'search']);
});

// Test route to test protected route and verify.frontend
Route::get('test', function() {
     return true;
})->name('test')->middleware('verify.frontend');