<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Purchase;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Product;
use Illuminate\Support\Facades\DB;


class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $purchase = Purchase::with(['supplier', 'items.product', 'creator'])->latest()->get();

            return response()->json([
                'status' => true,
                'message' => 'Purchases retrieved successfully',
                'data' => $purchase
            ]);
        } catch (\Exception $e) {
            Log::error('Purchase Fetch Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Purchase not found.'
            ], 404);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            // 🔥 নতুন ভ্যালিডেশন
            'paid_amount' => 'nullable|numeric|min:0'
        ]);

        try {
            DB::beginTransaction();

            // ১. সাবটোটাল ক্যালকুলেশন (আপনার আগের কোড)
            $subtotal = 0;
            foreach ($request->items as $item) {
                $subtotal += $item['quantity'] * $item['unit_cost'];
            }

            $discount = $request->discount ?? 0;
            $tax = $request->tax ?? 0;

            // গ্র্যান্ড টোটাল
            $grandTotal = ($subtotal + $tax) - $discount;

            // ২. 🔥 পেমেন্ট এবং ডিউ ক্যালকুলেশন (নতুন লজিক)
            $paidAmount = $request->paid_amount ?? 0;
            $dueAmount = $grandTotal - $paidAmount;

            // পেমেন্ট স্ট্যাটাস নির্ধারণ
            $paymentStatus = 'due';
            if ($dueAmount <= 0) {
                $paymentStatus = 'paid';
                $dueAmount = 0; // নেগেটিভ ডিউ এড়াতে
            } elseif ($paidAmount > 0) {
                $paymentStatus = 'partial';
            }

            // ৩. পারচেজ তৈরি (আপডেট করা হয়েছে)
            $purchase = Purchase::create([
                'supplier_id' => $request->supplier_id,
                'date' => $request->date,
                'reference_no' => $request->reference_no,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,       // 🔥 New
                'due_amount' => $dueAmount,         // 🔥 New
                'payment_status' => $paymentStatus, // 🔥 New
                'status' => 'received', // বা 'completed' আপনার ডিফল্ট অনুযায়ী
                'created_by' => auth()->id() ?? 1,
            ]);

            // ৪. আইটেম তৈরি এবং স্টক আপডেট (আপনার আগের কোড - অপরিবর্তিত)
            foreach ($request->items as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'subtotal' => $item['quantity'] * $item['unit_cost']
                ]);

                // প্রোডাক্ট স্টক এবং কস্ট প্রাইস আপডেট
                $product = Product::find($item['product_id']);
                $product->increment('stock_quantity', $item['quantity']);
                $product->update(['cost_price' => $item['unit_cost']]);
            }

            // ৫. 🔥 সাপ্লায়ার ব্যালেন্স আপডেট (যদি বাকি থাকে)
            if ($dueAmount > 0) {
                Supplier::where('id', $request->supplier_id)->increment('balance', $dueAmount);
            }

            // ৬. 🔥 ট্রানজেকশন রেকর্ড (যদি পেমেন্ট করা হয়)
            if ($paidAmount > 0) {
                Transaction::create([
                    'trx_id' => 'TRX-' . time() . rand(1000,9999),
                    'type' => 'debit', // টাকা যাচ্ছে (Supplier Payment)
                    'supplier_id' => $request->supplier_id,
                    'purchase_id' => $purchase->id, // লিংক করা হলো
                    'amount' => $paidAmount,
                    'date' => $request->date,
                    'payment_method' => 'cash', // ডিফল্ট বা রিকোয়েস্ট থেকে নিতে পারেন
                    'note' => 'Paid during purchase creation',
                    'created_by' => auth()->id() ?? 1
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Purchase created, stock updated & payment recorded successfully',
                'data' => $purchase
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase Creation Error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $purchase = Purchase::with(['supplier', 'items.product'])->find($id);
            if (!$purchase) {
                return response()->json([
                    'status' => false,
                    'message' => 'Purchase not found.'
                ], 404);
            }
            return response()->json([
                'status' => true,
                'message' => 'Purchase retrieved successfully',
                'data' => $purchase
            ]);

        } catch (\Exception $e) {
            Log::error('Purchase Fetch Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Purchase not found.'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
