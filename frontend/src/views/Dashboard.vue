<script setup>
import { ref, onMounted, watch, computed } from "vue";
import { useRouter } from "vue-router";
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
  ComputerDesktopIcon,
  UserPlusIcon,
  QueueListIcon,
  ClipboardDocumentListIcon,
  SparklesIcon,
  UserCircleIcon,
} from "@heroicons/vue/24/outline";

// --- State ---
const router = useRouter();
const loading = ref(true); // Default loading true
const filterRange = ref("today");
const user = ref(JSON.parse(localStorage.getItem("user") || "{}"));

// 🔥 Greeting Logic
const timeGreeting = computed(() => {
  const hour = new Date().getHours();
  if (hour >= 5 && hour < 12) return "Good Morning";
  if (hour >= 12 && hour < 17) return "Good Afternoon";
  return "Good Evening";
});

// 🔥 Formatted Date
const currentDate = computed(() => {
  return new Date().toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
});

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
  recent_sales: [],
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
    // Fake delay for smoother transition (remove in production if super fast)
    setTimeout(() => {
      loading.value = false;
    }, 500);
  }
};

const getImageUrl = (path) =>
  path?.startsWith("http") ? path : `http://localhost:8000/storage/${path}`;
const formatCurrency = (amount) => "৳ " + Number(amount || 0).toLocaleString();

const navigateTo = (path) => router.push(path);

watch(filterRange, () => fetchData());
onMounted(() => fetchData());
</script>

