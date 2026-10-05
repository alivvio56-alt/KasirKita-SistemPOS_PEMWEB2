<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| KasirKita REST API  (prefix: /api)
|--------------------------------------------------------------------------
| Semua endpoint selain register/login wajib header:
|   Authorization: Bearer <token>
|   Accept: application/json
*/

Route::middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // Auth
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // Dashboard & laporan
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('reports/sales', [DashboardController::class, 'sales']);

    // Master data
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::get('customers/{customer}/orders', [CustomerController::class, 'orders']);

    // Inventori
    Route::get('stock-movements', [StockMovementController::class, 'index']);
    Route::get('products/{product}/stock-movements', [StockMovementController::class, 'forProduct']);
    Route::post('products/{product}/stock-movements', [StockMovementController::class, 'store']);

    // Pesanan (proses bisnis utama)
    Route::apiResource('orders', OrderController::class);
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('orders/{order}/payment', [OrderController::class, 'pay']);

    // Manajemen pengguna (admin)
    Route::apiResource('users', UserController::class);
});
