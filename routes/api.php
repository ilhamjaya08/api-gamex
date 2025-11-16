<?php

use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\H2hBalanceController;
use App\Http\Controllers\Api\Admin\ProductSyncController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\QrisWebhookController;
use App\Http\Controllers\Api\TransactionCallbackController;
use App\Http\Controllers\Api\TransactionController;
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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('deposits', [DepositController::class, 'index']);
    Route::get('deposits/active', [DepositController::class, 'active']);
    Route::post('deposits', [DepositController::class, 'store']);
    Route::get('deposits/{deposit}', [DepositController::class, 'show']);
    Route::post('deposits/{deposit}/cancel', [DepositController::class, 'cancel']);
});

// Public routes
Route::get('deposits/{deposit}/refresh-status', [DepositController::class, 'refreshStatus']);
Route::post('webhook/qris', [QrisWebhookController::class, 'handle']);

// Transaction routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store']);
    Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
    Route::get('transactions/{transaction}/refresh-status', [TransactionController::class, 'refreshStatus']);
});

// Transaction callback webhook (public)
Route::get('webhook/transaction-callback', TransactionCallbackController::class);