<template>
  <div class="space-y-6 pb-20">
    <div
      class="bg-white dark:bg-slate-900 rounded-xl p-4 border-l-4 border-indigo-600 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4 transition-all"
    >
      <div v-if="loading" class="flex items-center gap-3 w-full md:w-auto">
        <div
          class="w-12 h-12 bg-gray-200 dark:bg-slate-800 rounded-full animate-pulse"
        ></div>
        <div class="flex flex-col gap-2">
          <div
            class="w-40 h-4 bg-gray-200 dark:bg-slate-800 rounded animate-pulse"
          ></div>
          <div
            class="w-24 h-3 bg-gray-200 dark:bg-slate-800 rounded animate-pulse"
          ></div>
        </div>
      </div>

      <div v-else class="flex items-center gap-3">
        <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-full">
          <UserCircleIcon
            class="w-8 h-8 text-indigo-600 dark:text-indigo-400"
          />
        </div>
        <div>
          <h2
            class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"
          >
            {{ timeGreeting }}, {{ user.name }}
          </h2>
          <p class="text-xs text-gray-500 font-medium">
            {{ currentDate }} •
            <span class="uppercase text-indigo-600 font-bold"
              >{{ user.role }} Panel</span
            >
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2 w-full md:w-auto">
        <span class="text-xs font-bold text-gray-400 uppercase hidden sm:block"
          >Timeline:</span
        >
        <div class="relative min-w-[160px] w-full md:w-auto">
          <select
            v-model="filterRange"
            class="w-full appearance-none bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 py-2 pl-3 pr-8 rounded-lg text-xs font-bold focus:ring-2 focus:ring-indigo-500 text-gray-700 dark:text-gray-200 cursor-pointer transition-colors"
          >
            <option value="today">Today</option>
            <option value="yesterday">Yesterday</option>
            <option value="last_7_days">Last 7 Days</option>
            <option value="this_month">This Month</option>
            <option value="last_month">Last Month</option>
            <option value="all_time">All Time</option>
          </select>
          <ClockIcon
            class="w-4 h-4 absolute right-2.5 top-2 text-gray-400 pointer-events-none"
          />
        </div>
      </div>
    </div>

    <div>
      <div
        class="grid gap-3"
        :class="
          user.role === 'admin'
            ? 'grid-cols-2 md:grid-cols-4'
            : 'grid-cols-2 md:grid-cols-3'
        "
      >
        <button
          @click="navigateTo('/pos')"
          class="flex items-center justify-center gap-2 p-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-sm transition transform hover:-translate-y-0.5 active:scale-95"
        >
          <ComputerDesktopIcon class="w-5 h-5" />
          <span class="font-bold text-sm">POS System</span>
        </button>

        <button
          @click="navigateTo('/customers')"
          class="flex items-center justify-center gap-2 p-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:border-indigo-300 text-gray-700 dark:text-gray-200 rounded-lg shadow-sm transition hover:bg-gray-50 dark:hover:bg-slate-700"
        >
          <UserPlusIcon class="w-5 h-5 text-blue-500" />
          <span class="font-medium text-sm">Customers</span>
        </button>

        <button
          v-if="user.role === 'admin'"
          @click="navigateTo('/inventory')"
          class="flex items-center justify-center gap-2 p-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:border-indigo-300 text-gray-700 dark:text-gray-200 rounded-lg shadow-sm transition hover:bg-gray-50 dark:hover:bg-slate-700"
        >
          <QueueListIcon class="w-5 h-5 text-emerald-500" />
          <span class="font-medium text-sm">Stock List</span>
        </button>

        <button
          @click="navigateTo('/sales')"
          class="flex items-center justify-center gap-2 p-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:border-indigo-300 text-gray-700 dark:text-gray-200 rounded-lg shadow-sm transition hover:bg-gray-50 dark:hover:bg-slate-700"
        >
          <ClipboardDocumentListIcon class="w-5 h-5 text-orange-500" />
          <span class="font-medium text-sm">History</span>
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <template v-if="loading">
        <div
          v-for="i in user.role === 'admin' ? 3 : 2"
          :key="i"
          class="bg-white dark:bg-slate-900 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 h-[140px] animate-pulse relative"
        >
          <div class="flex justify-between items-start">
            <div class="space-y-3">
              <div class="h-3 w-20 bg-gray-200 dark:bg-slate-800 rounded"></div>
              <div class="h-8 w-32 bg-gray-200 dark:bg-slate-800 rounded"></div>
            </div>
            <div
              class="w-10 h-10 bg-gray-200 dark:bg-slate-800 rounded-lg"
            ></div>
          </div>
          <div
            class="mt-4 h-2 w-24 bg-gray-200 dark:bg-slate-800 rounded"
          ></div>
        </div>
      </template>

      <template v-else>
        <div
          class="bg-white dark:bg-slate-900 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 relative overflow-hidden transition hover:shadow-md"
        >
          <div class="flex justify-between items-start">
            <div>
              <p
                class="text-xs font-bold text-gray-400 uppercase tracking-wider"
              >
                Net Sales
              </p>
              <h3
                class="text-2xl font-extrabold text-gray-800 dark:text-white mt-1"
              >
                {{ formatCurrency(data.metrics.range_sales) }}
              </h3>
            </div>
            <div
              class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-indigo-600"
            >
              <CurrencyBangladeshiIcon class="w-6 h-6" />
            </div>
          </div>
          <div class="mt-3 text-[10px] text-gray-400 font-medium">
            Gross: {{ formatCurrency(data.metrics.range_gross_sales) }}
          </div>
        </div>

        <div
          v-if="user.role === 'admin'"
          class="bg-white dark:bg-slate-900 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 relative overflow-hidden transition hover:shadow-md"
        >
          <div class="flex justify-between items-start">
            <div>
              <p
                class="text-xs font-bold text-gray-400 uppercase tracking-wider"
              >
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
            </div>
            <div
              class="p-2 bg-emerald-50 dark:bg-emerald-900/30 rounded-lg text-emerald-600"
            >
              <BanknotesIcon class="w-6 h-6" />
            </div>
          </div>
          <div class="mt-3 text-[10px] text-gray-400 font-medium">
            Net Income after expenses
          </div>
        </div>

        <div
          class="bg-white dark:bg-slate-900 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 relative overflow-hidden transition hover:shadow-md"
        >
          <div class="flex justify-between items-start mb-2">
            <div>
              <p
                class="text-xs font-bold text-gray-400 uppercase tracking-wider"
              >
                Collection
              </p>
              <h3 class="text-xl font-bold text-gray-800 dark:text-white mt-1">
                {{
                  formatCurrency(
                    data.metrics.range_cash + data.metrics.range_digital,
                  )
                }}
              </h3>
            </div>
            <div
              class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg text-blue-600"
            >
              <WalletIcon class="w-6 h-6" />
            </div>
          </div>

          <div class="space-y-2 mt-2">
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
                class="w-full bg-gray-100 rounded-full h-1 dark:bg-slate-700"
              >
                <div
                  class="bg-blue-500 h-1 rounded-full transition-all duration-1000"
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
                  >Due
                  <ExclamationTriangleIcon
                    v-if="data.metrics.range_due > 0"
                    class="w-3 h-3 text-orange-500"
                /></span>
                <span class="font-bold text-orange-500">{{
                  formatCurrency(data.metrics.range_due)
                }}</span>
              </div>
              <div
                class="w-full bg-gray-100 rounded-full h-1 dark:bg-slate-700"
              >
                <div
                  class="bg-orange-400 h-1 rounded-full transition-all duration-1000"
                  :style="{
                    width:
                      (data.metrics.range_due /
                        (data.metrics.range_sales || 1)) *
                        100 +
                      '%',
                  }"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <template v-if="loading">
        <div
          v-for="i in 4"
          :key="i"
          class="bg-white dark:bg-slate-900 p-3 rounded-lg border border-gray-100 dark:border-slate-800 flex items-center gap-3 shadow-sm animate-pulse"
        >
          <div class="w-10 h-10 bg-gray-200 dark:bg-slate-800 rounded-md"></div>
          <div class="flex flex-col gap-1 w-full">
            <div class="h-4 w-12 bg-gray-200 dark:bg-slate-800 rounded"></div>
            <div class="h-2 w-16 bg-gray-200 dark:bg-slate-800 rounded"></div>
          </div>
        </div>
      </template>

      <template v-else>
        <div
          class="bg-white dark:bg-slate-900 p-3 rounded-lg border border-gray-100 dark:border-slate-800 flex items-center gap-3 shadow-sm transition hover:shadow-md"
        >
          <div class="p-2 bg-purple-50 text-purple-600 rounded-md">
            <DocumentTextIcon class="w-5 h-5" />
          </div>
          <div>
            <h4 class="text-lg font-bold text-gray-800 dark:text-white">
              {{ data.metrics.range_count }}
            </h4>
            <p class="text-[10px] text-gray-500 uppercase font-bold">
              Invoices
            </p>
          </div>
        </div>

        <div
          class="bg-white dark:bg-slate-900 p-3 rounded-lg border border-gray-100 dark:border-slate-800 flex items-center gap-3 shadow-sm transition hover:shadow-md"
        >
          <div class="p-2 bg-orange-50 text-orange-600 rounded-md">
            <TagIcon class="w-5 h-5" />
          </div>
          <div>
            <h4 class="text-lg font-bold text-orange-600">
              {{ formatCurrency(data.metrics.range_discount) }}
            </h4>
            <p class="text-[10px] text-gray-500 uppercase font-bold">
              Discount
            </p>
          </div>
        </div>

        <div
          class="bg-white dark:bg-slate-900 p-3 rounded-lg border border-gray-100 dark:border-slate-800 flex items-center gap-3 shadow-sm transition hover:shadow-md"
        >
          <div class="p-2 bg-rose-50 text-rose-600 rounded-md">
            <ArrowPathIcon class="w-5 h-5" />
          </div>
          <div>
            <h4 class="text-lg font-bold text-rose-600">
              {{ formatCurrency(data.metrics.range_returns) }}
            </h4>
            <p class="text-[10px] text-gray-500 uppercase font-bold">Returns</p>
          </div>
        </div>

        <div
          v-if="user.role === 'admin'"
          class="bg-white dark:bg-slate-900 p-3 rounded-lg border border-gray-100 dark:border-slate-800 flex items-center gap-3 shadow-sm transition hover:shadow-md"
        >
          <div class="p-2 bg-gray-100 text-gray-600 rounded-md">
            <CreditCardIcon class="w-5 h-5" />
          </div>
          <div>
            <h4 class="text-lg font-bold text-gray-700 dark:text-gray-300">
              {{ formatCurrency(data.metrics.range_expenses) }}
            </h4>
            <p class="text-[10px] text-gray-500 uppercase font-bold">
              Expenses
            </p>
          </div>
        </div>
        <div
          v-else
          class="bg-white dark:bg-slate-900 p-3 rounded-lg border border-gray-100 dark:border-slate-800 flex items-center gap-3 shadow-sm transition hover:shadow-md"
        >
          <div class="p-2 bg-red-50 text-red-600 rounded-md">
            <ExclamationTriangleIcon class="w-5 h-5" />
          </div>
          <div>
            <h4 class="text-lg font-bold text-red-600">
              {{ data.inventory.low_stock }}
            </h4>
            <p class="text-[10px] text-gray-500 uppercase font-bold">
              Low Stock
            </p>
          </div>
        </div>
      </template>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div
        v-if="loading"
        class="lg:col-span-2 bg-white dark:bg-slate-900 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 h-[380px] animate-pulse"
      >
        <div class="h-4 w-32 bg-gray-200 dark:bg-slate-800 rounded mb-6"></div>
        <div class="h-full bg-gray-100 dark:bg-slate-800/50 rounded-lg"></div>
      </div>

      <div
        v-else
        class="lg:col-span-2 bg-white dark:bg-slate-900 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800"
      >
        <h3
          class="text-sm font-bold text-gray-800 dark:text-white flex items-center gap-2 mb-4"
        >
          <PresentationChartLineIcon class="w-4 h-4 text-gray-400" /> Business
          Trends
        </h3>
        <div class="h-[300px] w-full">
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
          v-if="loading"
          class="h-[140px] bg-gray-200 dark:bg-slate-800 rounded-xl animate-pulse"
        ></div>
        <div
          v-else-if="user.role === 'admin'"
          class="bg-gradient-to-br from-indigo-600 to-indigo-800 p-5 rounded-xl shadow-lg text-white relative overflow-hidden transform transition hover:scale-[1.02]"
        >
          <CubeIcon
            class="absolute -bottom-4 -right-4 w-28 h-28 text-white opacity-10"
          />
          <p
            class="text-indigo-100 text-xs font-medium uppercase tracking-wider"
          >
            Inventory Asset
          </p>
          <h3 class="text-2xl font-extrabold mt-1">
            {{ formatCurrency(data.overall.inventory_value) }}
          </h3>
          <div
            class="mt-4 pt-3 border-t border-white/10 flex justify-between items-center text-xs"
          >
            <span class="flex items-center gap-1 opacity-80">Damaged:</span>
            <span class="font-bold">{{
              formatCurrency(data.overall.damaged_stock_value)
            }}</span>
          </div>
        </div>

        <div
          class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden flex flex-col h-[300px]"
        >
          <div
            class="p-4 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center"
          >
            <h3
              class="font-bold text-gray-800 dark:text-white flex items-center gap-2 text-sm"
            >
              <ExclamationTriangleIcon class="w-4 h-4 text-rose-500" /> Critical
              Stock
            </h3>
          </div>

          <div v-if="loading" class="p-4 space-y-4">
            <div
              v-for="i in 3"
              :key="i"
              class="flex items-center gap-3 animate-pulse"
            >
              <div
                class="w-8 h-8 bg-gray-200 dark:bg-slate-800 rounded-lg"
              ></div>
              <div class="flex-1 space-y-2">
                <div
                  class="h-3 w-3/4 bg-gray-200 dark:bg-slate-800 rounded"
                ></div>
                <div
                  class="h-2 w-1/2 bg-gray-200 dark:bg-slate-800 rounded"
                ></div>
              </div>
            </div>
          </div>

          <div v-else class="flex-1 overflow-y-auto custom-scrollbar">
            <div
              v-for="p in data.low_stock_list"
              :key="p.id"
              class="flex items-center gap-3 p-3 border-b border-gray-50 dark:border-slate-800 last:border-0 hover:bg-rose-50/20 transition cursor-pointer"
              @click="navigateTo(`/inventory?highlight=${p.id}`)"
            >
              <img
                :src="getImageUrl(p.image)"
                class="w-8 h-8 rounded-lg border dark:border-slate-700 object-cover"
              />
              <div class="flex-1 min-w-0">
                <h4
                  class="text-xs font-bold text-gray-800 dark:text-gray-200 truncate"
                >
                  {{ p.name }}
                </h4>
                <span class="text-[10px] text-gray-400"
                  >Limit: {{ p.alert_quantity }}</span
                >
              </div>
              <div class="text-right">
                <span class="text-sm font-bold text-rose-600">{{
                  p.stock_quantity
                }}</span>
                <span class="text-[9px] text-gray-400 block">LEFT</span>
              </div>
            </div>
            <div
              v-if="data.low_stock_list.length === 0"
              class="p-6 text-center text-xs text-gray-400 flex flex-col items-center gap-2 h-full justify-center"
            >
              <SparklesIcon class="w-8 h-8 text-gray-300" />
              All stock levels are healthy.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div
        class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden flex flex-col h-[400px]"
      >
        <div
          class="p-4 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center"
        >
          <h3
            class="font-bold text-gray-800 dark:text-white flex items-center gap-2 text-sm"
          >
            <ClockIcon class="w-4 h-4 text-indigo-500" /> Recent Invoices
          </h3>
          <button
            @click="navigateTo('/sales')"
            class="text-[10px] font-bold text-indigo-600 hover:underline uppercase"
          >
            View All
          </button>
        </div>

        <div v-if="loading" class="p-4 space-y-4">
          <div
            v-for="i in 5"
            :key="i"
            class="h-8 w-full bg-gray-100 dark:bg-slate-800/50 rounded animate-pulse"
          ></div>
        </div>

        <div v-else class="flex-1 overflow-x-auto custom-scrollbar">
          <table class="w-full text-left text-xs">
            <thead
              class="bg-gray-50 dark:bg-slate-800 text-gray-500 uppercase text-[10px] sticky top-0 z-10"
            >
              <tr>
                <th class="px-4 py-2">Invoice</th>
                <th class="px-4 py-2">Customer</th>
                <th class="px-4 py-2 text-right">Amount</th>
                <th class="px-4 py-2 text-right">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
              <tr
                v-for="sale in data.recent_sales"
                :key="sale.id"
                class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition cursor-pointer"
                @click="navigateTo('/sales')"
              >
                <td
                  class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200"
                >
                  {{ sale.invoice_no }}
                  <span class="block text-[9px] text-gray-400 font-normal">{{
                    sale.time
                  }}</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                  {{ sale.customer }}
                </td>
                <td
                  class="px-4 py-3 text-right font-bold text-gray-800 dark:text-white"
                >
                  {{ formatCurrency(sale.amount) }}
                </td>
                <td class="px-4 py-3 text-right">
                  <span
                    class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase"
                    :class="
                      sale.status === 'paid'
                        ? 'bg-emerald-100 text-emerald-600'
                        : 'bg-orange-100 text-orange-600'
                    "
                  >
                    {{ sale.status }}
                  </span>
                </td>
              </tr>
              <tr v-if="!data.recent_sales || data.recent_sales.length === 0">
                <td colspan="4" class="p-6 text-center text-xs text-gray-400">
                  No recent transactions.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden flex flex-col h-[400px]"
      >
        <div class="p-4 border-b border-gray-100 dark:border-slate-800">
          <h3
            class="font-bold text-gray-800 dark:text-white flex items-center gap-2 text-sm"
          >
            <ShoppingBagIcon class="w-4 h-4 text-purple-500" /> Top Selling
            Items
          </h3>
        </div>

        <div v-if="loading" class="p-4 space-y-4">
          <div
            v-for="i in 5"
            :key="i"
            class="h-8 w-full bg-gray-100 dark:bg-slate-800/50 rounded animate-pulse"
          ></div>
        </div>

        <div v-else class="flex-1 overflow-x-auto custom-scrollbar">
          <table class="w-full text-left text-xs">
            <thead
              class="bg-gray-50 dark:bg-slate-800 text-gray-500 uppercase text-[10px] sticky top-0 z-10"
            >
              <tr>
                <th class="px-4 py-2">Product</th>
                <th class="px-4 py-2 text-right">Sold</th>
                <th class="px-4 py-2 text-right">Stock</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
              <tr
                v-for="p in data.top_products"
                :key="p.id"
                class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
              >
                <td class="px-4 py-3 flex items-center gap-2">
                  <img
                    :src="getImageUrl(p.image)"
                    class="w-8 h-8 rounded border dark:border-slate-700 object-cover"
                  />
                  <span
                    class="font-bold text-gray-700 dark:text-gray-200 text-xs"
                    >{{ p.name }}</span
                  >
                </td>
                <td class="px-4 py-3 text-right font-bold text-indigo-600">
                  {{ p.total_sold }}
                </td>
                <td class="px-4 py-3 text-right text-gray-500">
                  {{ p.stock_quantity }}
                </td>
              </tr>
              <tr v-if="data.top_products.length === 0">
                <td colspan="3" class="p-8 text-center text-gray-400">
                  No sales record found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
  height: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #e2e8f0;
  border-radius: 20px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #334155;
}
</style>
