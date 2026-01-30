<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Customer;
use App\Models\Supplier;
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

            $trxType = '';
            $trxId = 'TRX-' . time() . rand(100, 999);

            // --- Case A: Customer Paying Due (In) ---
            if ($request->type === 'customer_pay') {
                $trxType = 'credit';

                $transaction = Transaction::create([
                    'trx_id' => $trxId,
                    'type' => 'credit',
                    'customer_id' => $request->customer_id,
                    'amount' => $request->amount,
                    'date' => $request->date,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'created_by' => auth()->id() ?? 1
                ]);

                Customer::where('id', $request->customer_id)->decrement('balance', $request->amount);
            }

            // --- Case B: Payment to Supplier (Out) ---
            elseif ($request->type === 'supplier_pay') {
                $trxType = 'debit';

                $transaction = Transaction::create([
                    'trx_id' => $trxId,
                    'type' => 'debit',
                    'supplier_id' => $request->supplier_id,
                    'amount' => $request->amount,
                    'date' => $request->date,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'created_by' => auth()->id() ?? 1
                ]);

                Supplier::where('id', $request->supplier_id)->decrement('balance', $request->amount);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Transaction success & Balance updated',
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
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
