<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SaleItem;
use App\Models\Expense;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Dashboard Overview API
     */
    public function dashboardOverview(Request $request)
    {
        try {
            $user = auth()->user();
            $range = $request->query('range', 'today');
            $chartResolution = 'day';

            switch ($range) {
                case 'today':
                    $startDate = Carbon::today();
                    $endDate = Carbon::today()->endOfDay();
                    $chartResolution = 'hour';
                    break;
                case 'yesterday':
                    $startDate = Carbon::yesterday();
                    $endDate = Carbon::yesterday()->endOfDay();
                    $chartResolution = 'hour';
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
                    $firstSale = Sale::oldest('date')->first();
                    $startDate = $firstSale ? Carbon::parse($firstSale->date)->startOfDay() : Carbon::now()->startOfYear();
                    $endDate = Carbon::now()->endOfDay();

                    if ($startDate->diffInDays($endDate) > 90) {
                        $chartResolution = 'month';
                    }
                    break;
                default:
                    $startDate = Carbon::today();
                    $endDate = Carbon::today()->endOfDay();
                    $chartResolution = 'hour';
            }

            $startStr = $startDate->format('Y-m-d H:i:s');
            $endStr = $endDate->format('Y-m-d H:i:s');

            $salesQuery = Sale::query();
            $refundQuery = SalesReturn::query();


            if ($range === 'today' || $range === 'yesterday') {
                $salesQuery->whereDate('date', $startDate->format('Y-m-d'));
                $refundQuery->whereDate('created_at', $startDate->format('Y-m-d'));
            } else {
                $salesQuery->whereBetween('date', [$startStr, $endStr]);
                $refundQuery->whereBetween('date', [$startStr, $endStr]);
            }

            if ($user->role === 'staff') {
                $salesQuery->where('created_by', $user->id);
                $refundQuery->whereHas('sale', function($q) use ($user) {
                    $q->where('created_by', $user->id);
                });
            }


            $grossSales = (clone $salesQuery)->sum('grand_total');
            $totalDiscount = (clone $salesQuery)->sum('discount');
            $totalRefunds = $refundQuery->sum('refund_amount');
            $netSales = $grossSales - $totalRefunds;
            $invoiceCount = (clone $salesQuery)->count();

            // Payment Breakdown
            $cashSale = (clone $salesQuery)->where('payment_method', 'cash')->sum('paid_amount');
            $digitalSale = (clone $salesQuery)->where('payment_method', '!=', 'cash')->sum('paid_amount');

            // Due Calculation
            $totalCollectedInRange = $cashSale + $digitalSale;
            $rangeDue = max(0, $netSales - $totalCollectedInRange);

            // --- 4. Sensitive Metrics (Admin Only) ---
            $actualCOGS = 0;
            $totalExpenses = 0;
            $netProfit = 0;
            $inventoryValue = 0;
            $damagedValue = 0;

            if ($user->role === 'admin') {
                $totalExpenses = Expense::whereBetween('date', [$startStr, $endStr])->sum('amount');

                // COGS Calculation (Simplified)
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

                $actualCOGS = $totalSoldCost - $goodReturnCost;
                $netProfit = ($netSales - $actualCOGS) - $totalExpenses;

                $inventoryValue = Product::sum(DB::raw('stock_quantity * cost_price'));
                $damagedValue = Product::sum(DB::raw('damaged_quantity * cost_price'));
            }

            // --- 5. Optimized Chart Data Generation ---
            $chartData = $this->getOptimizedChartData($salesQuery, $refundQuery, $startDate, $endDate, $chartResolution);

            // --- 6. Overall Stats ---
            $overallStats = [
                'total_due' => round(Sale::sum('due_amount'), 2),
                'total_collected' => round(Sale::sum('paid_amount'), 2),
            ];

            if ($user->role === 'admin') {
                $overallStats['inventory_value'] = round($inventoryValue, 2);
                $overallStats['damaged_stock_value'] = round($damagedValue, 2);
            }

            $lowStockList = Product::whereColumn('stock_quantity', '<=', 'alert_quantity')->take(5)->get();

            // --- 7. Recent Sales ---
            $recentSalesQuery = Sale::with('customer:id,name')->latest();
            if ($user->role === 'staff') {
                $recentSalesQuery->where('created_by', $user->id);
            }
            $recentSales = $recentSalesQuery->take(5)->get()->map(function($sale) {
                return [
                    'id' => $sale->id,
                    'invoice_no' => $sale->invoice_no,
                    'customer' => $sale->customer ? $sale->customer->name : 'Walk-in',
                    'amount' => $sale->grand_total,
                    'status' => $sale->payment_status,
                    'time' => Carbon::parse($sale->created_at)->diffForHumans()
                ];
            });

            // --- 8. Top Products ---
            $topProducts = $this->getTopProducts($startStr, $endStr, $user);

            return response()->json([
                'status' => true,
                'data' => [
                    'metrics' => [
                        'range_sales' => round($netSales, 2),
                        'range_gross_sales' => round($grossSales, 2),
                        'range_returns' => round($totalRefunds, 2),
                        'range_discount' => round($totalDiscount, 2),
                        'range_count' => $invoiceCount,
                        'range_cash' => round($cashSale, 2),
                        'range_digital' => round($digitalSale, 2),
                        'range_due' => round($rangeDue, 2),
                        'range_profit' => round($netProfit, 2),
                        'range_expenses' => round($totalExpenses, 2),
                    ],
                    'overall' => $overallStats,
                    'inventory' => [
                        'low_stock' => Product::whereColumn('stock_quantity', '<=', 'alert_quantity')->count(),
                    ],
                    'chart' => $chartData,
                    'recent_sales' => $recentSales,
                    'top_products' => $topProducts,
                    'low_stock_list' => $lowStockList,
                    'filter_label' => ucfirst(str_replace('_', ' ', $range))
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Helper for Chart Optimization (Handles Hour/Day/Month)
    private function getOptimizedChartData($salesQueryBase, $refundQueryBase, $startDate, $endDate, $resolution)
    {
        $salesQuery = clone $salesQueryBase;
        $refundQuery = clone $refundQueryBase;
        $driver = DB::connection()->getDriverName();

        // --- 1. Define Format Strings based on Resolution & Driver ---
        $dateColumn = 'date';

        if ($resolution === 'hour') {
            // IMPORTANT: For hourly charts, we MUST use 'created_at' because 'date' usually has 00:00:00
            $dateColumn = 'created_at';

            if ($driver === 'pgsql') {
                $sqlFormat = "TO_CHAR(created_at, 'HH24')"; // 00-23
            } else {
                $sqlFormat = "DATE_FORMAT(created_at, '%H')"; // 00-23
            }
            $phpFormat = 'H'; // PHP hour format
        }
        elseif ($resolution === 'month') {
            if ($driver === 'pgsql') {
                $sqlFormat = "TO_CHAR(date, 'YYYY-MM')";
            } else {
                $sqlFormat = "DATE_FORMAT(date, '%Y-%m')";
            }
            $phpFormat = 'Y-m';
        }
        else { // Day (Default)
            if ($driver === 'pgsql') {
                $sqlFormat = "TO_CHAR(date, 'YYYY-MM-DD')";
            } else {
                $sqlFormat = "DATE_FORMAT(date, '%Y-%m-%d')";
            }
            $phpFormat = 'Y-m-d';
        }

        // --- 2. Fetch Sales Data ---
        $salesData = $salesQuery->select(
            DB::raw("$sqlFormat as date_label"),
            DB::raw('SUM(grand_total) as total')
        )
        ->groupBy('date_label')
        ->pluck('total', 'date_label')
        ->toArray();

        // --- 3. Fetch Refunds Data ---
        $refundData = $refundQuery->select(
            DB::raw("$sqlFormat as date_label"),
            DB::raw('SUM(refund_amount) as total')
        )
        ->groupBy('date_label')
        ->pluck('total', 'date_label')
        ->toArray();

        // --- 4. Fill Gaps Logic ---
        $categories = [];
        $netData = [];

        // Loop Logic differs for 'hour'
        if ($resolution === 'hour') {
            // Loop 00 to 23
            for ($i = 0; $i < 24; $i++) {
                $label = str_pad($i, 2, '0', STR_PAD_LEFT);
                $categories[] = Carbon::createFromTime($i, 0)->format('h A');

                $gross = $salesData[$label] ?? 0;
                $refund = $refundData[$label] ?? 0;
                $netData[] = max(0, round($gross - $refund, 2));
            }
        } else {
            // Loop Dates
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $label = $current->format($phpFormat);
                $categories[] = $resolution === 'month' ? $current->format('M Y') : $current->format('d M');

                $gross = $salesData[$label] ?? 0;
                $refund = $refundData[$label] ?? 0;
                $netData[] = max(0, round($gross - $refund, 2));

                if ($resolution === 'month') {
                    $current->addMonth();
                } else {
                    $current->addDay();
                }
            }
        }

        return [
            'categories' => $categories,
            'series' => [[
                'name' => 'Net Revenue',
                'data' => $netData
            ]]
        ];
    }

    private function getTopProducts($startStr, $endStr, $user)
    {
        $query = SaleItem::select('product_id', DB::raw('sum(quantity) as total_sold'))
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.date', [$startStr, $endStr]);

        if ($user && $user->role === 'staff') {
            $query->where('sales.created_by', $user->id);
        }

        return $query->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->with('product')
            ->take(5)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->product->id ?? 0,
                    'name' => $item->product->name ?? 'Unknown',
                    'image' => $item->product->image,
                    'total_sold' => (int) $item->total_sold,
                    'stock_quantity' => $item->product->stock_quantity ?? 0
                ];
            });
    }

    private function getLowStockList() {
        return Product::whereColumn('stock_quantity', '<=', 'alert_quantity')
            ->select('id', 'name', 'stock_quantity', 'alert_quantity', 'image')
            ->take(5)->get();
    }

    // ... Other endpoints (lowStockReport, dailySalesReport) ...
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
}
