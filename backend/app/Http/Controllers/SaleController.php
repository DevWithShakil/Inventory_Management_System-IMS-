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
    public function index(Request $request)
    {
        try {
            $query = Sale::with('customer')
                ->withSum('sales_returns', 'refund_amount')
                ->withCount('sales_returns')
                // 🔥 New: Load return items to check condition
                ->with(['sales_returns.return_items' => function($q) {
                    $q->select('sales_return_id', 'return_condition');
                }])
                ->latest();

            // Search Filter
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

            // Date Filter
            if ($request->start_date && $request->end_date) {
                $query->whereBetween('date', [$request->start_date, $request->end_date]);
            }

            // 🔥 Updated Status Filter Logic
            if ($request->status) {
                if ($request->status === 'returned') {
                    $query->whereHas('sales_returns');
                }
                elseif ($request->status === 'returned_good') {
                    $query->whereHas('sales_returns.return_items', function($q) {
                        $q->where('return_condition', 'good');
                    });
                }
                elseif ($request->status === 'returned_bad') {
                    $query->whereHas('sales_returns.return_items', function($q) {
                        $q->where('return_condition', 'bad'); // or 'damaged'
                    });
                }
                else {
                    $query->where('payment_status', $request->status);
                }
            }

            $sales = $query->paginate(10);

            return response()->json([
                'status' => true,
                'data' => $sales
            ]);
        } catch (\Exception $e) {
            Log::error('Sale Index Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to fetch sales'], 500);
        }
    }

  public function store(Request $request)
{
    $request->validate([
        'customer_id'    => 'nullable|exists:customers,id',
        'redeem_amount'  => 'nullable|integer|min:0',
        'coupon_code'    => 'nullable|string|exists:coupons,code',
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
        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);

            if (!$product || $product->stock_quantity < $item['quantity']) {
                throw new \Exception("Stock error for product: " . ($product->name ?? 'Unknown'));
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
        $pointsUsed = 0;
        $customer = null;

        if ($request->customer_id) {
            $customer = Customer::find($request->customer_id);
            $redeemAmount = $request->redeem_amount ?? 0;

            if ($customer && $redeemAmount > 0) {
                if ($customer->reward_points < $redeemAmount) {
                        throw new \Exception("Insufficient points! You have: " . $customer->reward_points);
                }

                $maxRedeemable = floor($subtotal * 0.25);

                if ($redeemAmount > $maxRedeemable) {
                    throw new \Exception("Max redeemable points: {$maxRedeemable}");
                }

                $pointsUsed = $redeemAmount;
            }
        }

        $coupon = null;
        if ($request->coupon_code) {
            $coupon = Coupon::where('code', $request->coupon_code)->first();

            if (!$coupon) throw new \Exception("Invalid coupon code.");
            if ($coupon->expires_at && now()->gt($coupon->expires_at)) throw new \Exception("Coupon has expired.");
            if ($coupon->usage_limit > 0 && $coupon->used_count >= $coupon->usage_limit) throw new \Exception("Coupon usage limit reached.");
            if ($subtotal < $coupon->min_purchase) throw new \Exception("Min purchase: {$coupon->min_purchase}");
        }


        $couponDiscount = $request->discount ?? 0;
        $totalDiscount = $couponDiscount + $pointsUsed;
        $tax = $request->tax ?? 0;
        $grandTotal = ($subtotal + $tax) - $totalDiscount;

        if ($grandTotal < 0) $grandTotal = 0;

        $dueAmount = $grandTotal - $request->paid_amount;
        $paymentStatus = $dueAmount <= 0 ? 'paid' : ($request->paid_amount > 0 ? 'partial' : 'due');

        $sale = Sale::create([
            'customer_id'    => $request->customer_id,
            'invoice_no'     => 'INV-' . time() . rand(10,99),
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
            'redeemed_points' => $pointsUsed,
        ]);

        if ($dueAmount > 0 && $customer) {
            $customer->increment('balance', $dueAmount);
        }

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

        if ($customer) {
            if ($pointsUsed > 0) {
                $customer->decrement('reward_points', $pointsUsed);
            }

            $newPoints = floor($grandTotal / 100);
            if ($newPoints > 0) {
                $customer->increment('reward_points', $newPoints);
            }
            $customer->increment('total_spent', $grandTotal);
        }
        if ($coupon) $coupon->increment('used_count');

        DB::commit();
        $sale->load(['customer', 'sale_items.product']);

        return response()->json([
            'status' => true,
            'message' => 'Sale created successfully!',
            'data' => $sale
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Sale Store Error: ' . $e->getMessage());
        return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
    }
}

    public function show($id)
    {
        try {
            $sale = Sale::with([
                'customer',
                'sale_items.product',
                'sales_returns.return_items.product'
            ])->find($id);

            if (!$sale) {
                return response()->json(['status' => false, 'message' => 'Invoice not found'], 404);
            }

            return response()->json(['status' => true, 'data' => $sale]);

        } catch (\Exception $e) {
            Log::error('Sale Show Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

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
            Log::error('Sale Destroy Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to delete sale'], 500);
        }
    }
}
