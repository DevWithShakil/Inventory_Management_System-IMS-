<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\Customer;
use App\Models\Expense;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Main Dashboard Overview API
     * Handles logic for Admin vs Staff views
     */
    public function dashboardOverview(Request $request)
{
    try {
        $user = auth()->user();
        $range = $request->query('range', 'today');

        // --- 1. Date Logic ---
        switch ($range) {
            case 'today':
                $startDate = Carbon::today();
                $endDate = Carbon::today()->endOfDay();
                break;
            case 'yesterday':
                $startDate = Carbon::yesterday();
                $endDate = Carbon::yesterday()->endOfDay();
                break;
            case 'last_7_days':
                $startDate = Carbon::now()->subDays(6)->startOfDay();
                $endDate = Carbon::now()->endOfDay();
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
                break;
            case 'all_time':
                $startDate = Carbon::create(2000, 1, 1);
                $endDate = Carbon::now()->endOfDay();
                break;
            default:
                $startDate = Carbon::today();
                $endDate = Carbon::today()->endOfDay();
        }

        $startStr = $startDate->format('Y-m-d H:i:s');
        $endStr = $endDate->format('Y-m-d H:i:s');

        // --- 2. Base Metrics (Visible to EVERYONE) ---

        // Gross Sales
        $grossSales = Sale::whereBetween('date', [$startStr, $endStr])->sum('grand_total');

        // 🔥 Total Discount (Newly Added)
        $totalDiscount = Sale::whereBetween('date', [$startStr, $endStr])->sum('discount');

        // Total Refunds
        $totalRefunds = SalesReturn::whereBetween('date', [$startStr, $endStr])->sum('refund_amount');

        // Net Sales
        $netSales = $grossSales - $totalRefunds;

        // Invoice Counts & Payment Breakdown
        $invoiceCount = Sale::whereBetween('date', [$startStr, $endStr])->count();

        $cashSale = Sale::whereBetween('date', [$startStr, $endStr])
                        ->where('payment_method', 'cash')->sum('paid_amount');

        $digitalSale = Sale::whereBetween('date', [$startStr, $endStr])
                           ->where('payment_method', '!=', 'cash')->sum('paid_amount');

        // Calculate Range Specific Due
        $totalCollectedInRange = $cashSale + $digitalSale;
        $rangeDue = max(0, $netSales - $totalCollectedInRange);

        // --- 3. Sensitive Metrics (Profit, Cost, Expense) - ADMIN ONLY ---
        $actualCOGS = 0;
        $totalExpenses = 0;
        $netProfit = 0;
        $badReturnCost = 0;
        $purchases = 0;
        $inventoryValue = 0;
        $damagedValue = 0;

        if ($user->role === 'admin') {
            // A. COGS Calculation
            $totalSoldCost = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.date', [$startStr, $endStr])
                ->sum(DB::raw('sale_items.quantity * products.cost_price'));

            $goodReturnCost = DB::table('sales_return_items')
                ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
                ->join('products', 'products.id', '=', 'sales_return_items.product_id')
                ->whereBetween('sales_returns.date', [$startStr, $endStr])
                ->where('sales_return_items.return_condition', 'good')
                ->sum(DB::raw('sales_return_items.quantity * products.cost_price'));

            $badReturnCost = DB::table('sales_return_items')
                ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
                ->join('products', 'products.id', '=', 'sales_return_items.product_id')
                ->whereBetween('sales_returns.date', [$startStr, $endStr])
                ->where('sales_return_items.return_condition', 'bad')
                ->sum(DB::raw('sales_return_items.quantity * products.cost_price'));

            $actualCOGS = $totalSoldCost - $goodReturnCost;

            // B. Expenses
            $totalExpenses = Expense::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                                    ->sum('amount');

            // C. Net Profit
            $netProfit = ($netSales - $actualCOGS) - $totalExpenses;

            // D. Overall Assets
            $inventoryValue = Product::sum(DB::raw('stock_quantity * cost_price'));
            $damagedValue = Product::sum(DB::raw('damaged_quantity * cost_price'));
            $purchases = Purchase::whereBetween('date', [$startStr, $endStr])->sum('grand_total');
        }

        // --- 4. Chart Data ---
        $chartData = $this->generateChartData($startDate, $endDate, $range, $user->role);

        // --- 5. Overall Stats ---
        $overallStats = [
            'total_due' => round(Sale::sum('due_amount'), 2),
            'total_collected' => round(Sale::sum('paid_amount'), 2),
        ];

        if ($user->role === 'admin') {
            $overallStats['inventory_value'] = round($inventoryValue, 2);
            $overallStats['damaged_stock_value'] = round($damagedValue, 2);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'role' => $user->role,
                'metrics' => [
                    'range_gross_sales' => round($grossSales, 2),
                    'range_sales' => round($netSales, 2),
                    'range_returns' => round($totalRefunds, 2),

                    // 🔥 Discount Added Here
                    'range_discount' => round($totalDiscount, 2),

                    'range_count' => $invoiceCount,
                    'range_cash' => round($cashSale, 2),
                    'range_digital' => round($digitalSale, 2),
                    'range_due' => round($rangeDue, 2),

                    // Admin Only (0 for staff)
                    'range_profit' => round($netProfit, 2),
                    'range_expenses' => round($totalExpenses, 2),
                    'range_cogs' => round($actualCOGS, 2),
                    'range_damaged_loss' => round($badReturnCost, 2),
                    'range_purchases' => round($purchases, 2),
                ],
                'overall' => $overallStats,
                'inventory' => [
                    'total_products' => Product::count(),
                    'low_stock' => Product::whereColumn('stock_quantity', '<=', 'alert_quantity')->count(),
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

    // --- Helpers ---

    public function lowStockReport()
    {
        $products = Product::whereColumn('stock_quantity', '<=', 'alert_quantity')
            ->with(['category', 'brand'])
            ->get();
        return response()->json(['status' => true, 'data' => $products]);
    }

    public function dailySalesReport(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        $report = Sale::whereBetween('date', [$startDate, $endDate])
            ->selectRaw('date, COUNT(*) as total_orders, SUM(grand_total) as total_sales, SUM(paid_amount) as total_received, SUM(due_amount) as total_due')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return response()->json(['status' => true, 'data' => $report]);
    }

    private function getTopProducts($startDate, $endDate) {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
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

    private function generateChartData($startDate, $endDate, $range, $role)
    {
        $categories = [];
        $netRevenues = [];
        $netCosts = [];

        $driver = DB::connection()->getDriverName();

        // Determine Date Format based on DB Driver and Range
        if ($range === 'today' || $range === 'yesterday') {
             // For hourly data, we group by hour
             $dateFormat = ($driver === 'pgsql') ? "EXTRACT(HOUR FROM created_at)" : "EXTRACT(HOUR FROM created_at)";
        } else {
             // For daily data
             $dateFormat = ($driver === 'pgsql') ? "CAST(date AS DATE)" : "DATE(date)";
        }

        // 1. Fetch Sales Data
        $salesData = Sale::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->selectRaw("$dateFormat as key_date, SUM(grand_total) as total")
            ->groupBy('key_date')
            ->pluck('total', 'key_date');

        // 2. Fetch Refunds Data
        $refundData = SalesReturn::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->selectRaw("$dateFormat as key_date, SUM(refund_amount) as total")
            ->groupBy('key_date')
            ->pluck('total', 'key_date');

        // 3. Fetch Cost Data (Only if Admin)
        $costData = collect([]);
        $restockData = collect([]);

        if ($role === 'admin') {
            $costDateCol = ($range === 'today' || $range === 'yesterday') ? 'sales.created_at' : 'sales.date';

            // Need to handle DB driver specific formatting for joins
            if ($range === 'today' || $range === 'yesterday') {
                 $costDateFormat = ($driver === 'pgsql') ? "EXTRACT(HOUR FROM $costDateCol)" : "EXTRACT(HOUR FROM $costDateCol)";
            } else {
                 $costDateFormat = ($driver === 'pgsql') ? "CAST($costDateCol AS DATE)" : "DATE($costDateCol)";
            }

            $costData = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->selectRaw("$costDateFormat as key_date, SUM(sale_items.quantity * products.cost_price) as total")
                ->groupBy('key_date')
                ->pluck('total', 'key_date');
        }

        // --- Data Filling Logic ---

        if ($range === 'today' || $range === 'yesterday') {
            // Hourly Loop (0 to 23)
            for ($i = 0; $i <= 23; $i++) {
                $categories[] = Carbon::createFromTime($i, 0)->format('h A');

                $gross = $salesData[(int)$i] ?? 0;
                $refund = $refundData[(int)$i] ?? 0;
                $netRevenues[] = max(0, $gross - $refund);

                if ($role === 'admin') {
                    $cogs = $costData[(int)$i] ?? 0;
                    $netCosts[] = $cogs;
                }
            }
        } elseif ($range === 'all_time') {
            // Simplify All Time to last 12 months for better chart view
             $months = Sale::selectRaw("DATE_FORMAT(date, '%Y-%m') as key_date")
                            ->groupBy('key_date')
                            ->orderBy('key_date', 'asc')
                            ->limit(12)
                            ->pluck('key_date');

             // This part can be complex for copy-paste, so defaulting to date loop logic for safety
             // or you can stick to a simpler logic.
             // Let's use the Period Loop as it's safer for "Last 7 days", "This Month" etc.
             $period = CarbonPeriod::create($startDate, $endDate);
             foreach ($period as $date) {
                $dayKey = $date->format('Y-m-d');
                $categories[] = $date->format('d M');

                $gross = $salesData[$dayKey] ?? 0;
                $refund = $refundData[$dayKey] ?? 0;
                $netRevenues[] = max(0, $gross - $refund);

                if ($role === 'admin') {
                    $cogs = $costData[$dayKey] ?? 0;
                    $netCosts[] = $cogs;
                }
             }

        } else {
            // Standard Date Loop (Last 7 days, This Month, etc)
            $period = CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $date) {
                $dayKey = $date->format('Y-m-d');
                $categories[] = $date->format('d M');

                $gross = $salesData[$dayKey] ?? 0;
                $refund = $refundData[$dayKey] ?? 0;
                $netRevenues[] = max(0, $gross - $refund);

                if ($role === 'admin') {
                    // For daily data, key is Y-m-d
                    $cogs = $costData[$dayKey] ?? 0;
                    $netCosts[] = $cogs;
                }
            }
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Revenue', 'data' => $netRevenues],
                ($role === 'admin' ? ['name' => 'Product Cost', 'data' => $netCosts] : [])
            ]
        ];
    }
}
