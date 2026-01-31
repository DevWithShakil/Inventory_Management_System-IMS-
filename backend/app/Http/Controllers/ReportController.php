<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\Expense;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Dashboard Overview API
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

        // --- 2. Query Builder (Role Based Filtering) ---

        // Sales Query Base
        $salesQuery = Sale::whereBetween('date', [$startStr, $endStr]);

        // Refunds Query Base
        $refundQuery = SalesReturn::whereBetween('date', [$startStr, $endStr]);

        // 🔥 STAFF FILTER: Metrics এর জন্য
        if ($user->role === 'staff') {
            $salesQuery->where('created_by', $user->id);
            $refundQuery->where('created_by', $user->id);
        }

        // --- 3. Base Metrics Calculation (Visible to EVERYONE) ---

        // Gross Sales (Using clone to reuse the filtered query)
        $grossSales = (clone $salesQuery)->sum('grand_total');

        // Total Discount
        $totalDiscount = (clone $salesQuery)->sum('discount');

        // Total Refunds
        $totalRefunds = $refundQuery->sum('refund_amount');

        // Net Sales (Gross - Returns)
        $netSales = $grossSales - $totalRefunds;

        // Invoice Counts
        $invoiceCount = (clone $salesQuery)->count();

        // Payment Breakdown
        $cashSale = (clone $salesQuery)->where('payment_method', 'cash')->sum('paid_amount');
        $digitalSale = (clone $salesQuery)->where('payment_method', '!=', 'cash')->sum('paid_amount');

        // Calculate Range Specific Due
        $totalCollectedInRange = $cashSale + $digitalSale;
        $rangeDue = max(0, $netSales - $totalCollectedInRange);

        // --- 4. Sensitive Metrics (Profit, Cost, Expense) - ADMIN ONLY ---
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

        // --- 5. Chart Data ---
        $chartData = $this->generateChartData($startDate, $endDate, $range, $user);

        // --- 6. Overall Stats ---
        $overallStats = [
            'total_due' => round(Sale::sum('due_amount'), 2),
            'total_collected' => round(Sale::sum('paid_amount'), 2),
        ];

        if ($user->role === 'admin') {
            $overallStats['inventory_value'] = round($inventoryValue, 2);
            $overallStats['damaged_stock_value'] = round($damagedValue, 2);
        }

        // --- 🔥 7. RECENT SALES (New Section) ---
        // Staff will see their own last 5 sales, Admin will see global last 5 sales
        $recentSalesQuery = Sale::with('customer:id,name')->latest();

        if ($user->role === 'staff') {
            $recentSalesQuery->where('created_by', $user->id);
        }

        $recentSales = $recentSalesQuery->take(5)->get()->map(function($sale) {
            return [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'customer' => $sale->customer ? $sale->customer->name : 'Walk-in Customer',
                'amount' => $sale->grand_total,
                'status' => $sale->payment_status, // e.g., 'paid', 'due'
                'time' => Carbon::parse($sale->created_at)->diffForHumans() // e.g., "5 mins ago"
            ];
        });

        return response()->json([
            'status' => true,
            'data' => [
                'role' => $user->role,
                'metrics' => [
                    'range_gross_sales' => round($grossSales, 2),
                    'range_sales' => round($netSales, 2),
                    'range_returns' => round($totalRefunds, 2),
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
                'top_products' => $this->getTopProducts($startDate, $endDate, $user),

                // 🔥 New Data Added to Response
                'recent_sales' => $recentSales,

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

    private function getTopProducts($startDate, $endDate, $user = null) {
        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

        // Filter Top Products by Staff if needed
        if ($user && $user->role === 'staff') {
            $query->where('sales.created_by', $user->id);
        }

        return $query->select('products.name', 'products.image', 'products.stock_quantity', DB::raw('SUM(sale_items.quantity) as total_sold'))
            ->groupBy('products.id', 'products.name', 'products.image', 'products.stock_quantity')
            ->orderByDesc('total_sold')
            ->limit(5)->get();
    }

    private function getLowStockList() {
        return Product::whereColumn('stock_quantity', '<=', 'alert_quantity')
            ->select('id', 'name', 'stock_quantity', 'alert_quantity', 'image', 'sku')
            ->take(5)->get();
    }

    private function generateChartData($startDate, $endDate, $range, $user)
    {
        $categories = [];
        $netRevenues = [];
        $netCosts = [];

        $driver = DB::connection()->getDriverName();
        $role = $user->role;

        // Date Format Logic
        if ($range === 'today' || $range === 'yesterday') {
             $dateFormat = ($driver === 'pgsql') ? "EXTRACT(HOUR FROM created_at)" : "EXTRACT(HOUR FROM created_at)";
        } else {
             $dateFormat = ($driver === 'pgsql') ? "CAST(date AS DATE)" : "DATE(date)";
        }

        // 1. Fetch Sales Data (Filtered by Role)
        $salesQuery = Sale::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        if ($role === 'staff') {
            $salesQuery->where('created_by', $user->id);
        }
        $salesData = $salesQuery->selectRaw("$dateFormat as key_date, SUM(grand_total) as total")
            ->groupBy('key_date')
            ->pluck('total', 'key_date');

        // 2. Fetch Refunds Data (Filtered by Role)
        $refundQuery = SalesReturn::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        if ($role === 'staff') {
            $refundQuery->where('created_by', $user->id);
        }
        $refundData = $refundQuery->selectRaw("$dateFormat as key_date, SUM(refund_amount) as total")
            ->groupBy('key_date')
            ->pluck('total', 'key_date');

        // 3. Fetch Cost Data (Admin Only)
        $costData = collect([]);
        if ($role === 'admin') {
            $costDateCol = ($range === 'today' || $range === 'yesterday') ? 'sales.created_at' : 'sales.date';

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

        // --- Data Filling Loop ---
        if ($range === 'today' || $range === 'yesterday') {
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
        } else {
            // Standard Daily Loop
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
