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
    public function dashboardOverview(Request $request)
    {
        try {
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

            $fStart = $startDate->format('Y-m-d H:i:s');
            $fEnd = $endDate->format('Y-m-d H:i:s');

            // --- Sales & Refunds ---
            $grossSales = Sale::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                             ->sum('grand_total');

            $totalRefunds = SalesReturn::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                                       ->sum('refund_amount');
            $netSales = $grossSales - $totalRefunds;

            // --- COGS Calculation ---
            $totalSoldCost = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->whereBetween('sales.date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->sum(DB::raw('sale_items.quantity * products.cost_price'));

            $goodReturnCost = DB::table('sales_return_items')
                ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
                ->join('products', 'products.id', '=', 'sales_return_items.product_id')
                ->whereBetween('sales_returns.date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->where('sales_return_items.return_condition', 'good')
                ->sum(DB::raw('sales_return_items.quantity * products.cost_price'));

            $badReturnCost = DB::table('sales_return_items')
                ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
                ->join('products', 'products.id', '=', 'sales_return_items.product_id')
                ->whereBetween('sales_returns.date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->where('sales_return_items.return_condition', 'bad')
                ->sum(DB::raw('sales_return_items.quantity * products.cost_price'));

            $actualCOGS = $totalSoldCost - $goodReturnCost;

            // --- 🔥 NEW: Expense Calculation ---
            // এই ডেট রেঞ্জের মোট খরচ বের করা হচ্ছে
            $totalExpenses = \App\Models\Expense::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                                ->sum('amount');

            // --- 🔥 NEW: Profit Logic Update ---
            // Gross Profit = (Sales - COGS) - Expenses
            $grossProfit = ($netSales - $actualCOGS) - $totalExpenses;


            // --- Other Metrics ---
            $purchases = Purchase::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])->sum('grand_total');
            $discounts = Sale::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])->sum('discount');
            $tax = Sale::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])->sum('tax');
            $invoiceCount = Sale::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])->count();

            $cashSale = Sale::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                            ->where('payment_method', 'cash')
                            ->sum('paid_amount');

            $digitalSale = Sale::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                               ->where('payment_method', '!=', 'cash')
                               ->sum('paid_amount');

            // --- Overall Stats ---
            $allTimeSales = Sale::sum('grand_total');
            $allTimeReturns = SalesReturn::sum('refund_amount');
            $inventoryValue = Product::sum(DB::raw('stock_quantity * cost_price'));
            $damagedValue = 0;
            try {
                 $damagedValue = Product::sum(DB::raw('damaged_quantity * cost_price'));
            } catch (\Exception $e) {  }

            $chartData = $this->generateChartData($startDate, $endDate, $range);

            return response()->json([
                'status' => true,
                'data' => [
                    'metrics' => [
                        'range_gross_sales' => round($grossSales, 2),
                        'range_sales' => round($netSales, 2),
                        'range_returns' => round($totalRefunds, 2),

                        // Updated Profit (After Expense deduction)
                        'range_profit' => round($grossProfit, 2),

                        // New Field for Frontend
                        'range_expenses' => round($totalExpenses, 2),

                        'range_cogs' => round($actualCOGS, 2),
                        'range_damaged_loss' => round($badReturnCost, 2),
                        'range_purchases' => round($purchases, 2),
                        'range_discount' => round($discounts, 2),
                        'range_tax' => round($tax, 2),
                        'range_count' => $invoiceCount,
                        'range_cash' => round($cashSale, 2),
                        'range_digital' => round($digitalSale, 2),
                    ],
                    'overall' => [
                        'net_sales' => round($allTimeSales - $allTimeReturns, 2),
                        'total_returns' => round($allTimeReturns, 2),
                        'total_purchase_spend' => round(Purchase::sum('grand_total'), 2),
                        'total_due' => round(Sale::sum('due_amount'), 2),
                        'total_collected' => round(Sale::sum('paid_amount'), 2),
                        'inventory_value' => round($inventoryValue, 2),
                        'damaged_stock_value' => round($damagedValue, 2)
                    ],
                    'inventory' => [
                        'total_products' => Product::count(),
                        'low_stock' => Product::whereColumn('stock_quantity', '<=', 'alert_quantity')->count(),
                    ],
                    'users' => [
                        'total_customers' => Customer::count()
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

    private function generateChartData($startDate, $endDate, $range)
    {
        $categories = [];
        $netRevenues = [];
        $netCosts = [];

        $driver = DB::connection()->getDriverName();

        if ($range === 'all_time') {
            $dateFormat = ($driver === 'pgsql') ? "TO_CHAR(date, 'YYYY-MM')" : "DATE_FORMAT(date, '%Y-%m')";
        } elseif ($range === 'today' || $range === 'yesterday') {
            $dateFormat = ($driver === 'pgsql') ? "EXTRACT(HOUR FROM created_at)" : "EXTRACT(HOUR FROM created_at)";
        } else {
            $dateFormat = ($driver === 'pgsql') ? "CAST(date AS DATE)" : "DATE(date)";
        }

        $salesData = Sale::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->selectRaw("$dateFormat as key_date, SUM(grand_total) as total")
            ->groupBy('key_date')
            ->pluck('total', 'key_date');

        // B. Refunds
        $refundData = SalesReturn::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->selectRaw("$dateFormat as key_date, SUM(refund_amount) as total")
            ->groupBy('key_date')
            ->pluck('total', 'key_date');

        $costDateCol = ($range === 'today' || $range === 'yesterday') ? 'sales.created_at' : 'sales.date';

        if ($range === 'all_time') {
            $costDateFormat = ($driver === 'pgsql') ? "TO_CHAR($costDateCol, 'YYYY-MM')" : "DATE_FORMAT($costDateCol, '%Y-%m')";
        } elseif ($range === 'today' || $range === 'yesterday') {
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

        $returnDateCol = ($range === 'today' || $range === 'yesterday') ? 'sales_returns.created_at' : 'sales_returns.date';

        if ($range === 'all_time') {
            $returnDateFormat = ($driver === 'pgsql') ? "TO_CHAR($returnDateCol, 'YYYY-MM')" : "DATE_FORMAT($returnDateCol, '%Y-%m')";
        } elseif ($range === 'today' || $range === 'yesterday') {
            $returnDateFormat = ($driver === 'pgsql') ? "EXTRACT(HOUR FROM $returnDateCol)" : "EXTRACT(HOUR FROM $returnDateCol)";
        } else {
            $returnDateFormat = ($driver === 'pgsql') ? "CAST($returnDateCol AS DATE)" : "DATE($returnDateCol)";
        }

        $restockData = DB::table('sales_return_items')
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->join('products', 'products.id', '=', 'sales_return_items.product_id')
            ->whereBetween('sales_returns.date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('sales_return_items.return_condition', 'good')
            ->selectRaw("$returnDateFormat as key_date, SUM(sales_return_items.quantity * products.cost_price) as total")
            ->groupBy('key_date') // 🔥 Fixed
            ->pluck('total', 'key_date');


        if ($range === 'today' || $range === 'yesterday') {
            for ($i = 0; $i <= 23; $i += 1) {
                $categories[] = Carbon::createFromTime($i, 0)->format('h A');
                $gross = $salesData[(int)$i] ?? 0;
                $refund = $refundData[(int)$i] ?? 0;
                $cogs = $costData[(int)$i] ?? 0;
                $restock = $restockData[(int)$i] ?? 0;

                $netRevenues[] = max(0, $gross - $refund);
                $netCosts[] = max(0, $cogs - $restock);
            }
        }
        elseif ($range === 'all_time') {
            $months = Sale::selectRaw("$dateFormat as key_date")
                        ->groupBy('key_date')
                        ->orderBy('key_date', 'asc')
                        ->limit(12)
                        ->pluck('key_date');

            foreach ($months as $month) {
                $categories[] = Carbon::createFromFormat('Y-m', $month)->format('M Y');

                $gross = $salesData[$month] ?? 0;
                $refund = $refundData[$month] ?? 0;
                $cogs = $costData[$month] ?? 0;
                $restock = $restockData[$month] ?? 0;

                $netRevenues[] = max(0, $gross - $refund);
                $netCosts[] = max(0, $cogs - $restock);
            }
        }
        else {
            $period = CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $date) {
                $dayKey = $date->format('Y-m-d');
                $categories[] = $date->format('d M');

                $gross = $salesData[$dayKey] ?? 0;
                $refund = $refundData[$dayKey] ?? 0;
                $cogs = $costData[$dayKey] ?? 0;
                $restock = $restockData[$dayKey] ?? 0;

                $netRevenues[] = max(0, $gross - $refund);
                $netCosts[] = max(0, $cogs - $restock);
            }
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Revenue', 'data' => $netRevenues],
                ['name' => 'Cost', 'data' => $netCosts]
            ]
        ];
    }
}
