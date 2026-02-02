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
use App\Library\SslCommerz\SslCommerzNotification;

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

                // 🔥 SSLCommerz Integration Logic (NEW)
                // If payment method is NOT cash, initiate online payment
                if ($request->payment_method !== 'cash') {

                    $trxId = 'TRX-' . time() . rand(100, 999);

                    $post_data = array();
                    $post_data['total_amount'] = $request->amount;
                    $post_data['currency'] = "BDT";
                    $post_data['tran_id'] = $trxId;

                    // Reuse existing routes in SslCommerzController
                    // Make sure these named routes exist in api.php or use url() helper
                    $post_data['success_url'] = route('ssl.success');
                    $post_data['fail_url'] = route('ssl.fail');
                    $post_data['cancel_url'] = route('ssl.cancel');

                    # Customer Info
                    $post_data['cus_name'] = $customer->name;
                    $post_data['cus_email'] = $customer->email ?? 'customer@example.com';
                    $post_data['cus_add1'] = $customer->address ?? 'Dhaka';
                    $post_data['cus_add2'] = "";
                    $post_data['cus_city'] = "";
                    $post_data['cus_state'] = "";
                    $post_data['cus_postcode'] = "";
                    $post_data['cus_country'] = "Bangladesh";
                    $post_data['cus_phone'] = $customer->phone;
                    $post_data['cus_fax'] = "";

                    # Shipment (Required fields)
                    $post_data['ship_name'] = "Store Test";
                    $post_data['ship_add1'] = "Dhaka";
                    $post_data['ship_add2'] = "Dhaka";
                    $post_data['ship_city'] = "Dhaka";
                    $post_data['ship_state'] = "Dhaka";
                    $post_data['ship_postcode'] = "1000";
                    $post_data['ship_country'] = "Bangladesh";

                    $post_data['shipping_method'] = "NO";
                    $post_data['product_name'] = "Due Payment";
                    $post_data['product_category'] = "Due Collection";
                    $post_data['product_profile'] = "general";

                    # 🔥 Custom Parameters (Sent to Success Callback)
                    // value_a identifies this as a "due_collection" payment vs a POS sale
                    $post_data['value_a'] = 'due_collection';
                    $post_data['value_b'] = $request->customer_id; // Customer ID
                    $post_data['value_c'] = $request->note; // Note
                    $post_data['value_d'] = auth()->id() ?? 1; // Staff ID

                    // Call SSLCommerz
                    $sslc = new SslCommerzNotification();
                    $payment_options = $sslc->makePayment($post_data, 'checkout', 'json');

                    if (!is_array($payment_options)) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Invalid Payment Configuration'
                        ], 400);
                    }

                    // Return URL to frontend for redirection
                    return response()->json([
                        'status' => true,
                        'gateway_url' => $payment_options['GatewayPageURL'] ?? $payment_options['url'],
                        'direct_payment' => false
                    ]);
                }

                // ---------------------------------------------------------
                // CASH Payment Logic (Immediate Execution)
                // ---------------------------------------------------------

                DB::beginTransaction();
                $trxId = 'TRX-' . time() . rand(100, 999);

                $customer->decrement('balance', $request->amount);

                // Helper function to clear invoices logic
                $clearedInvoices = $this->processInvoiceClearing($request->customer_id, $request->amount);

                $transaction = Transaction::create([
                    'trx_id' => $trxId,
                    'type' => 'credit',
                    'customer_id' => $request->customer_id,
                    'amount' => $request->amount,
                    'date' => $request->date,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'meta_data' => $clearedInvoices,
                    'created_by' => auth()->id() ?? 1
                ]);

                DB::commit();
                $transaction->load(['customer']);

                return response()->json([
                    'status' => true,
                    'message' => 'Payment recorded successfully',
                    'data' => $transaction
                ]);
            }

            // ==========================================
            // Case B: Supplier Payment (Debit)
            // ==========================================
            elseif ($request->type === 'supplier_pay') {
                DB::beginTransaction();
                $trxId = 'TRX-' . time() . rand(100, 999);

                $supplier = Supplier::findOrFail($request->supplier_id);

                if ($request->amount > $supplier->balance) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Amount exceeds payable balance (৳ ' . $supplier->balance . ')'
                    ], 422);
                }

                $supplier->decrement('balance', $request->amount);

                // Process Purchase Clearing logic
                $clearedInvoices = $this->processPurchaseClearing($request->supplier_id, $request->amount);

                $transaction = Transaction::create([
                    'trx_id' => $trxId,
                    'type' => 'debit',
                    'supplier_id' => $request->supplier_id,
                    'amount' => $request->amount,
                    'date' => $request->date,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,
                    'meta_data' => $clearedInvoices,
                    'created_by' => auth()->id() ?? 1
                ]);

                DB::commit();
                $transaction->load(['supplier']);

                return response()->json([
                    'status' => true,
                    'message' => 'Payment recorded successfully',
                    'data' => $transaction
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Transaction Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Helper: Logic to clear sales invoices
    private function processInvoiceClearing($customerId, $amount) {
        $unpaidSales = Sale::where('customer_id', $customerId)
            ->where('payment_status', '!=', 'paid')
            ->orderBy('date', 'asc')
            ->get();

        $remainingPayment = $amount;
        $clearedInvoices = [];

        foreach ($unpaidSales as $sale) {
            if ($remainingPayment <= 0) break;

            $due = $sale->due_amount;
            $pay = ($remainingPayment >= $due) ? $due : $remainingPayment;

            $sale->update([
                'paid_amount' => $sale->paid_amount + $pay,
                'due_amount' => $sale->due_amount - $pay,
                'payment_status' => ($remainingPayment >= $due) ? 'paid' : 'partial'
            ]);

            $remainingPayment -= $pay;

            $clearedInvoices[] = [
                'invoice_no' => $sale->invoice_no ?? ('INV-'.$sale->id),
                'date' => $sale->date,
                'amount' => $pay
            ];
        }
        return $clearedInvoices;
    }

    // Helper: Logic to clear purchase invoices
    private function processPurchaseClearing($supplierId, $amount) {
        $unpaidPurchases = Purchase::where('supplier_id', $supplierId)
            ->where('payment_status', '!=', 'paid')
            ->orderBy('date', 'asc')
            ->get();

        $remainingPayment = $amount;
        $clearedInvoices = [];

        foreach ($unpaidPurchases as $purchase) {
            if ($remainingPayment <= 0) break;

            $due = $purchase->due_amount;
            $pay = ($remainingPayment >= $due) ? $due : $remainingPayment;

            $purchase->update([
                'paid_amount' => $purchase->paid_amount + $pay,
                'due_amount' => $purchase->due_amount - $pay,
                'payment_status' => ($remainingPayment >= $due) ? 'paid' : 'partial'
            ]);

            $remainingPayment -= $pay;

            $clearedInvoices[] = [
                'invoice_no' => $purchase->reference_no ?? ('PUR-'.$purchase->id),
                'date' => $purchase->date,
                'amount' => $pay
            ];
        }
        return $clearedInvoices;
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
