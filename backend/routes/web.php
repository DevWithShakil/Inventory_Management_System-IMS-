<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SslCommerzController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/ssl/success', [SslCommerzController::class, 'success'])->name('ssl.success');
Route::post('/ssl/fail', [SslCommerzController::class, 'fail'])->name('ssl.fail');
Route::post('/ssl/cancel', [SslCommerzController::class, 'cancel'])->name('ssl.cancel');

