<script setup>
import { ref, onMounted, watch, computed } from "vue";
import axios from "../axios";
import VueApexCharts from "vue3-apexcharts";
import {
  CurrencyBangladeshiIcon,
  PresentationChartLineIcon,
  ClockIcon,
  BanknotesIcon,
  WalletIcon,
  CubeIcon,
  ExclamationTriangleIcon,
  ShoppingBagIcon,
  DocumentTextIcon,
  TagIcon,
  ArrowPathIcon,
  CreditCardIcon,
} from "@heroicons/vue/24/outline";

// --- State ---
const loading = ref(false);
const filterRange = ref("today");
const user = ref(JSON.parse(localStorage.getItem("user") || "{}"));

const data = ref({
  metrics: {
    range_sales: 0,
    range_gross_sales: 0,
    range_returns: 0,
    range_profit: 0,
    range_expenses: 0,
    range_cash: 0,
    range_digital: 0,
    range_due: 0,
    range_count: 0,
    range_discount: 0,
  },
  overall: {
    inventory_value: 0,
    damaged_stock_value: 0,
  },
  inventory: { low_stock: 0 },
  chart: { categories: [], series: [] },
  top_products: [],
  low_stock_list: [],
  filter_label: "Today",
});

// --- Chart Options ---
const chartOptions = computed(() => ({
  chart: {
    type: "area",
    height: 350,
    fontFamily: "inherit",
    toolbar: { show: false },
    zoom: { enabled: false },
  },
  stroke: { curve: "smooth", width: 2 },
  colors: user.value.role === "admin" ? ["#10B981", "#F43F5E"] : ["#10B981"],
  fill: { type: "gradient", gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
  dataLabels: { enabled: false },
  xaxis: {
    categories: data.value.chart.categories,
    axisBorder: { show: false },
    axisTicks: { show: false },
    labels: { style: { colors: "#9CA3AF", fontSize: "11px" } },
  },
  yaxis: {
    labels: {
      style: { colors: "#9CA3AF", fontSize: "11px" },
      formatter: (val) => (val >= 1000 ? (val / 1000).toFixed(1) + "k" : val),
    },
  },
  grid: { borderColor: "#F3F4F6", strokeDashArray: 4 },
  tooltip: { theme: "light" },
  legend: {
    show: user.value.role === "admin",
    position: "top",
    horizontalAlign: "right",
  },
}));

const chartSeries = ref([]);

// --- Fetch Data ---
const fetchData = async () => {
  loading.value = true;
  try {
    const res = await axios.get(
      `/dashboard/overview?range=${filterRange.value}`,
    );
    if (res.data.status) {
      data.value = res.data.data;
      chartSeries.value = res.data.data.chart.series.filter(
        (s) => s.data && s.data.length > 0,
      );
    }
  } catch (error) {
    console.error("Dashboard Error:", error);
  } finally {
    loading.value = false;
  }
};

const getImageUrl = (path) =>
  path?.startsWith("http") ? path : `http://localhost:8000/storage/${path}`;
const formatCurrency = (amount) => "৳ " + Number(amount || 0).toLocaleString();

watch(filterRange, () => fetchData());
onMounted(() => fetchData());
</script>

<template>
  <div class="space-y-6 pb-20">
    <div
      class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:bg-slate-900 p-5 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800"
    >
      <div>
        <h1
          class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2"
        >
          <PresentationChartLineIcon class="w-8 h-8 text-indigo-600" />
          {{ user.role === "admin" ? "Executive Dashboard" : "Sales Overview" }}
        </h1>
        <p class="text-sm text-gray-500 mt-1">
          Performance snapshot for
          <span class="font-bold text-indigo-600">{{ data.filter_label }}</span>
        </p>
      </div>

      <div class="relative min-w-[200px]">
        <select
          v-model="filterRange"
          class="w-full appearance-none bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 py-2.5 pl-4 pr-10 rounded-xl shadow-sm text-sm font-bold focus:ring-2 focus:ring-indigo-500 text-gray-700 dark:text-gray-200 cursor-pointer"
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

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border-l-4 border-indigo-500 relative overflow-hidden"
      >
        <div class="flex justify-between items-start">
          <div>
            <p
              class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
            >
              Net Sales
            </p>
            <h3
              class="text-3xl font-extrabold text-gray-800 dark:text-white mt-2"
            >
              {{ formatCurrency(data.metrics.range_sales) }}
            </h3>
          </div>
          <div
            class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-full text-indigo-600"
          >
            <CurrencyBangladeshiIcon class="w-8 h-8" />
          </div>
        </div>
        <div class="mt-4 text-xs text-gray-400">
          Gross Sales: {{ formatCurrency(data.metrics.range_gross_sales) }}
        </div>
      </div>

      <div
        v-if="user.role === 'admin'"
        class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border-l-4 border-emerald-500 relative overflow-hidden"
      >
        <div class="flex justify-between items-start">
          <div>
            <p
              class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
            >
              Net Profit
            </p>
            <h3
              class="text-3xl font-extrabold mt-2"
              :class="
                data.metrics.range_profit >= 0
                  ? 'text-emerald-600'
                  : 'text-rose-500'
              "
            >
              {{ formatCurrency(data.metrics.range_profit) }}
            </h3>
          </div>
          <div
            class="p-3 bg-emerald-50 dark:bg-emerald-900/30 rounded-full text-emerald-600"
          >
            <BanknotesIcon class="w-8 h-8" />
          </div>
        </div>
        <div class="mt-4 text-xs text-gray-400">
          After deducting expenses & costs
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border-l-4 border-blue-500 relative overflow-hidden"
      >
        <div class="flex justify-between items-start mb-2">
          <div>
            <p
              class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
            >
              Collection
            </p>
            <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1">
              {{
                formatCurrency(
                  data.metrics.range_cash + data.metrics.range_digital,
                )
              }}
            </h3>
          </div>
          <div
            class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-full text-blue-600"
          >
            <WalletIcon class="w-8 h-8" />
          </div>
        </div>

        <div class="space-y-3 mt-2">
          <div>
            <div class="flex justify-between text-[10px] mb-1">
              <span class="text-gray-500">Collected</span>
              <span class="font-bold text-blue-600">{{
                formatCurrency(
                  data.metrics.range_cash + data.metrics.range_digital,
                )
              }}</span>
            </div>
            <div
              class="w-full bg-gray-100 rounded-full h-1.5 dark:bg-slate-700"
            >
              <div
                class="bg-blue-500 h-1.5 rounded-full"
                :style="{
                  width:
                    ((data.metrics.range_cash + data.metrics.range_digital) /
                      (data.metrics.range_sales || 1)) *
                      100 +
                    '%',
                }"
              ></div>
            </div>
          </div>
          <div>
            <div class="flex justify-between text-[10px] mb-1">
              <span class="text-gray-500 flex items-center gap-1"
                >Due Amount
                <ExclamationTriangleIcon
                  v-if="data.metrics.range_due > 0"
                  class="w-3 h-3 text-orange-500"
              /></span>
              <span class="font-bold text-orange-500">{{
                formatCurrency(data.metrics.range_due)
              }}</span>
            </div>
            <div
              class="w-full bg-gray-100 rounded-full h-1.5 dark:bg-slate-700"
            >
              <div
                class="bg-orange-400 h-1.5 rounded-full"
                :style="{
                  width:
                    (data.metrics.range_due / (data.metrics.range_sales || 1)) *
                      100 +
                    '%',
                }"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div
        class="bg-white dark:bg-slate-900 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 flex flex-col items-center justify-center text-center hover:bg-gray-50 transition"
      >
        <div class="p-2 bg-purple-50 text-purple-600 rounded-lg mb-2">
          <DocumentTextIcon class="w-6 h-6" />
        </div>
        <h4 class="text-2xl font-bold text-gray-800 dark:text-white">
          {{ data.metrics.range_count }}
        </h4>
        <p class="text-xs text-gray-500 font-medium uppercase mt-1">
          Total Invoices
        </p>
      </div>

      <div
        class="bg-white dark:bg-slate-900 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 flex flex-col items-center justify-center text-center hover:bg-orange-50/50 transition"
      >
        <div class="p-2 bg-orange-50 text-orange-600 rounded-lg mb-2">
          <TagIcon class="w-6 h-6" />
        </div>
        <h4 class="text-2xl font-bold text-orange-600">
          {{ formatCurrency(data.metrics.range_discount) }}
        </h4>
        <p class="text-xs text-gray-500 font-medium uppercase mt-1">
          Total Discount
        </p>
      </div>

      <div
        class="bg-white dark:bg-slate-900 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 flex flex-col items-center justify-center text-center hover:bg-rose-50/50 transition"
      >
        <div class="p-2 bg-rose-50 text-rose-600 rounded-lg mb-2">
          <ArrowPathIcon class="w-6 h-6" />
        </div>
        <h4 class="text-2xl font-bold text-rose-600">
          {{ formatCurrency(data.metrics.range_returns) }}
        </h4>
        <p class="text-xs text-gray-500 font-medium uppercase mt-1">
          Total Returns
        </p>
      </div>

      <div
        v-if="user.role === 'admin'"
        class="bg-white dark:bg-slate-900 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 flex flex-col items-center justify-center text-center hover:bg-gray-50 transition"
      >
        <div class="p-2 bg-gray-100 text-gray-600 rounded-lg mb-2">
          <CreditCardIcon class="w-6 h-6" />
        </div>
        <h4 class="text-2xl font-bold text-gray-700 dark:text-gray-300">
          {{ formatCurrency(data.metrics.range_expenses) }}
        </h4>
        <p class="text-xs text-gray-500 font-medium uppercase mt-1">Expenses</p>
      </div>

      <div
        v-else
        class="bg-white dark:bg-slate-900 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 flex flex-col items-center justify-center text-center hover:bg-red-50 transition"
      >
        <div class="p-2 bg-red-50 text-red-600 rounded-lg mb-2">
          <ExclamationTriangleIcon class="w-6 h-6" />
        </div>
        <h4 class="text-2xl font-bold text-red-600">
          {{ data.inventory.low_stock }}
        </h4>
        <p class="text-xs text-gray-500 font-medium uppercase mt-1">
          Low Stock
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div
        class="lg:col-span-2 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-800"
      >
        <h3
          class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2 mb-6"
        >
          <PresentationChartLineIcon class="w-5 h-5 text-gray-400" />
          Business Trends
        </h3>
        <div class="h-[350px] w-full">
          <VueApexCharts
            type="area"
            height="100%"
            :options="chartOptions"
            :series="chartSeries"
          />
        </div>
      </div>

      <div class="space-y-6">
        <div
          v-if="user.role === 'admin'"
          class="bg-gradient-to-br from-indigo-600 to-indigo-800 p-6 rounded-2xl shadow-lg text-white relative overflow-hidden"
        >
          <CubeIcon
            class="absolute -bottom-4 -right-4 w-32 h-32 text-white opacity-10"
          />
          <p class="text-indigo-100 text-sm font-medium">
            Total Inventory Asset
          </p>
          <h3 class="text-3xl font-extrabold mt-2">
            {{ formatCurrency(data.overall.inventory_value) }}
          </h3>

          <div
            class="mt-6 pt-4 border-t border-white/10 flex justify-between items-center text-sm"
          >
            <span class="flex items-center gap-2"
              ><ExclamationTriangleIcon class="w-4 h-4 text-orange-300" />
              Damaged Value:</span
            >
            <span class="font-bold">{{
              formatCurrency(data.overall.damaged_stock_value)
            }}</span>
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
              <ExclamationTriangleIcon class="w-5 h-5 text-rose-500" /> Critical
              Stock
            </h3>
          </div>
          <div class="flex-1 overflow-y-auto max-h-[300px] custom-scrollbar">
            <div
              v-for="p in data.low_stock_list"
              :key="p.id"
              class="flex items-center gap-3 p-4 border-b border-gray-50 dark:border-slate-800 last:border-0 hover:bg-rose-50/20 transition"
            >
              <img
                :src="getImageUrl(p.image)"
                class="w-10 h-10 rounded-lg border dark:border-slate-700 object-cover"
              />
              <div class="flex-1 min-w-0">
                <h4
                  class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate"
                >
                  {{ p.name }}
                </h4>
                <span class="text-[10px] text-gray-400"
                  >Limit: {{ p.alert_quantity }}</span
                >
              </div>
              <div class="text-right">
                <span class="text-lg font-bold text-rose-600">{{
                  p.stock_quantity
                }}</span>
                <span class="text-[9px] text-gray-400 block">LEFT</span>
              </div>
            </div>
            <div
              v-if="data.low_stock_list.length === 0"
              class="p-6 text-center text-xs text-gray-400"
            >
              All stock levels are healthy.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-800 overflow-hidden"
    >
      <div class="p-5 border-b border-gray-100 dark:border-slate-800">
        <h3
          class="font-bold text-gray-800 dark:text-white flex items-center gap-2"
        >
          <ShoppingBagIcon class="w-5 h-5 text-indigo-500" /> Top Selling
          Products
        </h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead
            class="bg-gray-50 dark:bg-slate-800 text-gray-500 uppercase text-xs"
          >
            <tr>
              <th class="px-6 py-3">Product</th>
              <th class="px-6 py-3 text-right">Sold Qty</th>
              <th class="px-6 py-3 text-right">Current Stock</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
            <tr
              v-for="p in data.top_products"
              :key="p.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >
              <td class="px-6 py-4 flex items-center gap-3">
                <img
                  :src="getImageUrl(p.image)"
                  class="w-10 h-10 rounded-lg border dark:border-slate-700 object-cover"
                />
                <span class="font-bold text-gray-700 dark:text-gray-200">{{
                  p.name
                }}</span>
              </td>
              <td class="px-6 py-4 text-right font-bold text-indigo-600">
                {{ p.total_sold }}
              </td>
              <td class="px-6 py-4 text-right text-gray-500">
                {{ p.stock_quantity }}
              </td>
            </tr>
            <tr v-if="data.top_products.length === 0">
              <td colspan="3" class="p-8 text-center text-gray-400">
                No sales record found for this period.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
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
