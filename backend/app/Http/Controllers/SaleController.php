<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Product;
use App\Models\SaleItem;
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
            $q->where('id', 'like', "%$search%")
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
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $subtotal = 0;

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Stock not available for product: " . $product->name);
                }

                $subtotal += $item['quantity'] * $item['unit_price'];
            }

            $discount = $request->discount ?? 0;
            $tax = $request->tax ?? 0;
            $grandTotal = ($subtotal + $tax) - $discount;

            $dueAmount = $grandTotal - $request->paid_amount;
            $paymentStatus = ($dueAmount > 0) ? 'partial' : 'paid';
            if ($dueAmount == $grandTotal) $paymentStatus = 'due';

            $invoiceNo = 'INV-' . time();

            $sale = Sale::create([
                'customer_id' => $request->customer_id,
                'invoice_no' => $invoiceNo,
                'date' => $request->date,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'grand_total' => $grandTotal,
                'paid_amount' => $request->paid_amount,
                'due_amount' => $dueAmount,
                'payment_method' => $request->payment_method,
                'payment_status' => $paymentStatus,
                'created_by' => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price']
                ]);

                $product = Product::find($item['product_id']);
                $product->decrement('stock_quantity', $item['quantity']);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Sale completed successfully',
                'data' => $sale
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sale Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $sale = Sale::with(['customer', 'items.product', 'creator'])->find($id);

            if (!$sale) {
                return response()->json(['status' => false, 'message' => 'Sale not found'], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Sale details retrieved successfully',
                'data' => $sale
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error fetching details'], 500);
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
        try {
            DB::beginTransaction();

            $sale = Sale::with('items')->find($id);

            if (!$sale) {
                return response()->json(['status' => false, 'message' => 'Sale not found'], 404);
            }

            foreach ($sale->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock_quantity', $item->quantity);
                }
            }

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
