<script setup>
import { ref, onMounted, watch } from "vue";
import axios from "../axios";
import VueApexCharts from "vue3-apexcharts";
import {
  CurrencyBangladeshiIcon,
  ShoppingBagIcon,
  PresentationChartLineIcon,
  ArrowTrendingUpIcon,
  ExclamationTriangleIcon,
  ClockIcon,
  BanknotesIcon,
  TagIcon,
  CreditCardIcon,
  WalletIcon,
  ArrowPathIcon,
  ArchiveBoxIcon,
  DocumentTextIcon,
  CubeIcon,
} from "@heroicons/vue/24/outline";

// --- State ---
const loading = ref(false);
const filterRange = ref("today");

// Initial Data Structure
const data = ref({
  metrics: {
    range_sales: 0,
    range_returns: 0,
    range_profit: 0,
    range_purchases: 0,
    range_discount: 0,
    range_tax: 0,
    range_count: 0,
    range_cash: 0,
    range_digital: 0,
  },
  overall: {
    net_sales: 0,
    total_returns: 0,
    total_due: 0,
    total_collected: 0,
    inventory_value: 0,
  },
  inventory: { total_products: 0, low_stock: 0 },
  users: { total_customers: 0 },
  chart: { categories: [], series: [] },
  top_products: [],
  low_stock_list: [],
  filter_label: "Today",
});

