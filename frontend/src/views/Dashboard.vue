<script setup>
import { ref, onMounted, watch } from "vue";
import axios from "../axios";
import VueApexCharts from "vue3-apexcharts";
import {
  CurrencyBangladeshiIcon,
  PresentationChartLineIcon,
  ArrowTrendingUpIcon,
  ClockIcon,
  BanknotesIcon,
  TagIcon,
  CreditCardIcon,
  WalletIcon,
  ArchiveBoxIcon,
  CubeIcon,
  ExclamationTriangleIcon,
  ArrowPathIcon,
  ShoppingBagIcon,
} from "@heroicons/vue/24/outline";

// --- State ---
const loading = ref(false);
const filterRange = ref("today");

// Initial Data Structure (Syncs with ReportController)
const data = ref({
  metrics: {
    range_gross_sales: 0,
    range_sales: 0, // Net Sales
    range_returns: 0,
    range_profit: 0,
    range_purchases: 0,
    range_cogs: 0,
    range_damaged_loss: 0, // 🔥 Loss from bad returns
    range_discount: 0,
    range_tax: 0,
    range_count: 0,
    range_cash: 0,
    range_digital: 0,
  },
  overall: {
    net_sales: 0,
    total_returns: 0,
    total_purchase_spend: 0,
    total_due: 0,
    total_collected: 0,
    inventory_value: 0,
    damaged_stock_value: 0,
  },
  inventory: { total_products: 0, low_stock: 0 },
  users: { total_customers: 0 },
  chart: { categories: [], series: [] }, // Dynamic Chart Data
  top_products: [],
  low_stock_list: [],
  filter_label: "Today",
});

// --- Chart Configuration ---
const chartOptions = ref({
  chart: {
    type: "area",
    height: 350,
    fontFamily: "inherit",
    toolbar: { show: false },
    animations: { enabled: true, easing: "easeinout", speed: 800 },
    zoom: { enabled: false },
  },
  stroke: { curve: "smooth", width: 2 },
  // Green for Revenue, Red for Cost (Matches Logic)
  colors: ["#10B981", "#F43F5E"],
  fill: {
    type: "gradient",
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.4,
      opacityTo: 0.05,
      stops: [0, 90, 100],
    },
  },
  dataLabels: { enabled: false },
  xaxis: {
    categories: [], // Populated from Backend
    tooltip: { enabled: false },
    axisBorder: { show: false },
    axisTicks: { show: false },
    labels: { style: { colors: "#6B7280", fontSize: "11px" } },
  },
  yaxis: {
    labels: {
      style: { colors: "#6B7280", fontSize: "11px" },
      formatter: (val) => (val >= 1000 ? (val / 1000).toFixed(1) + "k" : val),
    },
  },
  grid: {
    borderColor: "#F3F4F6",
    strokeDashArray: 4,
    xaxis: { lines: { show: true } },
  },
  tooltip: {
    theme: "light",
    y: { formatter: (val) => "৳ " + Number(val).toLocaleString() },
  },
  legend: { position: "top", horizontalAlign: "right" },
});

const chartSeries = ref([]);

// --- Fetch Data ---
const fetchData = async () => {
  loading.value = true;
  try {
    // API Call
    const res = await axios.get(
      `/dashboard/overview?range=${filterRange.value}`,
    );

    if (res.data.status) {
      data.value = res.data.data;

      // Update Chart Data (Time/Date Axis)
      chartOptions.value = {
        ...chartOptions.value,
        xaxis: {
          ...chartOptions.value.xaxis,
          categories: data.value.chart.categories, // ["10 AM", "11 AM"] or ["Jan 01", "Jan 02"]
        },
      };
      chartSeries.value = data.value.chart.series;
    }
  } catch (error) {
    console.error("Dashboard Load Error:", error);
  } finally {
    loading.value = false;
  }
};

// Helper for Image URLs
const getImageUrl = (path) =>
  path?.startsWith("http") ? path : `http://localhost:8000/storage/${path}`;

// Helper for Currency Formatting
const formatCurrency = (amount) => {
  return "৳ " + Number(amount || 0).toLocaleString();
};

// Watch for filter changes
watch(filterRange, () => fetchData());

// Initial Load
onMounted(() => fetchData());
</script>

