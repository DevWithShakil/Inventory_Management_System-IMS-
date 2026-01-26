<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SslCommerzController;


// --- Public Routes ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --- Protected Routes ---
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Staff Routes
    Route::apiResource('sales', SaleController::class)->only(['store', 'index', 'show']);
    Route::apiResource('customers', CustomerController::class);
    Route::get('/products', [ProductController::class, 'index']);

    // payment
    Route::post('/pay-via-ssl', [SslCommerzController::class, 'payViaAjax']);

// Admin Routes
Route::middleware('role:admin')->group(function () {

    // Inventory Routes
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('products', ProductController::class)->except(['index']);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('purchases', PurchaseController::class);

    // Reports & Dashboard Routes
    Route::get('/dashboard/overview', [ReportController::class, 'dashboardOverview']);
    Route::get('/reports/low-stock', [ReportController::class, 'lowStockReport']);
    Route::get('/reports/daily-sales', [ReportController::class, 'dailySalesReport']);

    Route::delete('/sales/{id}', [SaleController::class, 'destroy']);
    });
});




