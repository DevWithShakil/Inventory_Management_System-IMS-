<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "../axios";
import {
  BanknotesIcon,
  ShoppingBagIcon,
  UsersIcon,
  ArrowTrendingUpIcon,
  ExclamationTriangleIcon,
  CubeIcon,
  ArrowPathIcon,
  CalendarDaysIcon,
} from "@heroicons/vue/24/outline";

const user = JSON.parse(localStorage.getItem("user") || "{}");
const role = user.role;
const isAdmin = computed(() => role === "admin");

// State
const isLoading = ref(true);
const selectedRange = ref("7days"); // ডিফল্ট ফিল্টার

// Reactive Data Store
const dashboardData = ref({
  stats: { revenue: 0, orders: 0, customers: 0, growth: 0 },
  chart: { categories: [], series: [] },
  recent_orders: [],
  low_stock: [],
  top_products: [],
});

// API Call Function
const fetchDashboardData = async () => {
  isLoading.value = true;
  try {
    // রেঞ্জ প্যারামিটার সহ রিকোয়েস্ট পাঠানো হচ্ছে
    const response = await axios.get(
      `/dashboard/overview?range=${selectedRange.value}`,
    );

    if (response.data.status) {
      dashboardData.value = response.data.data;

      // চার্ট আপডেট করা
      chartOptions.value = {
        ...chartOptions.value,
        xaxis: {
          ...chartOptions.value.xaxis,
          categories: response.data.data.chart.categories,
        },
      };
      series.value = response.data.data.chart.series;
    }
  } catch (error) {
    console.error("Error fetching dashboard data:", error);
  } finally {
    isLoading.value = false;
  }
};

// পেজ লোড হলে ডাটা আনবে
onMounted(() => {
  fetchDashboardData();
});

// Stats Cards Configuration
const stats = computed(() => [
  {
    title: "Total Revenue",
    value: `৳ ${dashboardData.value.stats.revenue}`,
    change: `${dashboardData.value.stats.growth}%`,
    trend: dashboardData.value.stats.growth >= 0 ? "up" : "down",
    icon: BanknotesIcon,
    iconColor: "text-emerald-600",
    iconBg: "bg-emerald-50 dark:bg-emerald-500/10",
  },
  {
    title: "Total Orders",
    value: dashboardData.value.stats.orders,
    change: "Lifetime",
    trend: "up",
    icon: ShoppingBagIcon,
    iconColor: "text-blue-600",
    iconBg: "bg-blue-50 dark:bg-blue-500/10",
  },
  {
    title: "Total Customers",
    value: dashboardData.value.stats.customers,
    change: "Active",
    trend: "up",
    icon: UsersIcon,
    iconColor: "text-violet-600",
    iconBg: "bg-violet-50 dark:bg-violet-500/10",
  },
  {
    title: "Growth Rate",
    value: `${dashboardData.value.stats.growth}%`,
    change: "vs Last Month",
    trend: dashboardData.value.stats.growth >= 0 ? "up" : "down",
    icon: ArrowTrendingUpIcon,
    iconColor: "text-orange-600",
    iconBg: "bg-orange-50 dark:bg-orange-500/10",
  },
]);

