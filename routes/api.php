<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [LoginController::class, 'login'])->name('login');
    Route::get('validate-access-token', [LoginController::class, 'validateAccessToken'])->name('validate-access-token');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('verify.frontend');
});

// Route::middleware('auth:api')->group(function () {
//     Route::prefix('users')->name('users.')->group(function () {
//         Route::post('/', [UsersController::class, 'store'])->name('create');
//         Route::put('{user}', [UsersController::class, 'update'])->name('update');
//         Route::get('{user}', [UsersController::class, 'show'])->name('show');
//         Route::delete('{user}', [UsersController::class, 'delete'])->name('delete');
//     });
// });