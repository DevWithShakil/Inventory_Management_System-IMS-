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
  TagIcon,
  CreditCardIcon,
  WalletIcon,
} from "@heroicons/vue/24/outline";

// --- State ---
const loading = ref(false);
const filterRange = ref("today");
const dashboardData = ref({
  metrics: {
    revenue: 0,
    orders: 0,
    expense: 0,
    profit: 0,
    discount: 0,
    growth: 0,
    paid: 0,
    due: 0,
  },
  low_stock: [],
  top_products: [],
  recent_sales: [],
});

// Area Chart (Revenue vs Cost)
const chartOptions = ref({
  chart: {
    type: "area",
    height: 350,
    toolbar: { show: false },
    zoom: { enabled: false },
  },
  stroke: { curve: "smooth", width: 3 },
  colors: ["#6366F1", "#F43F5E"],
  fill: {
    type: "gradient",
    gradient: { shadeIntensity: 1, opacityFrom: 0.55, opacityTo: 0.05 },
  },
  dataLabels: { enabled: false },
  xaxis: {
    categories: [],
    labels: { style: { colors: "#9ca3af", fontSize: "12px" } },
  },
  yaxis: {
    labels: {
      style: { colors: "#9ca3af", fontSize: "12px" },
      formatter: (val) => (val >= 1000 ? (val / 1000).toFixed(1) + "k" : val),
    },
  },
  tooltip: {
    theme: "light",
    y: { formatter: (val) => "৳ " + Number(val).toLocaleString() },
  },
  legend: { position: "top", horizontalAlign: "right" },
});
const chartSeries = ref([
  { name: "Revenue", data: [] },
  { name: "Cost", data: [] },
]);

// Pie Chart (Payment Methods)
const pieOptions = ref({
  chart: { type: "donut", height: 350 },
  labels: [],
  colors: ["#10B981", "#F59E0B", "#3B82F6", "#6366F1"],
  legend: { position: "bottom" },
  plotOptions: { donut: { size: "65%" } },
});
const pieSeries = ref([]);

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

      // Update Area Chart
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

      // Update Pie Chart
      pieOptions.value = { ...pieOptions.value, labels: data.pie_chart.labels };
      pieSeries.value = data.pie_chart.series;
    }
  } catch (error) {
    console.error("Dashboard Error:", error);
  } finally {
    loading.value = false;
  }
};

const getImageUrl = (path) =>
  path?.startsWith("http") ? path : `http://localhost:8000/storage/${path}`;

watch(filterRange, () => fetchDashboardData());
onMounted(() => fetchDashboardData());
</script>