// Chart Configuration
const chartOptions = ref({
  chart: {
    type: "area",
    toolbar: { show: false },
    fontFamily: "inherit",
    background: "transparent",
  },
  colors: ["#4F46E5", "#10B981"],
  stroke: { curve: "smooth", width: 2 },
  dataLabels: { enabled: false },
  fill: { type: "gradient", gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
  xaxis: {
    categories: [],
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: { show: true, tickAmount: 4 },
  grid: { borderColor: "#e5e7eb", strokeDashArray: 4 },
  theme: { mode: localStorage.getItem("theme") || "light" },
  tooltip: { theme: "light" },
});

const series = ref([]);
</script>

<template>
  <div class="space-y-6 pb-8">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
    >
      <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
          Dashboard Overview
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Welcome back, {{ user.name }}! Here is your store summary.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <div class="relative">
          <div
            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
          >
            <CalendarDaysIcon class="h-4 w-4 text-gray-500" />
          </div>
          <select
            v-model="selectedRange"
            @change="fetchDashboardData"
            class="pl-9 pr-4 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 block w-full outline-none shadow-sm cursor-pointer"
          >
            <option value="today">Today</option>
            <option value="7days">Last 7 Days</option>
            <option value="30days">Last 30 Days</option>
            <option value="this_month">This Month</option>
          </select>
        </div>

        <button
          @click="fetchDashboardData"
          class="p-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition shadow-sm"
          title="Refresh Data"
        >
          <ArrowPathIcon
            class="w-5 h-5 text-gray-500 dark:text-gray-400"
            :class="{ 'animate-spin': isLoading }"
          />
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div
        v-for="stat in stats"
        :key="stat.title"
        class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm transition hover:shadow-md"
      >
        <div class="flex justify-between items-start">
          <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
              {{ stat.title }}
            </p>
            <div
              v-if="isLoading"
              class="h-8 w-24 bg-gray-200 dark:bg-gray-800 rounded animate-pulse mt-2"
            ></div>
            <h3
              v-else
              class="text-2xl font-bold text-gray-900 dark:text-white mt-2"
            >
              {{ stat.value }}
            </h3>
          </div>
          <div :class="`p-3 rounded-lg ${stat.iconBg}`">
            <component :is="stat.icon" :class="`w-6 h-6 ${stat.iconColor}`" />
          </div>
        </div>
        <div v-if="!isLoading" class="mt-4 flex items-center text-sm">
          <span
            :class="
              stat.trend === 'up'
                ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10'
                : 'text-red-600 bg-red-50 dark:bg-red-500/10'
            "
            class="px-2 py-0.5 rounded text-xs font-bold mr-2"
          >
            {{ stat.change }}
          </span>
          <span class="text-gray-400 text-xs">Analytics</span>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <div
          class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm p-6"
        >
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white">
              Revenue Analytics
            </h3>
            <span
              class="text-xs font-medium px-2 py-1 bg-gray-100 dark:bg-slate-800 rounded text-gray-500 capitalize"
              >{{ selectedRange.replace("_", " ") }}</span
            >
          </div>
          <div
            v-if="isLoading"
            class="h-[300px] bg-gray-100 dark:bg-gray-800 animate-pulse rounded"
          ></div>
          <apexchart
            v-else
            type="area"
            height="300"
            :options="chartOptions"
            :series="series"
          ></apexchart>
        </div>

        <div
          class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm p-6"
        >
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white">
              Recent Orders
            </h3>
            <router-link
              to="/sales"
              class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
              >View All</router-link
            >
          </div>

          <div v-if="isLoading" class="space-y-4">
            <div
              v-for="i in 5"
              :key="i"
              class="h-12 bg-gray-100 dark:bg-gray-800 animate-pulse rounded"
            ></div>
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="order in dashboardData.recent_orders"
              :key="order.id"
              class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-800/50 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition border border-transparent hover:border-gray-200 dark:hover:border-slate-700"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold text-xs"
                >
                  {{ order.customer.charAt(0) }}
                </div>
                <div>
                  <p
                    class="text-sm font-semibold text-gray-800 dark:text-white"
                  >
                    {{ order.customer }}
                  </p>
                  <p class="text-xs text-gray-500">
                    {{ order.id }} • {{ order.date }}
                  </p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-sm font-bold text-gray-800 dark:text-white">
                  {{ order.amount }}
                </p>
                <span
                  class="text-[10px] px-2 py-0.5 rounded-full font-bold text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20"
                >
                  {{ order.status }}
                </span>
              </div>
            </div>
            <p
              v-if="dashboardData.recent_orders.length === 0"
              class="text-center text-gray-500 text-sm py-4"
            >
              No recent orders found.
            </p>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div
          class="bg-white dark:bg-slate-900 rounded-xl border border-red-100 dark:border-red-900/30 shadow-sm p-6 relative overflow-hidden"
        >
          <div class="absolute top-0 right-0 p-4 opacity-10">
            <ExclamationTriangleIcon class="w-24 h-24 text-red-500" />
          </div>
          <h3
            class="text-lg font-bold text-red-600 dark:text-red-400 flex items-center gap-2 mb-4 relative z-10"
          >
            <ExclamationTriangleIcon class="w-5 h-5" /> Low Stock Alerts
          </h3>

          <div v-if="isLoading" class="space-y-3">
            <div
              v-for="i in 3"
              :key="i"
              class="h-10 bg-gray-100 dark:bg-gray-800 animate-pulse rounded"
            ></div>
          </div>

          <div v-else class="space-y-3 relative z-10">
            <div
              v-for="product in dashboardData.low_stock"
              :key="product.id"
              class="flex items-center justify-between p-2.5 bg-red-50 dark:bg-red-900/10 rounded-lg border border-red-100 dark:border-red-900/20"
            >
              <div class="flex items-center gap-3 overflow-hidden">
                <div
                  class="w-10 h-10 rounded bg-white dark:bg-slate-800 flex-shrink-0 flex items-center justify-center border border-red-100 dark:border-red-900/30"
                >
                  <img
                    v-if="product.image"
                    :src="product.image"
                    class="w-full h-full object-cover rounded"
                  />
                  <CubeIcon v-else class="w-5 h-5 text-red-400" />
                </div>
                <div class="min-w-0">
                  <h4
                    class="text-sm font-bold text-gray-800 dark:text-white truncate"
                  >
                    {{ product.name }}
                  </h4>
                  <p class="text-xs text-red-500 font-medium">
                    Alert: {{ product.alert_quantity }}
                  </p>
                </div>
              </div>
              <span
                class="text-xs font-bold text-red-600 bg-white dark:bg-slate-900 px-2 py-1 rounded shadow-sm border border-red-100 dark:border-red-900/30 whitespace-nowrap"
              >
                Qty: {{ product.stock_quantity }}
              </span>
            </div>

            <div
              v-if="!dashboardData.low_stock?.length"
              class="text-center py-4 bg-green-50 dark:bg-green-900/10 rounded-lg border border-green-100 dark:border-green-900/20"
            >
              <p class="text-sm font-bold text-green-600 dark:text-green-400">
                All stocks are healthy! 🎉
              </p>
            </div>
          </div>
        </div>

        <div
          class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm p-6"
        >
          <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6">
            🏆 Top Selling Products
          </h3>

          <div v-if="isLoading" class="space-y-4">
            <div
              v-for="i in 4"
              :key="i"
              class="h-10 bg-gray-100 dark:bg-gray-800 animate-pulse rounded"
            ></div>
          </div>

          <div v-else class="space-y-5">
            <div
              v-for="(product, index) in dashboardData.top_products"
              :key="index"
              class="flex items-center justify-between group"
            >
              <div class="flex items-center gap-3 flex-1 min-w-0">
                <span class="text-sm font-bold text-gray-400 w-5"
                  >#{{ index + 1 }}</span
                >
                <div class="flex-1 min-w-0">
                  <p
                    class="text-sm font-semibold text-gray-800 dark:text-white truncate group-hover:text-indigo-600 transition"
                  >
                    {{ product.name }}
                  </p>
                  <div
                    class="w-full h-1.5 bg-gray-100 dark:bg-slate-800 rounded-full mt-1.5 overflow-hidden"
                  >
                    <div
                      class="h-full bg-indigo-500 rounded-full"
                      :style="`width: ${Math.min(product.total_sold, 100)}%`"
                    ></div>
                  </div>
                </div>
              </div>
              <span
                class="ml-2 text-xs font-bold text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 px-2 py-1 rounded whitespace-nowrap"
              >
                {{ product.total_sold }} sold
              </span>
            </div>
            <p
              v-if="!dashboardData.top_products?.length"
              class="text-center text-gray-500 text-sm py-2"
            >
              No sales data yet.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
