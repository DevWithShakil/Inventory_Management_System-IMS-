<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Customer;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    // ==========================================
    // 1. DASHBOARD OVERVIEW
    // ==========================================
    public function dashboardOverview(Request $request)
    {
        try {
            $range = $request->query('range', 'today');

            // --- Date Logic ---
            switch ($range) {
                case 'today':
                    $startDate = Carbon::today()->format('Y-m-d');
                    $endDate = Carbon::today()->format('Y-m-d');
                    break;
                case 'yesterday':
                    $startDate = Carbon::yesterday()->format('Y-m-d');
                    $endDate = Carbon::yesterday()->format('Y-m-d');
                    break;
                case 'last_7_days':
                    $startDate = Carbon::now()->subDays(6)->format('Y-m-d');
                    $endDate = Carbon::now()->format('Y-m-d');
                    break;
                case 'this_month':
                    $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
                    $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
                    break;
                case 'last_month':
                    $startDate = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
                    $endDate = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
                    break;
                case 'all_time':
                    $startDate = '2000-01-01';
                    $endDate = Carbon::now()->format('Y-m-d');
                    break;
                default:
                    $startDate = Carbon::today()->format('Y-m-d');
                    $endDate = Carbon::today()->format('Y-m-d');
            }

            // 1. Sales & Returns
            $grossSales = Sale::whereDate('date', '>=', $startDate)->whereDate('date', '<=', $endDate)->sum('grand_total');
            $totalRefunds = SalesReturn::whereDate('date', '>=', $startDate)->whereDate('date', '<=', $endDate)->sum('refund_amount');
            $netSales = $grossSales - $totalRefunds;

            // 2. COGS & Profit
            $totalSoldCost = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereDate('sales.date', '>=', $startDate)
                ->whereDate('sales.date', '<=', $endDate)
                ->sum(DB::raw('sale_items.quantity * products.cost_price'));

            $goodReturnCost = DB::table('sales_return_items')
                ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
                ->join('products', 'products.id', '=', 'sales_return_items.product_id')
                ->whereDate('sales_returns.date', '>=', $startDate)
                ->whereDate('sales_returns.date', '<=', $endDate)
                ->where('sales_return_items.return_condition', 'good')
                ->sum(DB::raw('sales_return_items.quantity * products.cost_price'));

            $badReturnCost = DB::table('sales_return_items')
                ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
                ->join('products', 'products.id', '=', 'sales_return_items.product_id')
                ->whereDate('sales_returns.date', '>=', $startDate)
                ->whereDate('sales_returns.date', '<=', $endDate)
                ->where('sales_return_items.return_condition', 'bad')
                ->sum(DB::raw('sales_return_items.quantity * products.cost_price'));

            $actualCOGS = $totalSoldCost - $goodReturnCost;
            $grossProfit = $netSales - $actualCOGS - $badReturnCost;

            // 3. Other Metrics
            $purchases = Purchase::whereDate('date', '>=', $startDate)->whereDate('date', '<=', $endDate)->sum('grand_total');
            $discounts = Sale::whereDate('date', '>=', $startDate)->whereDate('date', '<=', $endDate)->sum('discount');
            $tax = Sale::whereDate('date', '>=', $startDate)->whereDate('date', '<=', $endDate)->sum('tax');
            $invoiceCount = Sale::whereDate('date', '>=', $startDate)->whereDate('date', '<=', $endDate)->count();

            // Lifetime Stats
            $allTimeSales = Sale::sum('grand_total');
            $allTimeReturns = SalesReturn::sum('refund_amount');
            $inventoryValue = Product::sum(DB::raw('stock_quantity * cost_price'));
            $damagedValue = Product::sum(DB::raw('damaged_quantity * cost_price'));

            return response()->json([
                'status' => true,
                'data' => [
                    'metrics' => [
                        'range_gross_sales' => $grossSales,
                        'range_sales' => $netSales,
                        'range_returns' => $totalRefunds,
                        'range_profit' => $grossProfit,
                        'range_purchases' => $purchases,
                        'range_cogs' => $actualCOGS,
                        'range_damaged_loss' => $badReturnCost,
                        'range_discount' => $discounts,
                        'range_tax' => $tax,
                        'range_count' => $invoiceCount,
                    ],
                    'overall' => [
                        'net_sales' => $allTimeSales - $allTimeReturns,
                        'total_returns' => $allTimeReturns,
                        'total_purchase_spend' => Purchase::sum('grand_total'),
                        'total_due' => Sale::sum('due_amount'),
                        'total_collected' => Sale::sum('paid_amount'),
                        'inventory_value' => $inventoryValue,
                        'damaged_stock_value' => $damagedValue
                    ],
                    'inventory' => [
                        'total_products' => Product::count(),
                        'low_stock' => Product::whereColumn('stock_quantity', '<=', 'alert_quantity')->count(),
                    ],
                    'users' => [
                        'total_customers' => Customer::count()
                    ],
                    'chart' => $this->generateChartData($startDate, $endDate, $range),
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
    // 2. LOW STOCK REPORT (MISSING WAS HERE)
    // ==========================================
    public function lowStockReport()
    {
        $products = Product::whereColumn('stock_quantity', '<=', 'alert_quantity')
            ->select('id', 'name', 'stock_quantity', 'alert_quantity', 'image', 'sku')
            ->get();
        return response()->json(['status' => true, 'data' => $products]);
    }

    // ==========================================
    // 3. DAILY SALES REPORT
    // ==========================================
    public function dailySalesReport(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        $report = Sale::whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
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
            ->whereDate('sales.date', '>=', $startDate)
            ->whereDate('sales.date', '<=', $endDate)
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

        if ($range === 'today') {
            $salesData = Sale::whereDate('date', $startDate)
                ->selectRaw('EXTRACT(HOUR FROM created_at) as hour, SUM(grand_total) as total')
                ->groupBy('hour')->pluck('total', 'hour');

            for ($i = 0; $i <= 23; $i++) {
                $categories[] = Carbon::createFromTime($i, 0)->format('h A');
                $revenues[] = $salesData[(int)$i] ?? 0;
            }
        } else {
            $period = CarbonPeriod::create($startDate, $endDate);
            $salesData = Sale::whereDate('date', '>=', $startDate)
                ->whereDate('date', '<=', $endDate)
                ->selectRaw('DATE(date) as day, SUM(grand_total) as total')
                ->groupBy('day')->pluck('total', 'day');

            foreach ($period as $date) {
                $dayKey = $date->format('Y-m-d');
                $categories[] = $date->format('d M');
                $revenues[] = $salesData[$dayKey] ?? 0;
            }
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Revenue', 'data' => $revenues]
            ]
        ];
    }
}
