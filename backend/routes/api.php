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


Route::apiResource('categories', CategoryController::class);
Route::apiResource('brands', BrandController::class);
Route::apiResource('units', UnitController::class);
Route::apiResource('products', ProductController::class);
Route::apiResource('suppliers', SupplierController::class);
Route::apiResource('customers', CustomerController::class);
Route::apiResource('purchases', PurchaseController::class);
Route::apiResource('sales', SaleController::class);

// Reports & Dashboard Routes
Route::get('/dashboard/overview', [ReportController::class, 'dashboardOverview']);
Route::get('/reports/low-stock', [ReportController::class, 'lowStockReport']);
Route::get('/reports/daily-sales', [ReportController::class, 'dailySalesReport']);




