<script setup>
import { ref, onMounted, watch } from "vue";
import axios from "../axios";
import VueApexCharts from "vue3-apexcharts";
import {
  CurrencyBangladeshiIcon,
  ShoppingBagIcon,
  PresentationChartLineIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  ExclamationTriangleIcon,
  ClockIcon,
  BanknotesIcon,
} from "@heroicons/vue/24/outline";

// --- State ---
const loading = ref(false);
const filterRange = ref("today");
const dashboardData = ref({
  metrics: { revenue: 0, orders: 0, expense: 0, profit: 0, growth: 0 },
  low_stock: [],
  top_products: [],
  recent_sales: [],
});

const chartOptions = ref({
  chart: {
    type: "area",
    height: 350,
    fontFamily: "inherit",
    toolbar: { show: false },
    zoom: { enabled: false },
    animations: {
      enabled: true,
      easing: "easeinout",
      speed: 800,
    },
  },
  stroke: {
    curve: "smooth",
    width: 3,
  },
  colors: ["#6366F1", "#F43F5E"],

  // 🔥 Gradient Fill for depth
  fill: {
    type: "gradient",
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.55,
      opacityTo: 0.05,
      stops: [0, 90, 100],
    },
  },
  dataLabels: { enabled: false },
  xaxis: {
    categories: [],
    axisBorder: { show: false },
    axisTicks: { show: false },
    labels: {
      style: { colors: "#9ca3af", fontSize: "12px" },
    },
  },
  yaxis: {
    labels: {
      style: { colors: "#9ca3af", fontSize: "12px" },
      formatter: (value) => {
        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
        if (value >= 1000) return (value / 1000).toFixed(1) + "k";
        return value;
      },
    },
  },
  grid: {
    borderColor: "#f3f4f6",
    strokeDashArray: 4,
    padding: { top: 0, right: 0, bottom: 0, left: 10 },
  },
  tooltip: {
    theme: "light",
    y: {
      formatter: function (val) {
        return "৳ " + Number(val).toLocaleString();
      },
    },
  },
  legend: {
    position: "top",
    horizontalAlign: "right",
  },
});

const chartSeries = ref([
  { name: "Revenue", data: [] },
  { name: "Cost of Sales", data: [] },
]);

// --- Fetch Data ---
const fetchDashboardData = async () => {
  loading.value = true;
  try {
    const response = await axios.get(
      `/dashboard/overview?range=${filterRange.value}`,
    );

    if (response.data.status) {
      const data = response.data.data;
      dashboardData.value = data;

      // Update Chart Data
      chartOptions.value = {
        ...chartOptions.value,
        xaxis: {
          ...chartOptions.value.xaxis,
          categories: data.chart.categories,
        },
      };

      chartSeries.value = [
        { name: "Revenue", data: data.chart.series[0].data },
        { name: "Cost of Sales", data: data.chart.series[1].data },
      ];
    }
  } catch (error) {
    console.error("Dashboard Error:", error);
  } finally {
    loading.value = false;
  }
};

const getImageUrl = (path) => {
  if (!path) return "https://placehold.co/100x100?text=No+Img";
  if (path.startsWith("http")) return path;
  return `http://localhost:8000/storage/${path}`;
};

// --- Watchers & Hooks ---
watch(filterRange, () => {
  fetchDashboardData();
});

onMounted(() => {
  fetchDashboardData();
});
</script>

