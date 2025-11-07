<?php

use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\H2hBalanceController;
use App\Http\Controllers\Api\Admin\ProductSyncController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('h2h/balance', H2hBalanceController::class);
    Route::post('products/import', [ProductSyncController::class, 'import']);
    Route::post('products/refresh', [ProductSyncController::class, 'refresh']);
    Route::get('users', [AdminUserController::class, 'index']);
    Route::post('users', [AdminUserController::class, 'store']);
    Route::get('users/{user}', [AdminUserController::class, 'show']);
    Route::put('users/{user}', [AdminUserController::class, 'update']);
    Route::patch('users/{user}', [AdminUserController::class, 'update']);
    Route::delete('users/{user}', [AdminUserController::class, 'destroy']);
    Route::patch('users/{user}/toggle-role', [AdminUserController::class, 'toggleRole']);
});

Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/with-products', [CategoryController::class, 'withProducts']);
Route::get('categories/{category}/products', [CategoryController::class, 'products']);
