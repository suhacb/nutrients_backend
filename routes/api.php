<?php

use App\Http\Controllers\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::middleware('auth:api')->group(function () {
    Route::prefix('users')->name('users.')->group(function () {
        Route::post('/', [UsersController::class, 'store'])->name('create');
        Route::put('{user}', [UsersController::class, 'update'])->name('update');
        Route::get('{user}', [UsersController::class, 'show'])->name('show');
    });
});