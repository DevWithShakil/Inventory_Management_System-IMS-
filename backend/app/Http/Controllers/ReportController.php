<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function dashboardOverview()
    {
        try {
            $today = Carbon::today();
            $todaySales = Sale::whereDate('date', $today)->sum('grand_total');
            $todayCollection = Sale::whereDate('date', $today)->sum('paid_amount');
            $todayPurchase = Purchase::whereDate('date', $today)->sum('grand_total');
            $totalProducts = Product::count();
            $lowStockCount = Product::whereColumn('stock_quantity', '<=', 'alert_quantity')->count();

            return response()->json([
                'status' => true,
                'message' => 'Dashboard retrieved successfully',
                'data' => [
                    'today_sales' => $todaySales,
                    'today_collection' => $todayCollection,
                    'today_purchase' => $todayPurchase,
                    'total_products' => $totalProducts,
                    'low_stock_count' => $lowStockCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard Fetch Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Error loading dashboard'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function lowStockReport()
    {
        try {
            $products = Product::with(['category', 'unit'])
                ->whereColumn('stock_quantity', '<=', 'alert_quantity')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Low stock report retrieved successfully',
                'data' => $products
            ]);
        } catch (\Exception $e) {
            Log::error('Low Stock Report Fetch Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Error fetching stock report'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function dailySalesReport(Request $request)
    {
        try {
            $date = $request->date ?? Carbon::today();

            $sales = Sale::with(['customer', 'creator'])
                ->whereDate('date', $date)
                ->latest()
                ->get();

            $totalAmount = $sales->sum('grand_total');

            return response()->json([
                'status' => true,
                'meta' => [
                    'date' => $date,
                    'total_sales_amount' => $totalAmount,
                    'total_invoices' => $sales->count()
                ],
                'data' => $sales
            ]);

        } catch (\Exception $e) {
            Log::error('Daily Sales Report Fetch Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Error fetching sales report'], 500);
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
