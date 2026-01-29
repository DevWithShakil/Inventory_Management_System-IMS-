<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\Customer;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    // ==========================================
    // 1. DASHBOARD OVERVIEW (Complete Logic)
    // ==========================================
    public function dashboardOverview(Request $request)
    {
        try {
            $range = $request->query('range', 'today');
            $startDate = null;
            $endDate = Carbon::now()->endOfDay();

            // --- Date Range Logic ---
            switch ($range) {
                case 'today': $startDate = Carbon::today(); break;
                case 'yesterday':
                    $startDate = Carbon::yesterday();
                    $endDate = Carbon::yesterday()->endOfDay();
                    break;
                case 'last_7_days': $startDate = Carbon::now()->subDays(6)->startOfDay(); break;
                case 'this_month': $startDate = Carbon::now()->startOfMonth(); break;
                case 'last_month':
                    $startDate = Carbon::now()->subMonth()->startOfMonth();
                    $endDate = Carbon::now()->subMonth()->endOfMonth();
                    break;
                case 'all_time': $startDate = Carbon::create(2000, 1, 1); break;
                default: $startDate = Carbon::today();
            }

            // ==========================================
            // 🔥 PERFORMANCE METRICS (Selected Range)
            // ==========================================

            // 1. Sales & Returns
            $rangeGrossSales = Sale::whereBetween('date', [$startDate, $endDate])->sum('grand_total');
            $rangeReturns = SalesReturn::whereBetween('date', [$startDate, $endDate])->sum('refund_amount');
            $rangeNetSales = $rangeGrossSales - $rangeReturns;

            // 2. Purchases (Use 'grand_total' based on your schema)
            $rangePurchases = Purchase::whereBetween('date', [$startDate, $endDate])->sum('grand_total');

            // 3. COGS (Cost of Goods Sold)
            $rangeCOGS = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.date', [$startDate, $endDate])
                ->sum(DB::raw('sale_items.quantity * products.cost_price'));

            // 4. Profit
            $rangeProfit = $rangeNetSales - $rangeCOGS;

            // 5. Activity Breakdown (New Features)
            $rangeDiscount = Sale::whereBetween('date', [$startDate, $endDate])->sum('discount');
            $rangeTax = Sale::whereBetween('date', [$startDate, $endDate])->sum('tax');
            $rangeInvoiceCount = Sale::whereBetween('date', [$startDate, $endDate])->count();

            $rangeCashSale = Sale::whereBetween('date', [$startDate, $endDate])
                                ->where('payment_method', 'cash')
                                ->sum('paid_amount');

            $rangeDigitalSale = Sale::whereBetween('date', [$startDate, $endDate])
                                ->where('payment_method', '!=', 'cash')
                                ->sum('paid_amount');

            // ==========================================
            // 🔥 LIFETIME BUSINESS HEALTH
            // ==========================================
            $totalGrossSales = Sale::sum('grand_total');
            $totalReturns = SalesReturn::sum('refund_amount');
            $totalNetSales = $totalGrossSales - $totalReturns;

            $totalPurchases = Purchase::sum('grand_total');
            $totalDue = Sale::sum('due_amount');
            $totalPaid = Sale::sum('paid_amount');

            // Inventory Value (Asset)
            $currentStockValue = Product::sum(DB::raw('stock_quantity * cost_price'));

            // Counts
            $totalProducts = Product::count();
            $lowStockCount = Product::whereColumn('stock_quantity', '<=', 'alert_quantity')->count();
            $totalCustomers = Customer::count();

            // Chart Data
            $chartData = $this->generateChartData($startDate, $endDate, $range);

            return response()->json([
                'status' => true,
                'data' => [
                    'metrics' => [
                        'range_sales' => $rangeNetSales,
                        'range_returns' => $rangeReturns,
                        'range_profit' => $rangeProfit,
                        'range_purchases' => $rangePurchases,
                        // New Breakdown
                        'range_discount' => $rangeDiscount,
                        'range_tax' => $rangeTax,
                        'range_count' => $rangeInvoiceCount,
                        'range_cash' => $rangeCashSale,
                        'range_digital' => $rangeDigitalSale
                    ],
                    'overall' => [
                        'net_sales' => $totalNetSales,
                        'total_returns' => $totalReturns,
                        'total_purchase_spend' => $totalPurchases,
                        'total_due' => $totalDue,
                        'total_collected' => $totalPaid,
                        'inventory_value' => $currentStockValue
                    ],
                    'inventory' => [
                        'total_products' => $totalProducts,
                        'low_stock' => $lowStockCount,
                    ],
                    'users' => [
                        'total_customers' => $totalCustomers
                    ],
                    'chart' => $chartData,
                    'top_products' => $this->getTopProducts($startDate, $endDate),
                    'low_stock_list' => $this->getLowStockList(),
                    'filter_label' => ucfirst(str_replace('_', ' ', $range))
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 2. LOW STOCK REPORT
    // ==========================================
    public function lowStockReport()
    {
        $products = Product::whereColumn('stock_quantity', '<=', 'alert_quantity')
            ->with(['category', 'brand'])
            ->get();
        return response()->json(['status' => true, 'data' => $products]);
    }

    // ==========================================
    // 3. DAILY SALES REPORT
    // ==========================================
    public function dailySalesReport(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth();
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth();

        $report = Sale::whereBetween('date', [$startDate, $endDate])
            ->selectRaw('date, COUNT(*) as total_orders, SUM(grand_total) as total_sales, SUM(paid_amount) as total_received, SUM(due_amount) as total_due')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return response()->json(['status' => true, 'data' => $report]);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================
    private function getTopProducts($startDate, $endDate) {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.date', [$startDate, $endDate])
            ->select('products.name', 'products.image', 'products.stock_quantity', DB::raw('SUM(sale_items.quantity) as total_sold'))
            ->groupBy('products.id', 'products.name', 'products.image', 'products.stock_quantity')
            ->orderByDesc('total_sold')
            ->limit(5)->get();
    }

    private function getLowStockList() {
        return Product::whereColumn('stock_quantity', '<=', 'alert_quantity')
            ->select('id', 'name', 'stock_quantity', 'alert_quantity', 'image', 'sku')
            ->take(5)->get();
    }

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