<template>
  <div class="space-y-6 pb-10">
    <div
      class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"
    >
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
          Business Overview
        </h1>
        <p class="text-sm text-gray-500">Real-time performance analytics.</p>
      </div>
      <div class="relative">
        <select
          v-model="filterRange"
          class="appearance-none bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 py-2 pl-4 pr-10 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 text-sm font-medium"
        >
          <option value="today">Today</option>
          <option value="yesterday">Yesterday</option>
          <option value="last_7_days">Last 7 Days</option>
          <option value="this_month">This Month</option>
          <option value="all_time">All Time</option>
        </select>
        <ClockIcon
          class="w-4 h-4 absolute right-3 top-3 text-gray-400 pointer-events-none"
        />
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div
        class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 flex justify-between items-start"
      >
        <div>
          <p class="text-xs font-bold text-gray-400 uppercase">Total Revenue</p>
          <h3
            class="text-2xl font-extrabold text-gray-800 dark:text-white mt-1"
          >
            ৳ {{ Number(dashboardData.metrics.revenue).toLocaleString() }}
          </h3>
          <div
            class="mt-2 flex items-center text-xs font-medium"
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
            <span>{{ Math.abs(dashboardData.metrics.growth) }}%</span>
          </div>
        </div>
        <div
          class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl text-indigo-600"
        >
          <CurrencyBangladeshiIcon class="w-6 h-6" />
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 flex justify-between items-start"
      >
        <div>
          <p class="text-xs font-bold text-gray-400 uppercase">Net Profit</p>
          <h3
            class="text-2xl font-extrabold mt-1"
            :class="
              dashboardData.metrics.profit >= 0
                ? 'text-emerald-600'
                : 'text-red-500'
            "
          >
            ৳ {{ Number(dashboardData.metrics.profit).toLocaleString() }}
          </h3>
          <div class="mt-2 text-xs text-gray-400">Rev - Cost</div>
        </div>
        <div
          class="p-3 rounded-xl"
          :class="
            dashboardData.metrics.profit >= 0
              ? 'bg-emerald-50 text-emerald-600'
              : 'bg-red-50 text-red-600'
          "
        >
          <PresentationChartLineIcon class="w-6 h-6" />
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 flex justify-between items-start"
      >
        <div>
          <p class="text-xs font-bold text-gray-400 uppercase">Total Orders</p>
          <h3
            class="text-2xl font-extrabold text-gray-800 dark:text-white mt-1"
          >
            {{ dashboardData.metrics.orders }}
          </h3>
          <div class="mt-2 text-xs text-blue-500">Invoices generated</div>
        </div>
        <div
          class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-xl text-blue-600"
        >
          <ShoppingBagIcon class="w-6 h-6" />
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 flex justify-between items-start"
      >
        <div>
          <p class="text-xs font-bold text-gray-400 uppercase">
            Expense (COGS)
          </p>
          <h3
            class="text-2xl font-extrabold text-gray-800 dark:text-white mt-1"
          >
            ৳ {{ Number(dashboardData.metrics.expense).toLocaleString() }}
          </h3>
          <div class="mt-2 text-xs text-rose-500">Product Buying Cost</div>
        </div>
        <div
          class="p-3 bg-rose-50 dark:bg-rose-900/30 rounded-xl text-rose-600"
        >
          <BanknotesIcon class="w-6 h-6" />
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div
        class="bg-emerald-50/50 dark:bg-slate-800 border border-emerald-100 dark:border-slate-700 p-4 rounded-xl flex items-center gap-4"
      >
        <div class="p-3 bg-emerald-100 text-emerald-600 rounded-lg">
          <WalletIcon class="w-6 h-6" />
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase font-bold">
            Total Collected (Paid)
          </p>
          <h4 class="text-xl font-bold text-emerald-700">
            ৳ {{ Number(dashboardData.metrics.paid).toLocaleString() }}
          </h4>
        </div>
      </div>

      <div
        class="bg-red-50/50 dark:bg-slate-800 border border-red-100 dark:border-slate-700 p-4 rounded-xl flex items-center gap-4"
      >
        <div class="p-3 bg-red-100 text-red-600 rounded-lg">
          <CreditCardIcon class="w-6 h-6" />
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase font-bold">Pending Due</p>
          <h4 class="text-xl font-bold text-red-600">
            ৳ {{ Number(dashboardData.metrics.due).toLocaleString() }}
          </h4>
        </div>
      </div>

      <div
        class="bg-orange-50/50 dark:bg-slate-800 border border-orange-100 dark:border-slate-700 p-4 rounded-xl flex items-center gap-4"
      >
        <div class="p-3 bg-orange-100 text-orange-600 rounded-lg">
          <TagIcon class="w-6 h-6" />
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase font-bold">
            Total Discount
          </p>
          <h4 class="text-xl font-bold text-orange-600">
            ৳ {{ Number(dashboardData.metrics.discount).toLocaleString() }}
          </h4>
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
        <h3 class="font-bold text-gray-800 dark:text-white mb-4">
          Payment Distribution
        </h3>
        <div
          class="flex-1 flex items-center justify-center"
          v-if="pieSeries.length > 0"
        >
          <VueApexCharts
            type="donut"
            width="380"
            :options="pieOptions"
            :series="pieSeries"
          />
        </div>
        <div
          v-else
          class="h-64 flex items-center justify-center text-gray-400 text-sm"
        >
          No transaction data
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div
        class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 overflow-hidden"
      >
        <div class="p-5 border-b border-gray-100 font-bold text-gray-800">
          Top Selling Products
        </div>
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 text-gray-500">
            <tr>
              <th class="px-5 py-3">Product</th>
              <th class="px-5 py-3 text-right">Sold</th>
              <th class="px-5 py-3 text-right">Stock</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr
              v-for="p in dashboardData.top_products"
              :key="p.id"
              class="hover:bg-gray-50"
            >
              <td class="px-5 py-3 flex items-center gap-3">
                <img
                  :src="getImageUrl(p.image)"
                  class="w-8 h-8 rounded border object-cover"
                />
                <span class="font-medium text-gray-700">{{ p.name }}</span>
              </td>
              <td class="px-5 py-3 text-right font-bold text-indigo-600">
                {{ p.total_sold }}
              </td>
              <td class="px-5 py-3 text-right text-gray-500">
                {{ p.stock_quantity }}
              </td>
            </tr>
            <tr v-if="dashboardData.top_products.length === 0">
              <td colspan="3" class="p-5 text-center text-gray-400">No data</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 overflow-hidden"
      >
        <div
          class="p-5 border-b border-gray-100 flex justify-between items-center"
        >
          <h3 class="font-bold text-gray-800">Low Stock Alerts</h3>
          <span
            class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-xs font-bold"
            >{{ dashboardData.low_stock.length }} Items</span
          >
        </div>
        <div class="max-h-80 overflow-y-auto">
          <div
            v-for="p in dashboardData.low_stock"
            :key="p.id"
            class="flex items-center gap-3 p-4 border-b border-gray-50 last:border-0 hover:bg-gray-50"
          >
            <img
              :src="getImageUrl(p.image)"
              class="w-10 h-10 rounded border object-cover"
            />
            <div class="flex-1">
              <h4 class="text-sm font-bold text-gray-800">{{ p.name }}</h4>
              <p class="text-xs text-gray-500">
                Alert Limit: {{ p.alert_quantity }}
              </p>
            </div>
            <div class="text-right">
              <span class="text-lg font-bold text-red-500">{{
                p.stock_quantity
              }}</span>
              <span class="text-[10px] text-gray-400 block">Left</span>
            </div>
          </div>
          <div
            v-if="dashboardData.low_stock.length === 0"
            class="p-10 text-center text-gray-400"
          >
            Stock healthy!
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
