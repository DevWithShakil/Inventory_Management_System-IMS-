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

            $totalRevenue = Sale::whereBetween('date', [$startDate, $endDate])->sum('grand_total');
            $totalOrders = Sale::whereBetween('date', [$startDate, $endDate])->count();

            $costOfGoodsSold = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.date', [$startDate, $endDate])
                ->sum(DB::raw('sale_items.quantity * products.cost_price'));

            $grossProfit = $totalRevenue - $costOfGoodsSold;
            $metricExpense = $costOfGoodsSold;

            $previousRevenue = Sale::whereBetween('date', [$previousStartDate, $previousEndDate])->sum('grand_total');
            $growth = 0;
            if ($previousRevenue > 0) {
                $growth = (($totalRevenue - $previousRevenue) / $previousRevenue) * 100;
            } else {
                $growth = $totalRevenue > 0 ? 100 : 0;
            }

            $lowStockProducts = Product::whereColumn('stock_quantity', '<=', 'alert_quantity')
                ->select('id', 'name', 'stock_quantity', 'alert_quantity', 'image', 'sku')
                ->take(5)
                ->get();

            $topProducts = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.date', [$startDate, $endDate])
                ->select(
                    'products.name',
                    'products.image',
                    'products.stock_quantity',
                    DB::raw('SUM(sale_items.quantity) as total_sold')
                )
                ->groupBy('products.id', 'products.name', 'products.image', 'products.stock_quantity')
                ->orderByDesc('total_sold')
                ->limit(5)
                ->get();

            $recentSales = Sale::with('customer')
                ->latest()
                ->limit(6)
                ->get();

            $chartData = $this->generateChartData($startDate, $endDate, $range);

            return response()->json([
                'status' => true,
                'data' => [
                    'metrics' => [
                        'revenue' => $totalRevenue,
                        'orders' => $totalOrders,
                        'expense' => $metricExpense,
                        'profit' => $grossProfit,
                        'growth' => round($growth, 1)
                    ],
                    'low_stock' => $lowStockProducts,
                    'top_products' => $topProducts,
                    'recent_sales' => $recentSales,
                    'chart' => $chartData,
                    'filter_label' => ucfirst(str_replace('_', ' ', $range))
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function generateChartData($startDate, $endDate, $range)
    {
        $categories = [];
        $revenues = [];
        $costs = [];

        // 🟢 TODAY: Hour by Hour View (Optimized for PostgreSQL)
        if ($range === 'today') {

            // ১. ডাটাবেস থেকে সব ডাটা একবারে নিয়ে আসা (Hour Grouping)
            // আমরা 'date' এর বদলে 'created_at' ব্যবহার করছি কারণ এতে Time থাকে
            $salesData = Sale::whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('EXTRACT(HOUR FROM created_at) as hour, SUM(grand_total) as total')
                ->groupBy('hour')
                ->pluck('total', 'hour');

            $costData = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.created_at', [$startDate, $endDate])
                ->selectRaw('EXTRACT(HOUR FROM sales.created_at) as hour, SUM(sale_items.quantity * products.cost_price) as total_cost')
                ->groupBy('hour')
                ->pluck('total_cost', 'hour');

            // ২. লুপ চালিয়ে ২৪ ঘন্টার ডাটা সাজানো (ডাটাবেস কুয়েরি ছাড়া)
            for ($i = 0; $i <= 23; $i++) {
                // Hour format (Example: 01 PM)
                $categories[] = Carbon::createFromTime($i, 0)->format('h A');

                // যদি এই ঘন্টায় সেল থাকে বসাবে, না থাকলে ০
                $revenues[] = $salesData[(int)$i] ?? 0;
                $costs[] = $costData[(int)$i] ?? 0;
            }
        }
        // 🟡 Monthly View (Large Range)
        elseif ($range === 'all_time' || Carbon::parse($startDate)->diffInDays($endDate) > 90) {

            $salesData = Sale::whereBetween('date', [$startDate, $endDate])
                ->selectRaw("TO_CHAR(date, 'YYYY-MM') as month, SUM(grand_total) as total")
                ->groupBy('month')
                ->pluck('total', 'month');

            $costData = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.date', [$startDate, $endDate])
                ->selectRaw("TO_CHAR(sales.date, 'YYYY-MM') as month, SUM(sale_items.quantity * products.cost_price) as total_cost")
                ->groupBy('month')
                ->pluck('total_cost', 'month');

            $period = CarbonPeriod::create($startDate, '1 month', $endDate);

            foreach ($period as $date) {
                $monthKey = $date->format('Y-m');
                $categories[] = $date->format('M Y');

                $revenues[] = $salesData[$monthKey] ?? 0;
                $costs[] = $costData[$monthKey] ?? 0;
            }
        }
        // 🔵 Daily View (Standard Range)
        else {
            $period = CarbonPeriod::create($startDate, $endDate);

            $salesData = Sale::whereBetween('date', [$startDate, $endDate])
                ->selectRaw('CAST(date AS DATE) as day, SUM(grand_total) as total')
                ->groupBy('day')
                ->pluck('total', 'day');

            $costData = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.date', [$startDate, $endDate])
                ->selectRaw('CAST(sales.date AS DATE) as day, SUM(sale_items.quantity * products.cost_price) as total_cost')
                ->groupBy('day')
                ->pluck('total_cost', 'day');

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

    public function lowStockReport()
    {
        return response()->json([
            'status' => true,
            'data' => Product::whereColumn('stock_quantity', '<=', 'alert_quantity')->get()
        ]);
    }

    public function dailySalesReport(Request $request)
    {
         $date = $request->date ?? Carbon::today();
         $sales = Sale::with(['customer', 'creator'])->whereDate('date', $date)->latest()->get();
         return response()->json(['status' => true, 'data' => $sales]);
    }
}
