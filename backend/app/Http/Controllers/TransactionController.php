<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Sale;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['customer', 'supplier', 'creator'])->latest();

        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $transactions = $query->paginate(15);
        return response()->json(['status' => true, 'data' => $transactions]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:customer_pay,supplier_pay',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'payment_method' => 'required|string',
            'customer_id' => 'required_if:type,customer_pay|exists:customers,id|nullable',
            'supplier_id' => 'required_if:type,supplier_pay|exists:suppliers,id|nullable',
        ]);

        try {
            DB::beginTransaction();

            $trxId = 'TRX-' . time() . rand(100, 999);
            $clearedInvoices = [];

            // ==========================================
            // Case A: Customer Paying Due
            // ==========================================
            if ($request->type === 'customer_pay') {

                $customer = Customer::findOrFail($request->customer_id);

                if ($request->amount > $customer->balance) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Amount exceeds current due (৳ ' . $customer->balance . ')'
                    ], 422);
                }

                $customer->decrement('balance', $request->amount);

                $unpaidSales = Sale::with('sale_items.product')
                    ->where('customer_id', $request->customer_id)
                    ->where('payment_status', '!=', 'paid')
                    ->orderBy('date', 'asc')
                    ->get();

                $remainingPayment = $request->amount;

                foreach ($unpaidSales as $sale) {
                    if ($remainingPayment <= 0) break;

                    $due = $sale->due_amount;
                    $paidForThis = 0;

                    if ($remainingPayment >= $due) {
                        $sale->update(['paid_amount' => $sale->paid_amount + $due, 'due_amount' => 0, 'payment_status' => 'paid']);
                        $paidForThis = $due;
                        $remainingPayment -= $due;
                    } else {
                        $sale->update(['paid_amount' => $sale->paid_amount + $remainingPayment, 'due_amount' => $sale->due_amount - $remainingPayment, 'payment_status' => 'partial']);
                        $paidForThis = $remainingPayment;
                        $remainingPayment = 0;
                    }

                    $productNames = $sale->sale_items->map(function($item) {
                        return $item->product ? $item->product->name . " (Qty: $item->quantity)" : 'Item';
                    })->join(', ');

                    $clearedInvoices[] = [
                        'invoice_no' => $sale->invoice_no ?? ('INV-'.$sale->id),
                        'date' => $sale->date,
                        'products' => $productNames,
                        'amount' => $paidForThis
                    ];
                }

                $transaction = Transaction::create([
                    'trx_id' => $trxId,
                    'type' => 'credit',
                    'customer_id' => $request->customer_id,
                    'amount' => $request->amount,
                    'date' => $request->date,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'meta_data' => $clearedInvoices, // 🔥 FIX: No json_encode
                    'created_by' => auth()->id() ?? 1
                ]);
            }

            // ==========================================
            // Case B: Supplier Payment
            // ==========================================
            elseif ($request->type === 'supplier_pay') {

                $supplier = Supplier::findOrFail($request->supplier_id);

                if ($request->amount > $supplier->balance) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Amount exceeds payable balance (৳ ' . $supplier->balance . ')'
                    ], 422);
                }

                $supplier->decrement('balance', $request->amount);

                $unpaidPurchases = Purchase::with(['purchase_items.product'])
                    ->where('supplier_id', $request->supplier_id)
                    ->where('payment_status', '!=', 'paid')
                    ->orderBy('date', 'asc')
                    ->get();

                $remainingPayment = $request->amount;

                foreach ($unpaidPurchases as $purchase) {
                    if ($remainingPayment <= 0) break;

                    $due = $purchase->due_amount;
                    $paidForThis = 0;

                    if ($remainingPayment >= $due) {
                        $purchase->update(['paid_amount' => $purchase->paid_amount + $due, 'due_amount' => 0, 'payment_status' => 'paid']);
                        $paidForThis = $due;
                        $remainingPayment -= $due;
                    } else {
                        $purchase->update(['paid_amount' => $purchase->paid_amount + $remainingPayment, 'due_amount' => $purchase->due_amount - $remainingPayment, 'payment_status' => 'partial']);
                        $paidForThis = $remainingPayment;
                        $remainingPayment = 0;
                    }

                    $itemsList = $purchase->purchase_items ?? collect([]);
                    $productNames = $itemsList->map(function($item) {
                        return $item->product ? $item->product->name . " (Qty: $item->quantity)" : 'Item';
                    })->join(', ');

                    $clearedInvoices[] = [
                        'invoice_no' => $purchase->reference_no ?? ('PUR-'.$purchase->id),
                        'date' => $purchase->date,
                        'products' => $productNames,
                        'amount' => $paidForThis
                    ];
                }

                $transaction = Transaction::create([
                    'trx_id' => $trxId,
                    'type' => 'debit',
                    'supplier_id' => $request->supplier_id,
                    'amount' => $request->amount,
                    'date' => $request->date,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'meta_data' => $clearedInvoices, // 🔥 FIX: Consistent Array
                    'created_by' => auth()->id() ?? 1
                ]);
            }

            DB::commit();

            // 🔥 FIX: Return loaded relationship so frontend gets customer name immediately
            $transaction->load(['customer', 'supplier']);

            return response()->json([
                'status' => true,
                'message' => 'Payment recorded successfully',
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Transaction Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($trx_id)
    {
        $transaction = Transaction::with(['customer', 'supplier', 'creator'])
            ->where('trx_id', $trx_id)
            ->first();

        if (!$transaction) {
            return response()->json(['status' => false, 'message' => 'Transaction not found'], 404);
        }

        $currentBalance = 0;
        if ($transaction->type === 'credit' && $transaction->customer) {
            $currentBalance = $transaction->customer->balance;
        } elseif ($transaction->type === 'debit' && $transaction->supplier) {
            $currentBalance = $transaction->supplier->balance;
        }

        $transaction->prev_balance = $currentBalance + $transaction->amount;
        $transaction->curr_balance = $currentBalance;

        return response()->json(['status' => true, 'data' => $transaction]);
    }

    public function destroy($id)
    {
        // ... (Destroy method remains same)
        $transaction = Transaction::find($id);
        if (!$transaction) return response()->json(['status' => false, 'message' => 'Not found'], 404);

        try {
            DB::beginTransaction();
            if ($transaction->type === 'credit' && $transaction->customer_id) {
                Customer::where('id', $transaction->customer_id)->increment('balance', $transaction->amount);
            } elseif ($transaction->type === 'debit' && $transaction->supplier_id) {
                Supplier::where('id', $transaction->supplier_id)->increment('balance', $transaction->amount);
            }
            $transaction->delete();
            DB::commit();
            return response()->json(['status' => true, 'message' => 'Transaction deleted & Balance reverted']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
