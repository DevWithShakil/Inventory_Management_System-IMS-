<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Customer;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Sale::with('customer')->latest();

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_no', 'like', "%$search%")
                  ->orWhereHas('customer', function($c) use ($search) {
                      $c->where('name', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%");
                  });
            });
        }

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        if ($request->status) {
            $query->where('payment_status', $request->status);
        }

        $sales = $query->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $sales
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(Request $request)
    {
        // ১. ভ্যালিডেশন আপডেট (কুপন কোড যোগ করা হয়েছে)
        $request->validate([
            'customer_id'    => 'nullable|exists:customers,id',
            'redeem_amount'  => 'nullable|integer|min:0',
            'coupon_code'    => 'nullable|string|exists:coupons,code', // ✅ কুপন ভ্যালিডেশন
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'paid_amount'    => 'required|numeric|min:0',
            'payment_method' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $subtotal = 0;
            $itemsToInsert = [];

            // ২. সাবটোটাল এবং স্টক চেক
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    throw new \Exception("Product not found ID: " . $item['product_id']);
                }

                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Stock not available for: " . $product->name);
                }

                $lineTotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $lineTotal;

                $itemsToInsert[] = [
                    'product_obj' => $product,
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'subtotal'    => $lineTotal
                ];
            }

            // ৩. রিওয়ার্ড পয়েন্ট লজিক
            $pointsDiscount = 0;
            $customer = null;

            if ($request->customer_id) {
                $customer = Customer::find($request->customer_id);
                $redeemAmount = $request->redeem_amount ?? 0;

                if ($customer && $redeemAmount > 0) {
                    // ব্যালেন্স চেক
                    if ($customer->reward_points < $redeemAmount) {
                        throw new \Exception("Insufficient reward points! Available: " . $customer->reward_points);
                    }

                    // ২৫% লিমিট চেক
                    $limitPercentage = 0.25;
                    $maxRedeemable = floor($subtotal * $limitPercentage);

                    if ($redeemAmount > $maxRedeemable) {
                        throw new \Exception("You can redeem max {$maxRedeemable} points for this order (25% of Total).");
                    }

                    $pointsDiscount = $redeemAmount;
                }
            }

            // ৪. কুপন লজিক (নতুন যোগ করা হয়েছে) 🔥
            $coupon = null;
            if ($request->coupon_code) {
                $coupon = \App\Models\Coupon::where('code', $request->coupon_code)->first();

                // কুপন ভ্যালিডিটি চেক (নিরাপত্তার জন্য ডাবল চেক)
                if (!$coupon) {
                    throw new \Exception("Invalid coupon code.");
                }

                // মেয়াদ চেক
                if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
                    throw new \Exception("Coupon has expired.");
                }

                // ব্যবহারের লিমিট চেক
                if ($coupon->usage_limit > 0 && $coupon->used_count >= $coupon->usage_limit) {
                    throw new \Exception("Coupon usage limit reached.");
                }

                // মিনিমাম পারচেজ চেক
                if ($subtotal < $coupon->min_purchase) {
                    throw new \Exception("Minimum purchase of {$coupon->min_purchase} required for this coupon.");
                }
            }

            // ৫. টোটাল ডিসকাউন্ট ক্যালকুলেশন
            // $request->discount = কুপন অ্যামাউন্ট (ফ্রন্টএন্ড থেকে আসছে)
            $couponDiscount = $request->discount ?? 0;
            $totalDiscount = $couponDiscount + $pointsDiscount;

            $tax = $request->tax ?? 0;
            $grandTotal = ($subtotal + $tax) - $totalDiscount;

            if ($grandTotal < 0) $grandTotal = 0;

            $dueAmount = $grandTotal - $request->paid_amount;

            // পেমেন্ট স্ট্যাটাস
            if ($dueAmount <= 0) {
                $paymentStatus = 'paid';
                $dueAmount = 0;
            } elseif ($request->paid_amount > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'due';
            }

            $invoiceNo = 'INV-' . time() . rand(10,99);

            // ৬. সেল তৈরি
            $sale = Sale::create([
                'customer_id'    => $request->customer_id,
                'invoice_no'     => $invoiceNo,
                'date'           => $request->date ?? now(),
                'subtotal'       => $subtotal,
                'discount'       => $totalDiscount,
                'tax'            => $tax,
                'grand_total'    => $grandTotal,
                'paid_amount'    => $request->paid_amount,
                'due_amount'     => $dueAmount,
                'payment_method' => $request->payment_method,
                'payment_status' => $paymentStatus,
                'created_by'     => auth()->id() ?? 1,
                // 'coupon_code' => $request->coupon_code, // যদি sales টেবিলে coupon_code কলাম থাকে তবে কমেন্ট তুলে দিন
            ]);

            // ৭. আইটেম ইনসার্ট এবং স্টক আপডেট
            foreach ($itemsToInsert as $itemData) {
                SaleItem::create([
                    'sale_id'    => $sale->id,
                    'product_id' => $itemData['product_id'],
                    'quantity'   => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal'   => $itemData['subtotal']
                ]);

                $itemData['product_obj']->decrement('stock_quantity', $itemData['quantity']);
            }

            // ৮. কাস্টমার রিওয়ার্ড পয়েন্ট আপডেট
            if ($customer) {
                // পয়েন্ট রিডিম করলে কেটে নেওয়া
                if ($pointsDiscount > 0) {
                    $customer->decrement('reward_points', $pointsDiscount);
                }

                // নতুন পয়েন্ট যোগ করা
                $newPointsEarned = floor($grandTotal / 100);
                if ($newPointsEarned > 0) {
                    $customer->increment('reward_points', $newPointsEarned);
                }
            }

            // ৯. কুপন ব্যবহার আপডেট (Increment Used Count) 🔥
            if ($coupon) {
                $coupon->increment('used_count');
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Sale created successfully',
                'data'    => $sale->load('customer', 'sale_items.product')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sale Error: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $sale = Sale::with(['customer', 'sale_items.product'])
                ->find($id);

            if (!$sale) {
                return response()->json(['status' => false, 'message' => 'Invoice not found'], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $sale
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $sale = Sale::with('sale_items')->find($id);

            if (!$sale) {
                return response()->json(['status' => false, 'message' => 'Sale not found'], 404);
            }

            foreach ($sale->sale_items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock_quantity', $item->quantity);
                }
            }

            $sale->sale_items()->delete();

            $sale->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Sale deleted and items restocked successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sale Delete Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to delete sale'], 500);
        }
    }
}
