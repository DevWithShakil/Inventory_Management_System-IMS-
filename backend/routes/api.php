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
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\TransactionController;

// --- Public Routes ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --- Protected Routes (Logged in Users: Admin & Staff) ---
Route::middleware('auth:sanctum')->group(function () {

    // Auth & User Info
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);

    // --- SHARED ROUTES (Access for Admin & Staff) ---

    // 1. Dashboard & Reports
    Route::get('/dashboard/overview', [ReportController::class, 'dashboardOverview']);
    Route::get('/reports/low-stock', [ReportController::class, 'lowStockReport']); // 🔥 Moved Here (Fixes Dashboard Alert)
    Route::get('/notifications', [ReportController::class, 'lowStockReport']);     // 🔥 Moved Here (Fixes Sidebar Badge)

    // 2. Settings (Read Only - For Logo/App Name)
    Route::get('/settings', [SettingController::class, 'index']); // 🔥 Moved Here (Fixes Sidebar Error)

    // 3. Sales Operations
    Route::apiResource('sales', SaleController::class)->only(['store', 'index', 'show']);
    Route::apiResource('customers', CustomerController::class);
    Route::get('/customers/{id}/history', [CustomerController::class, 'history']);

    // 4. Product View & Search (Staff needs this for POS)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/search', [ProductController::class, 'searchProduct']);

    // 5. Payment
    Route::post('/pay-via-ssl', [SslCommerzController::class, 'payViaAjax']);

    // 6. Coupon Management Routes
    Route::get('/coupons', [CouponController::class, 'index']);
    Route::post('/check-coupon', [CouponController::class, 'checkCoupon']);

    // 7. Sales Return Routes
    Route::get('/sales-returns', [SalesReturnController::class, 'index']);
    Route::post('/sales-returns', [SalesReturnController::class, 'store']);


    // 8. Expense Management (Shared Access)
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::put('/expenses/{id}', [ExpenseController::class, 'update']);
    Route::get('/expense-categories', [ExpenseController::class, 'categories']);
    Route::post('/expense-categories', [ExpenseController::class, 'storeCategory']);

    // 9. Transaction / Payment Routes
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions/{trx_id}', [TransactionController::class, 'show']);


    // --- 🔒 ADMIN ONLY ROUTES ---
    Route::middleware('role:admin')->group(function () {

        // Inventory Management (CRUD)
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('brands', BrandController::class);
        Route::apiResource('units', UnitController::class);
        Route::apiResource('products', ProductController::class)->except(['index']);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('purchases', PurchaseController::class);

        // Expense Management (Admin Only Actions)
        Route::put('/expense-categories/{id}', [ExpenseController::class, 'updateCategory']);
        Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);

        // Reports (Specific Reports)
        Route::get('/reports/daily-sales', [ReportController::class, 'dailySalesReport']);

        // Dangerous Actions
        Route::delete('/sales/{id}', [SaleController::class, 'destroy']);

        // Settings (Write Access)
        Route::post('/settings', [SettingController::class, 'update']);

        // User Management
        Route::apiResource('users', UserController::class);

        // Import Products
        Route::post('/products/import', [ProductController::class, 'import']);

        // Coupon Admin Actions
        Route::post('/coupons', [CouponController::class, 'store']);
        Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);
        Route::put('/coupons/{id}', [CouponController::class, 'update']);

        // Transaction / Payment
        Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);

    });

});
