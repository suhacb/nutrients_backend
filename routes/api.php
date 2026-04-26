<?php

use App\Http\Controllers\IngredientNutrientController;
use App\Http\Controllers\IngredientsController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NutrientTagsController;
use App\Http\Controllers\NutrientsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SourcesController;
use App\Http\Controllers\UnitsController;
use Illuminate\Support\Facades\Route;

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

    Route::prefix('{nutrient}/tags')->name('tags.')->group(function () {
        Route::post('', [NutrientTagsController::class, 'attach'])->name('attach');
        Route::delete('{tag}', [NutrientTagsController::class, 'detach'])->name('detach');
        Route::delete('', [NutrientTagsController::class, 'detachAll'])->name('detach-all');
    });
});

Route::prefix('nutrient-tags')->name('nutrient-tags.')->middleware('verify.frontend')->group(function() {
    Route::get('', [NutrientTagsController::class, 'index'])->name('index');
    Route::get('{nutrientTag}', [NutrientTagsController::class, 'show'])->name('show');
    Route::post('', [NutrientTagsController::class, 'store'])->name('store');
    Route::put('{nutrientTag}', [NutrientTagsController::class, 'update'])->name('update');
    Route::delete('{nutrientTag}', [NutrientTagsController::class, 'delete'])->name('delete');
});

Route::prefix('ingredients')->name('ingredients.')->middleware('verify.frontend')->group(function() {
    Route::get('', [IngredientsController::class, 'index'])->name('index');
    Route::get('{ingredient}', [IngredientsController::class, 'show'])->name('show');
    Route::post('', [IngredientsController::class, 'store'])->name('store');
    Route::put('{ingredient}', [IngredientsController::class, 'update'])->name('update');
    Route::delete('{ingredient}', [IngredientsController::class, 'delete'])->name('delete');
    Route::post('search', [IngredientsController::class, 'search'])->name('search');

    Route::prefix('{ingredient}/nutrients')->name('nutrients.')->group(function () {
        Route::get('', [IngredientNutrientController::class, 'index'])->name('index');
        Route::post('attach', [IngredientNutrientController::class, 'attach'])->name('attach');
        Route::put('{nutrient}', [IngredientNutrientController::class, 'updatePivot'])->name('update-pivot');
        Route::delete('{nutrient}', [IngredientNutrientController::class, 'detach'])->name('detach');
        Route::delete('', [IngredientNutrientController::class, 'detachAll'])->name('detach-all');
    });
});

Route::prefix('units')->name('units.')->middleware('verify.frontend')->group(function() {
    Route::get('', [UnitsController::class, 'index'])->name('index');
    Route::get('{unit}', [UnitsController::class, 'show'])->name('show');
    Route::post('', [UnitsController::class, 'store'])->name('store');
    Route::put('{unit}', [UnitsController::class, 'update'])->name('update');
    Route::delete('{unit}', [UnitsController::class, 'delete'])->name('delete');
});

Route::prefix('sources')->name('sources.')->middleware('verify.frontend')->group(function() {
    Route::get('', [SourcesController::class, 'index'])->name('index');
    Route::get('{source}', [SourcesController::class, 'show'])->name('show');
    Route::post('', [SourcesController::class, 'store'])->name('store');
    Route::put('{source}', [SourcesController::class, 'update'])->name('update');
    Route::delete('{source}', [SourcesController::class, 'delete'])->name('delete');
});

Route::prefix('search')->name('search')->middleware('verify.frontend')->group(function() {
    Route::post('', [SearchController::class, 'search']);
});

// Test route to test protected route and verify.frontend
Route::get('test', function() {
     return true;
})->name('test')->middleware('verify.frontend');