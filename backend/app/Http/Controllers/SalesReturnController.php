<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesReturnController extends Controller
{
    public function index(Request $request)
{
    $returns = SalesReturn::with(['sale', 'customer', 'user'])
                ->latest()
                ->paginate(15);
    return response()->json([
        'status' => true,
        'data' => $returns
    ]);
}

    public function store(Request $request)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.return_condition' => 'required|in:good,bad',
        ]);

        try {
            DB::beginTransaction();

            $sale = Sale::find($request->sale_id);

            $totalRefundAmount = 0;
            $returnedOriginalValue = 0;
            $itemsToInsert = [];

            foreach ($request->items as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $totalRefundAmount += $lineTotal;

                $originalSaleItem = SaleItem::where('sale_id', $sale->id)
                                            ->where('product_id', $item['product_id'])
                                            ->first();

                if ($originalSaleItem) {
                    $returnedOriginalValue += ($originalSaleItem->unit_price * $item['quantity']);
                }

                $itemsToInsert[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $lineTotal,
                    'return_condition' => $item['return_condition']
                ];
            }
            $finalRefundAmount = $totalRefundAmount - ($request->deduction_amount ?? 0);

            $salesReturn = SalesReturn::create([
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'return_no' => 'RET-' . time(),
                'date' => $request->date,
                'total_amount' => $totalRefundAmount,
                'deduction_amount' => $request->deduction_amount ?? 0,
                'refund_amount' => $finalRefundAmount,
                'note' => $request->note,
                'created_by' => auth()->id(),
            ]);

            foreach ($itemsToInsert as $itemData) {
                SalesReturnItem::create([
                    'sales_return_id' => $salesReturn->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemData['subtotal'],
                    'return_condition' => $itemData['return_condition']
                ]);

                if ($itemData['return_condition'] === 'good') {
                    Product::where('id', $itemData['product_id'])
                        ->increment('stock_quantity', $itemData['quantity']);
                } else {
                    Product::where('id', $itemData['product_id'])
                        ->increment('damaged_quantity', $itemData['quantity']);
                }
            }

            if ($sale->customer_id) {
                $customer = Customer::find($sale->customer_id);
                $noteUpdate = "";

                if ($customer) {
                    $customer->decrement('total_spent', $finalRefundAmount);

                    if (!empty($sale->redeemed_points) && $sale->redeemed_points > 0) {
                        $originalSubtotal = $sale->subtotal;

                        if ($originalSubtotal > 0) {
                            $returnRatio = $returnedOriginalValue / $originalSubtotal;
                            $pointsToRestore = round($sale->redeemed_points * $returnRatio);

                            if ($pointsToRestore > 0) {
                                $customer->increment('reward_points', $pointsToRestore);
                                $noteUpdate .= " (Restored {$pointsToRestore} used pts)";
                            }
                        }
                    }
                    $pointsEarnedOriginally = floor($totalRefundAmount / 100);

                    if ($pointsEarnedOriginally > 0) {
                        if ($customer->reward_points >= $pointsEarnedOriginally) {
                            $customer->decrement('reward_points', $pointsEarnedOriginally);
                            $noteUpdate .= " (Deducted {$pointsEarnedOriginally} earned pts)";
                        } else {
                            $customer->update(['reward_points' => 0]);
                            $noteUpdate .= " (Reset points to 0)";
                        }
                    }
                }

                if (!empty($noteUpdate)) {
                    $salesReturn->update([
                        'note' => $salesReturn->note . $noteUpdate
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Return Processed! Inventory updated based on condition.',
                'data' => $salesReturn
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Return Failed: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function returnHistory()
{
    $returns = SalesReturn::with(['user', 'sale', 'sale.customer'])
                ->latest()
                ->paginate(20);

    return response()->json([
        'status' => true,
        'data' => $returns
    ]);
}
}
