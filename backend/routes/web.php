<?php

use Illuminate\Support\Facades\Route;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Transaction;
use App\Http\Controllers\SslCommerzController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/ssl/success', [SslCommerzController::class, 'success'])->name('ssl.success');
Route::post('/ssl/fail', [SslCommerzController::class, 'fail'])->name('ssl.fail');
Route::post('/ssl/cancel', [SslCommerzController::class, 'cancel'])->name('ssl.cancel');

Route::get('/fix-balances', function () {
    $customers = Customer::all();
    foreach ($customers as $customer) {
        $totalSalesDue = Sale::where('customer_id', $customer->id)->sum('due_amount');
        $totalPaidViaTransactions = Transaction::where('customer_id', $customer->id)
                                                ->where('type', 'credit')
                                                ->sum('amount');
        $actualBalance = $totalSalesDue - $totalPaidViaTransactions;

        $customer->update(['balance' => $actualBalance]);
    }
    return "All customer balances recalculated successfully!";
});