<template>
  <div class="space-y-8 pb-10">
    <div
      class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:bg-slate-900 p-5 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800"
    >
      <div>
        <h1
          class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2"
        >
          <PresentationChartLineIcon class="w-8 h-8 text-indigo-600" />
          Executive Dashboard
        </h1>
        <p class="text-sm text-gray-500 mt-1">
          Performance snapshot for
          <span class="font-bold text-indigo-600">{{ data.filter_label }}</span>
        </p>
      </div>

      <div class="relative min-w-[200px]">
        <select
          v-model="filterRange"
          class="w-full appearance-none bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 py-2.5 pl-4 pr-10 rounded-xl shadow-sm text-sm font-bold focus:ring-2 focus:ring-indigo-500 text-gray-700 dark:text-gray-200 cursor-pointer transition-all hover:border-indigo-400"
        >
          <option value="today">Today</option>
          <option value="yesterday">Yesterday</option>
          <option value="last_7_days">Last 7 Days</option>
          <option value="this_month">This Month</option>
          <option value="last_month">Last Month</option>
          <option value="all_time">All Time</option>
        </select>
        <ClockIcon
          class="w-5 h-5 absolute right-3 top-2.5 text-gray-400 pointer-events-none"
        />
      </div>
    </div>

    <div>
      <h3
        class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 flex items-center gap-2"
      >
        <ArrowTrendingUpIcon class="w-4 h-4" /> Financial Overview
      </h3>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <div
          class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 relative overflow-hidden group hover:shadow-md transition-all"
        >
          <div
            class="absolute top-4 right-4 p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition"
          >
            <CurrencyBangladeshiIcon class="w-6 h-6" />
          </div>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Net Sales (Actual)
          </p>
          <h3
            class="text-2xl font-extrabold text-gray-800 dark:text-white mt-1"
          >
            {{ formatCurrency(data.metrics.range_sales) }}
          </h3>

          <div
            class="mt-4 pt-3 border-t border-dashed border-gray-100 dark:border-slate-700 flex justify-between text-xs"
          >
            <span class="text-gray-400 font-medium"
              >Gross: {{ formatCurrency(data.metrics.range_gross_sales) }}</span
            >
            <span
              class="text-rose-500 font-bold flex items-center gap-1 bg-rose-50 dark:bg-rose-900/20 px-1.5 py-0.5 rounded"
            >
              <ArrowPathIcon class="w-3 h-3" /> -{{
                formatCurrency(data.metrics.range_returns)
              }}
            </span>
          </div>
        </div>

        <div
          class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 relative overflow-hidden group hover:shadow-md transition-all"
        >
          <div
            class="absolute top-4 right-4 p-2 bg-emerald-50 dark:bg-emerald-900/30 rounded-lg text-emerald-600 dark:text-emerald-400 group-hover:scale-110 transition"
          >
            <BanknotesIcon class="w-6 h-6" />
          </div>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Net Profit
          </p>
          <h3
            class="text-2xl font-extrabold mt-1"
            :class="
              data.metrics.range_profit >= 0
                ? 'text-emerald-600'
                : 'text-rose-500'
            "
          >
            {{ formatCurrency(data.metrics.range_profit) }}
          </h3>

          <div
            class="mt-4 pt-3 border-t border-dashed border-gray-100 dark:border-slate-700"
          >
            <div
              v-if="data.metrics.range_damaged_loss > 0"
              class="flex justify-between items-center text-xs text-rose-600 font-bold bg-rose-50 dark:bg-rose-900/20 px-2 py-1 rounded animate-pulse"
            >
              <span class="flex items-center gap-1"
                ><ExclamationTriangleIcon class="w-3 h-3" /> Damaged Loss:</span
              >
              <span
                >-{{ formatCurrency(data.metrics.range_damaged_loss) }}</span
              >
            </div>
            <div
              v-else
              class="flex justify-between text-xs text-gray-400 font-medium"
            >
              <span>Adj. COGS:</span>
              <span>{{ formatCurrency(data.metrics.range_cogs) }}</span>
            </div>
          </div>
        </div>

        <div
          class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 relative overflow-hidden"
        >
          <div class="grid grid-cols-2 gap-4 h-full">
            <div>
              <p class="text-[10px] uppercase font-bold text-gray-400">
                Purchases
              </p>
              <h4 class="text-lg font-bold text-gray-800 dark:text-white">
                {{ formatCurrency(data.metrics.range_purchases) }}
              </h4>
            </div>
            <div>
              <p class="text-[10px] uppercase font-bold text-gray-400">
                Discount
              </p>
              <h4 class="text-lg font-bold text-orange-500">
                {{ formatCurrency(data.metrics.range_discount) }}
              </h4>
            </div>
            <div>
              <p class="text-[10px] uppercase font-bold text-gray-400">Tax</p>
              <h4 class="text-lg font-bold text-blue-500">
                {{ formatCurrency(data.metrics.range_tax) }}
              </h4>
            </div>
            <div>
              <p class="text-[10px] uppercase font-bold text-gray-400">
                Invoices
              </p>
              <h4 class="text-lg font-bold text-indigo-500">
                {{ data.metrics.range_count }}
              </h4>
            </div>
          </div>
        </div>

        <div
          class="bg-gradient-to-br from-indigo-600 to-indigo-700 p-5 rounded-2xl shadow-lg shadow-indigo-200 dark:shadow-none text-white relative overflow-hidden flex flex-col justify-between"
        >
          <CubeIcon
            class="absolute -bottom-4 -right-4 w-24 h-24 text-white opacity-10"
          />
          <div>
            <p class="text-indigo-100 text-sm font-medium">
              Inventory Asset Value
            </p>
            <h3 class="text-2xl font-extrabold mt-1">
              {{ formatCurrency(data.overall.inventory_value) }}
            </h3>
          </div>

          <div
            class="mt-2 flex items-center justify-between text-xs bg-white/10 px-3 py-2 rounded-lg border border-white/10 backdrop-blur-sm"
          >
            <div class="flex items-center gap-1.5 text-orange-200">
              <ExclamationTriangleIcon class="w-4 h-4" />
              <span>Damaged Assets:</span>
            </div>
            <span class="font-bold text-white">{{
              formatCurrency(data.overall.damaged_stock_value)
            }}</span>
          </div>
        </div>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-800"
    >
      <div class="flex justify-between items-center mb-6">
        <h3
          class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"
        >
          <PresentationChartLineIcon class="w-5 h-5 text-gray-400" /> Revenue vs
          Cost Trend
        </h3>
        <div class="flex gap-3">
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
            <span class="text-xs font-bold text-gray-600 dark:text-gray-400"
              >Revenue</span
            >
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-rose-500"></span>
            <span class="text-xs font-bold text-gray-600 dark:text-gray-400"
              >Cost</span
            >
          </div>
        </div>
      </div>
      <div class="h-[350px] w-full">
        <VueApexCharts
          type="area"
          height="100%"
          :options="chartOptions"
          :series="chartSeries"
        />
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div
        class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-800 overflow-hidden flex flex-col"
      >
        <div
          class="p-5 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center"
        >
          <h3
            class="font-bold text-gray-800 dark:text-white flex items-center gap-2"
          >
            <ShoppingBagIcon class="w-5 h-5 text-indigo-500" /> Top Selling
            Products
          </h3>
        </div>
        <div class="flex-1 overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead
              class="bg-gray-50 dark:bg-slate-800 text-gray-500 uppercase text-xs"
            >
              <tr>
                <th class="px-5 py-3">Product</th>
                <th class="px-5 py-3 text-right">Sold</th>
                <th class="px-5 py-3 text-right">Stock</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
              <tr
                v-for="p in data.top_products"
                :key="p.id"
                class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
              >
                <td class="px-5 py-3 flex items-center gap-3">
                  <img
                    :src="getImageUrl(p.image)"
                    class="w-10 h-10 rounded-lg border dark:border-slate-700 object-cover"
                  />
                  <span class="font-bold text-gray-700 dark:text-gray-200">{{
                    p.name
                  }}</span>
                </td>
                <td class="px-5 py-3 text-right font-bold text-indigo-600">
                  {{ p.total_sold }}
                </td>
                <td class="px-5 py-3 text-right text-gray-500">
                  {{ p.stock_quantity }}
                </td>
              </tr>
              <tr v-if="data.top_products.length === 0">
                <td colspan="3" class="p-8 text-center text-gray-400">
                  No sales data available
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-800 overflow-hidden flex flex-col"
      >
        <div
          class="p-5 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center"
        >
          <h3
            class="font-bold text-gray-800 dark:text-white flex items-center gap-2"
          >
            <ExclamationTriangleIcon class="w-5 h-5 text-rose-500" /> Low Stock
            Alerts
          </h3>
          <span
            v-if="data.inventory.low_stock > 0"
            class="bg-rose-100 text-rose-600 px-3 py-1 rounded-full text-xs font-bold animate-pulse"
            >{{ data.inventory.low_stock }} Critical</span
          >
        </div>
        <div class="max-h-[350px] overflow-y-auto custom-scrollbar">
          <div
            v-for="p in data.low_stock_list"
            :key="p.id"
            class="flex items-center gap-4 p-4 border-b border-gray-50 dark:border-slate-800 last:border-0 hover:bg-rose-50/30 transition"
          >
            <img
              :src="getImageUrl(p.image)"
              class="w-12 h-12 rounded-lg border dark:border-slate-700 object-cover"
            />
            <div class="flex-1">
              <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200">
                {{ p.name }}
              </h4>
              <span class="text-[10px] text-gray-400"
                >Alert at: {{ p.alert_quantity }}</span
              >
            </div>
            <div class="text-right">
              <span class="text-xl font-bold text-rose-500">{{
                p.stock_quantity
              }}</span>
              <span class="text-[10px] text-gray-400 block">Left</span>
            </div>
          </div>
          <div
            v-if="data.low_stock_list.length === 0"
            class="p-10 text-center text-gray-400"
          >
            Stock levels are healthy!
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Custom Scrollbar for lists */
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #e2e8f0;
  border-radius: 20px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #334155;
}
</style>
