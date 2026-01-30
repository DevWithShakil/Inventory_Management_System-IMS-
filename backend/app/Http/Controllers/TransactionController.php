<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Sale;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['customer', 'supplier', 'creator'])->latest();

        // Filter by Type (credit/debit)
        if ($request->type) {
            $query->where('type', $request->type);
        }

        // Filter by Customer
        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by Supplier
        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Date Range
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

            // রিসিটে দেখানোর জন্য ইনভয়েস ডিটেইলস রাখার অ্যারে
            $clearedInvoices = [];

            // ==========================================
            // Case A: Customer Paying Due (Credit - In)
            // ==========================================
            if ($request->type === 'customer_pay') {

                $customer = Customer::findOrFail($request->customer_id);

                // ১. ভ্যালিডেশন: ব্যালেন্স চেক
                if ($request->amount > $customer->balance) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Amount exceeds current due (৳ ' . $customer->balance . ')'
                    ], 422);
                }

                // ২. কাস্টমার ব্যালেন্স কমানো
                $customer->decrement('balance', $request->amount);

                // ৩. FIFO লজিকে সেলস আপডেট করা
                $unpaidSales = Sale::with('sale_items.product') // প্রোডাক্ট ডিটেইলস লোড করলাম
                    ->where('customer_id', $request->customer_id)
                    ->where('payment_status', '!=', 'paid')
                    ->orderBy('date', 'asc') // পুরনো আগে
                    ->get();

                $remainingPayment = $request->amount;

                foreach ($unpaidSales as $sale) {
                    if ($remainingPayment <= 0) break;

                    $due = $sale->due_amount;
                    $paidForThis = 0;

                    if ($remainingPayment >= $due) {
                        // পুরো ইনভয়েস পেমেন্ট
                        $sale->update([
                            'paid_amount' => $sale->paid_amount + $due,
                            'due_amount' => 0,
                            'payment_status' => 'paid'
                        ]);
                        $paidForThis = $due;
                        $remainingPayment -= $due;
                    } else {
                        // আংশিক পেমেন্ট
                        $sale->update([
                            'paid_amount' => $sale->paid_amount + $remainingPayment,
                            'due_amount' => $sale->due_amount - $remainingPayment,
                            'payment_status' => 'partial'
                        ]);
                        $paidForThis = $remainingPayment;
                        $remainingPayment = 0;
                    }

                    // 🔥 রিসিটের জন্য ডাটা সংগ্রহ (Invoice No, Date, Products)
                    $productNames = $sale->sale_items->map(function($item) {
                        return $item->product ? $item->product->name . " (Qty: $item->quantity)" : 'Unknown Product';
                    })->join(', ');

                    $clearedInvoices[] = [
                        'invoice_no' => $sale->invoice_no ?? ('INV-'.$sale->id),
                        'date' => $sale->date,
                        'products' => $productNames,
                        'amount' => $paidForThis
                    ];
                }

                // ৪. ট্রানজেকশন তৈরি
                $transaction = Transaction::create([
                    'trx_id' => $trxId,
                    'type' => 'credit',
                    'customer_id' => $request->customer_id,
                    'amount' => $request->amount,
                    'date' => $request->date,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'meta_data' => json_encode($clearedInvoices), // 🔥 ডিটেইলস সেভ করলাম
                    'created_by' => auth()->id() ?? 1
                ]);
            }

            // ==========================================
            // Case B: Payment to Supplier (Debit - Out)
            // ==========================================
            elseif ($request->type === 'supplier_pay') {

                $supplier = Supplier::findOrFail($request->supplier_id);

                // ১. ভ্যালিডেশন
                if ($request->amount > $supplier->balance) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Amount exceeds payable balance (৳ ' . $supplier->balance . ')'
                    ], 422);
                }

                // ২. ব্যালেন্স কমানো
                $supplier->decrement('balance', $request->amount);

                // ৩. FIFO লজিক (Purchase Items সহ লোড করা)
                // 🔥 লক্ষ্য করুন: 'purchase_items.product' রিলেশন লোড করা হচ্ছে
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
                        $purchase->update([
                            'paid_amount' => $purchase->paid_amount + $due,
                            'due_amount' => 0,
                            'payment_status' => 'paid'
                        ]);
                        $paidForThis = $due;
                        $remainingPayment -= $due;
                    } else {
                        $purchase->update([
                            'paid_amount' => $purchase->paid_amount + $remainingPayment,
                            'due_amount' => $purchase->due_amount - $remainingPayment,
                            'payment_status' => 'partial'
                        ]);
                        $paidForThis = $remainingPayment;
                        $remainingPayment = 0;
                    }

                    // 🔥🔥 রিসিটের জন্য ডাটা সংগ্রহ (FIXED HERE)
                    // PurchaseItem থেকে প্রোডাক্টের নাম বের করা
                    $itemsList = $purchase->purchase_items ?? collect([]);

                    $productNames = $itemsList->map(function($item) {
                        return $item->product ? $item->product->name . " (Qty: $item->quantity)" : 'Unknown Item';
                    })->join(', ');

                    $clearedInvoices[] = [
                        'invoice_no' => $purchase->invoice_no ?? ('PUR-'.$purchase->id),
                        'date' => $purchase->date,
                        'products' => $productNames, // এখন এটি আর খালি থাকবে না
                        'amount' => $paidForThis
                    ];
                }

                // ৪. ট্রানজেকশন তৈরি
                $transaction = Transaction::create([
                    'trx_id' => $trxId,
                    'type' => 'debit',
                    'supplier_id' => $request->supplier_id,
                    'amount' => $request->amount,
                    'date' => $request->date,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'meta_data' => $clearedInvoices, // 🔥 কাস্টড অ্যারে অটোমেটিক JSON হবে
                    'created_by' => auth()->id() ?? 1
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Payment recorded successfully',
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
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
        $previousBalance = $currentBalance + $transaction->amount;
        $transaction->prev_balance = $previousBalance;
        $transaction->curr_balance = $currentBalance;

        return response()->json(['status' => true, 'data' => $transaction]);
    }

    public function destroy($id)
    {
        $transaction = Transaction::find($id);

        if (!$transaction) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        try {
            DB::beginTransaction();
            if ($transaction->type === 'credit' && $transaction->customer_id) {
                Customer::where('id', $transaction->customer_id)->increment('balance', $transaction->amount);
            }
            elseif ($transaction->type === 'debit' && $transaction->supplier_id) {
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
