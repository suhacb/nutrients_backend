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
    Route::get('validate-access-token', [LoginController::class, 'validateAccessToken'])->name('validate-access-token')->middleware('ensure.user.from.token');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('verify.frontend');
});

// Test route to test protected route and verify.frontend
Route::get('test', function() {
     return true;
})->name('test')->middleware('verify.frontend');