// Chart Config
const chartOptions = ref({
  chart: {
    type: "area",
    height: 350,
    fontFamily: "inherit",
    toolbar: { show: false },
    animations: { enabled: true },
  },
  stroke: { curve: "smooth", width: 3 },
  colors: ["#6366F1", "#F43F5E"],
  fill: {
    type: "gradient",
    gradient: { shadeIntensity: 1, opacityFrom: 0.6, opacityTo: 0.1 },
  },
  dataLabels: { enabled: false },
  xaxis: {
    categories: [],
    labels: { style: { colors: "#9ca3af", fontSize: "12px" } },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    labels: {
      style: { colors: "#9ca3af", fontSize: "12px" },
      formatter: (val) => (val >= 1000 ? (val / 1000).toFixed(1) + "k" : val),
    },
  },
  grid: { borderColor: "#f3f4f6", strokeDashArray: 4 },
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
    const res = await axios.get(
      `/dashboard/overview?range=${filterRange.value}`,
    );
    if (res.data.status) {
      data.value = res.data.data;

      // Update Chart
      chartOptions.value = {
        ...chartOptions.value,
        xaxis: {
          ...chartOptions.value.xaxis,
          categories: data.value.chart.categories,
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

const getImageUrl = (path) =>
  path?.startsWith("http") ? path : `http://localhost:8000/storage/${path}`;

watch(filterRange, () => fetchData());
onMounted(() => fetchData());
</script>

<template>
  <div class="space-y-8 pb-10">
    <div
      class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:bg-slate-900 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800"
    >
      <div>
        <h1
          class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2"
        >
          <PresentationChartLineIcon class="w-8 h-8 text-indigo-600" />
          Executive Dashboard
        </h1>
        <p class="text-sm text-gray-500 mt-1">
          Showing data for
          <span class="font-bold text-indigo-600">{{ data.filter_label }}</span>
        </p>
      </div>

      <div class="relative min-w-[200px]">
        <select
          v-model="filterRange"
          class="w-full appearance-none bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 py-2.5 pl-4 pr-10 rounded-lg shadow-sm text-sm font-semibold focus:ring-2 focus:ring-indigo-500 text-gray-700 dark:text-gray-200 cursor-pointer"
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
        <ArrowTrendingUpIcon class="w-4 h-4" /> Main Performance
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <div
          class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 relative overflow-hidden"
        >
          <div
            class="absolute top-4 right-4 p-2 bg-indigo-50 rounded-lg text-indigo-600"
          >
            <CurrencyBangladeshiIcon class="w-6 h-6" />
          </div>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Net Sales
          </p>
          <h3
            class="text-2xl font-extrabold text-gray-800 dark:text-white mt-1"
          >
            ৳ {{ Number(data.metrics.range_sales).toLocaleString() }}
          </h3>
          <div
            class="mt-2 text-xs text-emerald-600 bg-emerald-50 w-max px-2 py-0.5 rounded font-bold"
          >
            Revenue
          </div>
        </div>

        <div
          class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 relative overflow-hidden"
        >
          <div
            class="absolute top-4 right-4 p-2 bg-emerald-50 rounded-lg text-emerald-600"
          >
            <BanknotesIcon class="w-6 h-6" />
          </div>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Gross Profit
          </p>
          <h3
            class="text-2xl font-extrabold mt-1"
            :class="
              data.metrics.range_profit >= 0
                ? 'text-emerald-600'
                : 'text-rose-500'
            "
          >
            ৳ {{ Number(data.metrics.range_profit).toLocaleString() }}
          </h3>
          <div class="mt-2 text-xs text-gray-400">Based on COGS</div>
        </div>

        <div
          class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 relative overflow-hidden"
        >
          <div
            class="absolute top-4 right-4 p-2 bg-rose-50 rounded-lg text-rose-600"
          >
            <ArrowPathIcon class="w-6 h-6" />
          </div>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Returns
          </p>
          <h3 class="text-2xl font-extrabold text-rose-500 mt-1">
            ৳ {{ Number(data.metrics.range_returns).toLocaleString() }}
          </h3>
          <div class="mt-2 text-xs text-gray-400">Refunded Amount</div>
        </div>

        <div
          class="bg-white dark:bg-slate-800 p-5 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-700 relative overflow-hidden"
        >
          <div
            class="absolute top-4 right-4 p-2 bg-orange-50 rounded-lg text-orange-600"
          >
            <ShoppingBagIcon class="w-6 h-6" />
          </div>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Purchases
          </p>
          <h3
            class="text-2xl font-extrabold text-gray-800 dark:text-white mt-1"
          >
            ৳ {{ Number(data.metrics.range_purchases).toLocaleString() }}
          </h3>
          <div class="mt-2 text-xs text-gray-400">Stock Investment</div>
        </div>
      </div>
    </div>

    <div>
      <h3
        class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 flex items-center gap-2"
      >
        <ClockIcon class="w-4 h-4" /> Activity Breakdown ({{
          data.filter_label
        }})
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
        <div
          class="bg-indigo-50/50 dark:bg-slate-800 border border-indigo-100 dark:border-slate-700 p-4 rounded-xl flex items-center justify-between"
        >
          <div>
            <p class="text-xs font-bold text-indigo-500 uppercase">Invoices</p>
            <h4
              class="text-xl font-extrabold text-gray-800 dark:text-white mt-1"
            >
              {{ data.metrics.range_count }}
            </h4>
          </div>
          <div
            class="p-2 bg-white dark:bg-slate-700 rounded-lg text-indigo-500"
          >
            <DocumentTextIcon class="w-6 h-6" />
          </div>
        </div>

        <div
          class="bg-orange-50/50 dark:bg-slate-800 border border-orange-100 dark:border-slate-700 p-4 rounded-xl flex items-center justify-between"
        >
          <div>
            <p class="text-xs font-bold text-orange-500 uppercase">Discounts</p>
            <h4
              class="text-xl font-extrabold text-gray-800 dark:text-white mt-1"
            >
              ৳ {{ Number(data.metrics.range_discount).toLocaleString() }}
            </h4>
          </div>
          <div
            class="p-2 bg-white dark:bg-slate-700 rounded-lg text-orange-500"
          >
            <TagIcon class="w-6 h-6" />
          </div>
        </div>

        <div
          class="bg-emerald-50/50 dark:bg-slate-800 border border-emerald-100 dark:border-slate-700 p-4 rounded-xl flex items-center justify-between"
        >
          <div>
            <p class="text-xs font-bold text-emerald-600 uppercase">
              Cash Sale
            </p>
            <h4
              class="text-xl font-extrabold text-gray-800 dark:text-white mt-1"
            >
              ৳ {{ Number(data.metrics.range_cash).toLocaleString() }}
            </h4>
          </div>
          <div
            class="p-2 bg-white dark:bg-slate-700 rounded-lg text-emerald-600"
          >
            <BanknotesIcon class="w-6 h-6" />
          </div>
        </div>

        <div
          class="bg-blue-50/50 dark:bg-slate-800 border border-blue-100 dark:border-slate-700 p-4 rounded-xl flex items-center justify-between"
        >
          <div>
            <p class="text-xs font-bold text-blue-500 uppercase">
              Digital / Card
            </p>
            <h4
              class="text-xl font-extrabold text-gray-800 dark:text-white mt-1"
            >
              ৳ {{ Number(data.metrics.range_digital).toLocaleString() }}
            </h4>
          </div>
          <div class="p-2 bg-white dark:bg-slate-700 rounded-lg text-blue-500">
            <CreditCardIcon class="w-6 h-6" />
          </div>
        </div>
      </div>
    </div>

    <div>
      <h3
        class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 flex items-center gap-2"
      >
        <ArchiveBoxIcon class="w-4 h-4" /> Lifetime Status
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div
          class="flex items-center p-4 bg-blue-50/50 dark:bg-slate-800 border border-blue-100 dark:border-slate-700 rounded-xl"
        >
          <div class="p-3 bg-blue-500 text-white rounded-lg">
            <CubeIcon class="w-6 h-6" />
          </div>
          <div class="ml-4">
            <p class="text-xs font-bold text-blue-500 uppercase">
              Inventory Value
            </p>
            <h4 class="text-xl font-extrabold text-gray-800 dark:text-white">
              ৳ {{ Number(data.overall.inventory_value).toLocaleString() }}
            </h4>
          </div>
        </div>

        <div
          class="flex items-center p-4 bg-red-50/50 dark:bg-slate-800 border border-red-100 dark:border-slate-700 rounded-xl"
        >
          <div class="p-3 bg-rose-500 text-white rounded-lg">
            <CreditCardIcon class="w-6 h-6" />
          </div>
          <div class="ml-4">
            <p class="text-xs font-bold text-rose-500 uppercase">Total Due</p>
            <h4 class="text-xl font-extrabold text-gray-800 dark:text-white">
              ৳ {{ Number(data.overall.total_due).toLocaleString() }}
            </h4>
          </div>
        </div>

        <div
          class="flex items-center p-4 bg-emerald-50/50 dark:bg-slate-800 border border-emerald-100 dark:border-slate-700 rounded-xl"
        >
          <div class="p-3 bg-emerald-500 text-white rounded-lg">
            <WalletIcon class="w-6 h-6" />
          </div>
          <div class="ml-4">
            <p class="text-xs font-bold text-emerald-500 uppercase">
              Cash Collected
            </p>
            <h4 class="text-xl font-extrabold text-gray-800 dark:text-white">
              ৳ {{ Number(data.overall.total_collected).toLocaleString() }}
            </h4>
          </div>
        </div>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-gray-200 dark:border-slate-800"
    >
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-bold text-gray-800 dark:text-white">
          Revenue vs Cost Analysis
        </h3>
        <div class="flex gap-2">
          <span
            class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded"
            >Revenue</span
          >
          <span
            class="text-xs font-bold text-rose-500 bg-rose-50 px-2 py-1 rounded"
            >Cost</span
          >
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
        <div class="p-5 border-b border-gray-100 dark:border-slate-800">
          <h3 class="font-bold text-gray-800 dark:text-white">
            Top Selling Products
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
                class="hover:bg-gray-50 dark:hover:bg-slate-800/50"
              >
                <td class="px-5 py-3 flex items-center gap-3">
                  <img
                    :src="getImageUrl(p.image)"
                    class="w-10 h-10 rounded-lg border object-cover"
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
                  No sales data
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
          <h3 class="font-bold text-gray-800 dark:text-white">
            Low Stock Alerts
          </h3>
          <span
            v-if="data.inventory.low_stock > 0"
            class="bg-rose-100 text-rose-600 px-3 py-1 rounded-full text-xs font-bold animate-pulse"
            >{{ data.inventory.low_stock }} Issues</span
          >
        </div>
        <div class="max-h-[350px] overflow-y-auto custom-scrollbar">
          <div
            v-for="p in data.low_stock_list"
            :key="p.id"
            class="flex items-center gap-4 p-4 border-b border-gray-50 dark:border-slate-800 last:border-0 hover:bg-rose-50/30"
          >
            <img
              :src="getImageUrl(p.image)"
              class="w-12 h-12 rounded-lg border object-cover"
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
              }}</span
              ><span class="text-[10px] text-gray-400 block">Left</span>
            </div>
          </div>
          <div
            v-if="data.low_stock_list.length === 0"
            class="p-10 text-center text-gray-400"
          >
            Stock healthy!
          </div>
        </div>
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
