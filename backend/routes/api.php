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
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\SalesReturnController;


// --- Public Routes ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --- Protected Routes (Logged in Users: Admin & Staff) ---
Route::middleware('auth:sanctum')->group(function () {

    // Auth & User Info
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);

    // --- Staff & Admin Shared Routes ---

    // Sales Operations
    Route::apiResource('sales', SaleController::class)->only(['store', 'index', 'show']);
    Route::apiResource('customers', CustomerController::class);
    Route::get('/customers/{id}/history', [CustomerController::class, 'history']);

    // Product View & Search (Staff needs this for POS)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/search', [ProductController::class, 'searchProduct']);

    // Payment
    Route::post('/pay-via-ssl', [SslCommerzController::class, 'payViaAjax']);

    // Coupon Management Routes
    Route::get('/coupons', [CouponController::class, 'index']);
    Route::post('/check-coupon', [CouponController::class, 'checkCoupon']);

    // Sales Return Routes
    Route::get('/sales-returns', [SalesReturnController::class, 'index']);
    Route::post('/sales-returns', [SalesReturnController::class, 'store']);

    // --- Admin Only Routes ---
    Route::middleware('role:admin')->group(function () {

        // Inventory Management (CRUD)
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('brands', BrandController::class);
        Route::apiResource('units', UnitController::class);
        Route::apiResource('products', ProductController::class)->except(['index']);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('purchases', PurchaseController::class);

        // Reports & Dashboard
        Route::get('/dashboard/overview', [ReportController::class, 'dashboardOverview']);
        Route::get('/reports/low-stock', [ReportController::class, 'lowStockReport']);
        Route::get('/reports/daily-sales', [ReportController::class, 'dailySalesReport']);

        // Notifications (Low Stock Alert - Usually for Admin)
        Route::get('/notifications', [ReportController::class, 'lowStockReport']);

        // Dangerous Actions
        Route::delete('/sales/{id}', [SaleController::class, 'destroy']);

        // Settings
        Route::get('/settings', [SettingController::class, 'index']);
        Route::post('/settings', [SettingController::class, 'update']);

        // User Management
        Route::apiResource('users', UserController::class);

        // Import Products
        Route::post('/products/import', [ProductController::class, 'import']);

        // coupon create & delete
        Route::post('/coupons', [CouponController::class, 'store']);
        Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);
        Route::put('/coupons/{id}', [CouponController::class, 'update']);
    });

});