<template>
  <div class="space-y-6 pb-10">
    <div
      class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"
    >
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
          Dashboard Overview
        </h1>
        <p class="text-sm text-gray-500">
          Welcome back! Here's your store performance summary.
        </p>
      </div>

      <div class="relative">
        <select
          v-model="filterRange"
          class="appearance-none bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-gray-700 dark:text-gray-200 py-2 pl-4 pr-10 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium text-sm cursor-pointer transition-shadow"
        >
          <option value="today">Today</option>
          <option value="yesterday">Yesterday</option>
          <option value="last_7_days">Last 7 Days</option>
          <option value="this_month">This Month</option>
          <option value="last_month">Last Month</option>
          <option value="all_time">All Time</option>
        </select>
        <div
          class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500"
        >
          <ClockIcon class="w-4 h-4" />
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div
        class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 relative overflow-hidden transition-transform hover:-translate-y-1 duration-300"
      >
        <div class="flex justify-between items-start">
          <div>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">
              Total Revenue
            </p>
            <h3
              class="text-2xl font-extrabold text-gray-800 dark:text-white mt-1"
            >
              ৳ {{ Number(dashboardData.metrics.revenue).toLocaleString() }}
            </h3>
          </div>
          <div
            class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl text-indigo-600 dark:text-indigo-400"
          >
            <CurrencyBangladeshiIcon class="w-6 h-6" />
          </div>
        </div>
        <div
          class="mt-4 flex items-center text-xs font-medium"
          :class="
            dashboardData.metrics.growth >= 0
              ? 'text-green-600'
              : 'text-red-500'
          "
        >
          <component
            :is="
              dashboardData.metrics.growth >= 0
                ? ArrowTrendingUpIcon
                : ArrowTrendingDownIcon
            "
            class="w-3 h-3 mr-1"
          />
          <span>{{ Math.abs(dashboardData.metrics.growth) }}% vs Previous</span>
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 transition-transform hover:-translate-y-1 duration-300"
      >
        <div class="flex justify-between items-start">
          <div>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">
              Total Orders
            </p>
            <h3
              class="text-2xl font-extrabold text-gray-800 dark:text-white mt-1"
            >
              {{ dashboardData.metrics.orders }}
            </h3>
          </div>
          <div
            class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-xl text-blue-600 dark:text-blue-400"
          >
            <ShoppingBagIcon class="w-6 h-6" />
          </div>
        </div>
        <div class="mt-4 flex items-center text-xs font-medium text-gray-500">
          <ClockIcon class="w-3 h-3 mr-1" />
          <span>{{ filterRange.replace("_", " ") }}</span>
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 transition-transform hover:-translate-y-1 duration-300"
      >
        <div class="flex justify-between items-start">
          <div>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">
              Cost of Sales
            </p>
            <h3
              class="text-2xl font-extrabold text-gray-800 dark:text-white mt-1"
            >
              ৳ {{ Number(dashboardData.metrics.expense).toLocaleString() }}
            </h3>
          </div>
          <div
            class="p-3 bg-rose-50 dark:bg-rose-900/30 rounded-xl text-rose-600 dark:text-rose-400"
          >
            <BanknotesIcon class="w-6 h-6" />
          </div>
        </div>
        <div class="mt-4 flex items-center text-xs font-medium text-rose-600">
          <span class="px-2 py-0.5 rounded bg-rose-100 text-rose-700"
            >COGS</span
          >
          <span class="ml-2">Product Cost</span>
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 transition-transform hover:-translate-y-1 duration-300"
      >
        <div class="flex justify-between items-start">
          <div>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">
              Gross Profit
            </p>
            <h3
              class="text-2xl font-extrabold mt-1"
              :class="
                dashboardData.metrics.profit >= 0
                  ? 'text-gray-800 dark:text-white'
                  : 'text-red-500'
              "
            >
              ৳ {{ Number(dashboardData.metrics.profit).toLocaleString() }}
            </h3>
          </div>
          <div
            class="p-3 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl text-emerald-600 dark:text-emerald-400"
          >
            <PresentationChartLineIcon class="w-6 h-6" />
          </div>
        </div>
        <div
          class="mt-4 flex items-center text-xs font-medium text-emerald-600"
        >
          <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-700"
            >Margin</span
          >
          <span class="ml-2">Rev - Cost</span>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div
        class="lg:col-span-2 bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700"
      >
        <h3 class="font-bold text-gray-800 dark:text-white mb-4">
          Revenue vs Cost Analytics
        </h3>
        <div class="h-80">
          <VueApexCharts
            type="area"
            height="100%"
            :options="chartOptions"
            :series="chartSeries"
          />
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 flex flex-col"
      >
        <div class="flex justify-between items-center mb-4">
          <h3
            class="font-bold text-gray-800 dark:text-white flex items-center gap-2"
          >
            <ExclamationTriangleIcon class="w-5 h-5 text-amber-500" />
            Low Stock Alerts
          </h3>
          <span
            class="px-2 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-full"
          >
            {{ dashboardData.low_stock.length }} Items
          </span>
        </div>

        <div class="space-y-4 overflow-y-auto custom-scrollbar flex-1">
          <div
            v-if="dashboardData.low_stock.length === 0"
            class="h-full flex flex-col items-center justify-center text-gray-400 text-sm"
          >
            <ShoppingBagIcon class="w-8 h-8 mb-2 opacity-50" />
            All stock levels are healthy!
          </div>
          <div
            v-for="product in dashboardData.low_stock"
            :key="product.id"
            class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700/50 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-600 transition"
          >
            <img
              :src="getImageUrl(product.image)"
              class="w-12 h-12 rounded-lg object-cover border border-gray-200"
              @error="$event.target.src = 'https://placehold.co/100?text=Err'"
            />
            <div class="flex-1 min-w-0">
              <h4
                class="text-sm font-bold text-gray-800 dark:text-white truncate"
              >
                {{ product.name }}
              </h4>
              <p class="text-xs text-gray-500">
                Alert Limit: {{ product.alert_quantity }}
              </p>
            </div>
            <div class="text-right">
              <span class="block text-lg font-bold text-red-500">{{
                product.stock_quantity
              }}</span>
              <span class="text-[10px] text-gray-400">Left</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div
        class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden"
      >
        <div class="p-6 border-b border-gray-100 dark:border-slate-700">
          <h3 class="font-bold text-gray-800 dark:text-white">
            Top Selling Products
          </h3>
        </div>
        <div class="p-0">
          <table class="w-full text-left text-sm">
            <thead
              class="bg-gray-50 dark:bg-slate-700/50 text-gray-500 font-medium"
            >
              <tr>
                <th class="px-6 py-3">Product</th>
                <th class="px-6 py-3 text-right">Sold Qty</th>
                <th class="px-6 py-3 text-right">Stock</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
              <tr v-if="dashboardData.top_products.length === 0">
                <td colspan="3" class="px-6 py-8 text-center text-gray-400">
                  No sales in this period.
                </td>
              </tr>
              <tr
                v-for="product in dashboardData.top_products"
                :key="product.id"
                class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition"
              >
                <td class="px-6 py-3">
                  <div class="flex items-center gap-3">
                    <img
                      :src="getImageUrl(product.image)"
                      class="w-8 h-8 rounded-md object-cover border"
                    />
                    <span class="font-medium text-gray-800 dark:text-white">{{
                      product.name
                    }}</span>
                  </div>
                </td>
                <td class="px-6 py-3 text-right font-bold text-indigo-600">
                  {{ product.total_sold }}
                </td>
                <td class="px-6 py-3 text-right text-gray-500">
                  {{ product.stock_quantity }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden"
      >
        <div
          class="p-6 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center"
        >
          <h3 class="font-bold text-gray-800 dark:text-white">
            Recent Transactions
          </h3>
          <router-link
            to="/sales"
            class="text-sm text-indigo-600 font-bold hover:underline"
          >
            View All
          </router-link>
        </div>
        <div class="p-0">
          <table class="w-full text-left text-sm">
            <thead
              class="bg-gray-50 dark:bg-slate-700/50 text-gray-500 font-medium"
            >
              <tr>
                <th class="px-6 py-3">Invoice</th>
                <th class="px-6 py-3">Customer</th>
                <th class="px-6 py-3 text-right">Amount</th>
                <th class="px-6 py-3 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
              <tr v-if="dashboardData.recent_sales.length === 0">
                <td colspan="4" class="px-6 py-8 text-center text-gray-400">
                  No recent transactions.
                </td>
              </tr>
              <tr
                v-for="sale in dashboardData.recent_sales"
                :key="sale.id"
                class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition"
              >
                <td class="px-6 py-3 font-mono text-xs text-gray-500">
                  {{ sale.invoice_no }}
                </td>
                <td class="px-6 py-3 font-medium text-gray-800 dark:text-white">
                  {{ sale.customer?.name || "Walk-in" }}
                </td>
                <td class="px-6 py-3 text-right font-bold">
                  ৳ {{ Number(sale.grand_total).toLocaleString() }}
                </td>
                <td class="px-6 py-3 text-center">
                  <span
                    class="px-2 py-1 rounded-full text-xs font-bold"
                    :class="
                      sale.payment_status === 'paid'
                        ? 'bg-green-100 text-green-700'
                        : 'bg-amber-100 text-amber-700'
                    "
                  >
                    {{ sale.payment_status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
