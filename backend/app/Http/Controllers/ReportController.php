<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   /**
     * Display a listing of the resource.
     */
    public function dashboardOverview(Request $request)
    {
        try {
            $range = $request->range ?? '7days';
            $endDate = Carbon::now();
            $startDate = Carbon::now()->subDays(6);
            $dateFormat = 'D';

            if ($range === 'today') {
                $startDate = Carbon::today();
                $endDate = Carbon::now()->endOfDay();
                $dateFormat = 'h:i A';
            } elseif ($range === '30days') {
                $startDate = Carbon::now()->subDays(29);
                $dateFormat = 'd M'; // 12 Jan
            } elseif ($range === 'this_month') {
                $startDate = Carbon::now()->startOfMonth();
                $dateFormat = 'd M';
            }

            $totalRevenue = Sale::sum('grand_total');
            $totalOrders = Sale::count();
            $totalCustomers = Customer::count();

            $currentMonthSales = Sale::whereMonth('created_at', Carbon::now()->month)->sum('grand_total');
            $lastMonthSales = Sale::whereMonth('created_at', Carbon::now()->subMonth()->month)->sum('grand_total');

            $growth = 0;
            if ($lastMonthSales > 0) {
                $growth = (($currentMonthSales - $lastMonthSales) / $lastMonthSales) * 100;
            } else {
                $growth = $currentMonthSales > 0 ? 100 : 0;
            }

            $dates = [];
            $sales = [];
            $orders = [];

            if ($range === 'today') {

                $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            } else {
                $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            }

            foreach ($period as $date) {
                $formattedDate = $date->format('Y-m-d');

                $dailySale = Sale::whereDate('created_at', $formattedDate)->sum('grand_total');
                $dailyOrderCount = Sale::whereDate('created_at', $formattedDate)->count();

                $dates[] = $date->format($dateFormat);
                $sales[] = $dailySale;
                $orders[] = $dailyOrderCount;
            }

            $recentOrders = Sale::with('customer')
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($sale) {
                    return [
                        'id' => '#ORD-' . str_pad($sale->id, 4, '0', STR_PAD_LEFT),
                        'customer' => $sale->customer ? $sale->customer->name : 'Walk-in Customer',
                        'amount' => '৳ ' . number_format($sale->grand_total, 2),
                        'status' => $sale->payment_status ?? 'Completed',
                        'date' => $sale->created_at->diffForHumans()
                    ];
                });


            $lowStockProducts = Product::whereColumn('stock_quantity', '<=', 'alert_quantity')
                ->select('id', 'name', 'stock_quantity', 'alert_quantity', 'image')
                ->take(5)
                ->get();


            $topProducts = DB::table('sale_items')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->select('products.name', 'products.image', DB::raw('SUM(sale_items.quantity) as total_sold'))
                ->groupBy('sale_items.product_id', 'products.name', 'products.image')
                ->orderByDesc('total_sold')
                ->take(5)
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Dashboard data retrieved successfully',
                'data' => [
                    'stats' => [
                        'revenue' => number_format($totalRevenue, 2),
                        'orders' => $totalOrders,
                        'customers' => $totalCustomers,
                        'growth' => round($growth, 1)
                    ],
                    'chart' => [
                        'categories' => $dates,
                        'series' => [
                            ['name' => 'Revenue', 'data' => $sales],
                            ['name' => 'Orders', 'data' => $orders]
                        ]
                    ],
                    'recent_orders' => $recentOrders,
                    'low_stock' => $lowStockProducts,
                    'top_products' => $topProducts
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
