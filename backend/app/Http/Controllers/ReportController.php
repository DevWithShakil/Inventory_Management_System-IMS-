<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    public function dashboardOverview(Request $request)
    {
        try {
            $range = $request->query('range', 'today');

            $startDate = null;
            $endDate = Carbon::now()->endOfDay();
            $previousStartDate = null;
            $previousEndDate = null;

            switch ($range) {
                case 'today':
                    $startDate = Carbon::today();
                    $previousStartDate = Carbon::yesterday();
                    $previousEndDate = Carbon::yesterday()->endOfDay();
                    break;
                case 'yesterday':
                    $startDate = Carbon::yesterday();
                    $endDate = Carbon::yesterday()->endOfDay();
                    $previousStartDate = Carbon::today()->subDays(2);
                    $previousEndDate = Carbon::today()->subDays(2)->endOfDay();
                    break;
                case 'last_7_days':
                    $startDate = Carbon::now()->subDays(6)->startOfDay();
                    $previousStartDate = Carbon::now()->subDays(13)->startOfDay();
                    $previousEndDate = Carbon::now()->subDays(7)->endOfDay();
                    break;
                case 'this_month':
                    $startDate = Carbon::now()->startOfMonth();
                    $previousStartDate = Carbon::now()->subMonth()->startOfMonth();
                    $previousEndDate = Carbon::now()->subMonth()->endOfMonth();
                    break;
                case 'last_month':
                    $startDate = Carbon::now()->subMonth()->startOfMonth();
                    $endDate = Carbon::now()->subMonth()->endOfMonth();
                    $previousStartDate = Carbon::now()->subMonths(2)->startOfMonth();
                    $previousEndDate = Carbon::now()->subMonths(2)->endOfMonth();
                    break;
                case 'all_time':
                    $startDate = Carbon::create(2000, 1, 1);
                    break;
                default:
                    $startDate = Carbon::today();
            }

            // --- MAIN METRICS ---
            $totalRevenue = Sale::whereBetween('date', [$startDate, $endDate])->sum('grand_total');
            $totalOrders = Sale::whereBetween('date', [$startDate, $endDate])->count();

            // 🔥 Total Discount Calculation
            $totalDiscount = Sale::whereBetween('date', [$startDate, $endDate])->sum('discount');

            // Cash Flow
            $totalPaid = Sale::whereBetween('date', [$startDate, $endDate])->sum('paid_amount');
            $totalDue = Sale::whereBetween('date', [$startDate, $endDate])->sum('due_amount');

            // Expense & Profit
            $costOfGoodsSold = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.date', [$startDate, $endDate])
                ->sum(DB::raw('sale_items.quantity * products.cost_price'));

            $netProfit = $totalRevenue - $costOfGoodsSold;

            // Growth Calculation
            $previousRevenue = Sale::whereBetween('date', [$previousStartDate, $previousEndDate])->sum('grand_total');
            $growth = 0;
            if ($previousRevenue > 0) {
                $growth = (($totalRevenue - $previousRevenue) / $previousRevenue) * 100;
            } else {
                $growth = $totalRevenue > 0 ? 100 : 0;
            }

            // Pie Chart Data
            $payment_methods = Sale::whereBetween('date', [$startDate, $endDate])
                ->select('payment_method', DB::raw('count(*) as count'))
                ->groupBy('payment_method')
                ->pluck('count', 'payment_method');

            // Low Stock
            $lowStockProducts = Product::whereColumn('stock_quantity', '<=', 'alert_quantity')
                ->select('id', 'name', 'stock_quantity', 'alert_quantity', 'image', 'sku')
                ->take(5)->get();

            // Top Products
            $topProducts = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.date', [$startDate, $endDate])
                ->select('products.name', 'products.image', 'products.stock_quantity', DB::raw('SUM(sale_items.quantity) as total_sold'))
                ->groupBy('products.id', 'products.name', 'products.image', 'products.stock_quantity')
                ->orderByDesc('total_sold')
                ->limit(5)->get();

            // Recent Sales
            $recentSales = Sale::with('customer')->latest()->limit(6)->get();

            // Chart Data
            $chartData = $this->generateChartData($startDate, $endDate, $range);

            return response()->json([
                'status' => true,
                'data' => [
                    'metrics' => [
                        'revenue'  => $totalRevenue,
                        'orders'   => $totalOrders,
                        'expense'  => $costOfGoodsSold, // 🔥 Fixed Variable Name
                        'profit'   => $netProfit,
                        'discount' => $totalDiscount,   // 🔥 Ensure this is sent
                        'paid'     => $totalPaid,
                        'due'      => $totalDue,
                        'growth'   => round($growth, 1)
                    ],
                    'pie_chart' => [
                        'labels' => $payment_methods->keys(),
                        'series' => $payment_methods->values()
                    ],
                    'low_stock'    => $lowStockProducts,
                    'top_products' => $topProducts,
                    'recent_sales' => $recentSales,
                    'chart'        => $chartData,
                    'filter_label' => ucfirst(str_replace('_', ' ', $range))
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ... (generateChartData, lowStockReport, dailySalesReport মেথডগুলো একই থাকবে) ...
    // নিচের অংশটুকু আপনার আগের কোড থেকেই থাকবে, তাই এখানে রিপিট করলাম না।
    // শুধু dashboardOverview মেথডটা রিপ্লেস করলেই হবে।

    private function generateChartData($startDate, $endDate, $range)
    {
        $categories = [];
        $revenues = [];
        $costs = [];

        if ($range === 'today') {
            $salesData = Sale::whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('EXTRACT(HOUR FROM created_at) as hour, SUM(grand_total) as total')
                ->groupBy('hour')->pluck('total', 'hour');

            $costData = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.created_at', [$startDate, $endDate])
                ->selectRaw('EXTRACT(HOUR FROM sales.created_at) as hour, SUM(sale_items.quantity * products.cost_price) as total_cost')
                ->groupBy('hour')->pluck('total_cost', 'hour');

            for ($i = 0; $i <= 23; $i++) {
                $categories[] = Carbon::createFromTime($i, 0)->format('h A');
                $revenues[] = $salesData[(int)$i] ?? 0;
                $costs[] = $costData[(int)$i] ?? 0;
            }
        } elseif ($range === 'all_time' || Carbon::parse($startDate)->diffInDays($endDate) > 90) {
             $salesData = Sale::whereBetween('date', [$startDate, $endDate])
                ->selectRaw("TO_CHAR(date, 'YYYY-MM') as month, SUM(grand_total) as total")
                ->groupBy('month')->pluck('total', 'month');

            $costData = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.date', [$startDate, $endDate])
                ->selectRaw("TO_CHAR(sales.date, 'YYYY-MM') as month, SUM(sale_items.quantity * products.cost_price) as total_cost")
                ->groupBy('month')->pluck('total_cost', 'month');

            $period = CarbonPeriod::create($startDate, '1 month', $endDate);
            foreach ($period as $date) {
                $monthKey = $date->format('Y-m');
                $categories[] = $date->format('M Y');
                $revenues[] = $salesData[$monthKey] ?? 0;
                $costs[] = $costData[$monthKey] ?? 0;
            }
        } else {
            $period = CarbonPeriod::create($startDate, $endDate);
            $salesData = Sale::whereBetween('date', [$startDate, $endDate])
                ->selectRaw('CAST(date AS DATE) as day, SUM(grand_total) as total')
                ->groupBy('day')->pluck('total', 'day');

            $costData = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.date', [$startDate, $endDate])
                ->selectRaw('CAST(sales.date AS DATE) as day, SUM(sale_items.quantity * products.cost_price) as total_cost')
                ->groupBy('day')->pluck('total_cost', 'day');

            foreach ($period as $date) {
                $formatDate = $date->format('Y-m-d');
                $categories[] = $date->format('d M');
                $revenues[] = $salesData[$formatDate] ?? 0;
                $costs[] = $costData[$formatDate] ?? 0;
            }
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Revenue', 'data' => $revenues],
                ['name' => 'Cost of Sales', 'data' => $costs]
            ]
        ];
    }
}
