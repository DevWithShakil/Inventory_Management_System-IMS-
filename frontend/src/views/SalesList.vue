<script setup>
import { ref, onMounted, watch } from "vue";
import axios from "../axios";
import {
  MagnifyingGlassIcon,
  FunnelIcon,
  PrinterIcon,
  EyeIcon,
  TrashIcon,
  PlusIcon,
  CalendarDaysIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  DocumentArrowDownIcon,
} from "@heroicons/vue/24/outline";

// State
const sales = ref([]);
const pagination = ref({});
const isLoading = ref(false);

// Filters State
const filters = ref({
  search: "",
  start_date: "",
  end_date: "",
  status: "",
});

// Helper: Status Color Badge
const getStatusColor = (status) => {
  switch (status) {
    case "Paid":
      return "bg-emerald-50 text-emerald-700 border-emerald-200 ring-emerald-600/20";
    case "Partial":
      return "bg-amber-50 text-amber-700 border-amber-200 ring-amber-600/20";
    case "Due":
      return "bg-red-50 text-red-700 border-red-200 ring-red-600/20";
    default:
      return "bg-gray-50 text-gray-700 border-gray-200 ring-gray-600/20";
  }
};

// API Call
const fetchSales = async (page = 1) => {
  isLoading.value = true;
  try {
    const response = await axios.get(`/sales`, {
      params: {
        page: page,
        ...filters.value,
      },
    });

    if (response.data.status) {
      sales.value = response.data.data.data;
      pagination.value = response.data.data;
    }
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

// Watchers for Real-time filtering
let timeout = null;
watch(
  () => filters.value.search,
  () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => fetchSales(1), 500);
  },
);

watch(
  [
    () => filters.value.status,
    () => filters.value.start_date,
    () => filters.value.end_date,
  ],
  () => {
    fetchSales(1);
  },
);

onMounted(() => fetchSales());
</script>

<template>
  <div class="space-y-6 pb-10">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
    >
      <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
          Sales History
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Track and manage all customer invoices.
        </p>
      </div>
      <div class="flex gap-3">
        <button
          class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition shadow-sm text-sm font-medium"
        >
          <DocumentArrowDownIcon class="w-5 h-5" />
          <span>Export</span>
        </button>

        <router-link
          to="/pos"
          class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-md hover:shadow-lg transition transform active:scale-95 text-sm font-bold"
        >
          <PlusIcon class="w-5 h-5" />
          <span>Create New Sale</span>
        </router-link>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm"
    >
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div class="md:col-span-1">
          <label class="block text-xs font-semibold text-gray-500 mb-1"
            >SEARCH INVOICE</label
          >
          <div class="relative">
            <MagnifyingGlassIcon
              class="absolute left-3 top-2.5 h-5 w-5 text-gray-400"
            />
            <input
              v-model="filters.search"
              type="text"
              placeholder="Order ID, Name or Phone..."
              class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
            />
          </div>
        </div>

        <div class="md:col-span-2 grid grid-cols-2 gap-2">
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1"
              >FROM DATE</label
            >
            <div class="relative">
              <CalendarDaysIcon
                class="absolute left-3 top-2.5 h-5 w-5 text-gray-400"
              />
              <input
                v-model="filters.start_date"
                type="date"
                class="w-full pl-10 pr-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none text-gray-600 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 transition"
              />
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1"
              >TO DATE</label
            >
            <div class="relative">
              <CalendarDaysIcon
                class="absolute left-3 top-2.5 h-5 w-5 text-gray-400"
              />
              <input
                v-model="filters.end_date"
                type="date"
                class="w-full pl-10 pr-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none text-gray-600 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 transition"
              />
            </div>
          </div>
        </div>

        <div class="md:col-span-1">
          <label class="block text-xs font-semibold text-gray-500 mb-1"
            >PAYMENT STATUS</label
          >
          <div class="relative">
            <FunnelIcon class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
            <select
              v-model="filters.status"
              class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none text-gray-600 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 cursor-pointer appearance-none transition"
            >
              <option value="">All Status</option>
              <option value="paid">Paid</option>
              <option value="partial">Partial</option>
              <option value="due">Due</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm overflow-hidden"
    >
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr
              class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-800"
            >
              <th
                class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider"
              >
                Invoice No
              </th>
              <th
                class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider"
              >
                Date
              </th>
              <th
                class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider"
              >
                Customer Info
              </th>
              <th
                class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right"
              >
                Amount
              </th>
              <th
                class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center"
              >
                Status
              </th>
              <th
                class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center"
              >
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
            <tr v-if="isLoading">
              <td colspan="6" class="px-6 py-10">
                <div class="flex justify-center items-center gap-3">
                  <div
                    class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"
                  ></div>
                  <span class="text-sm text-gray-500">Loading data...</span>
                </div>
              </td>
            </tr>

            <tr
              v-else
              v-for="sale in sales"
              :key="sale.id"
              class="group hover:bg-gray-50 dark:hover:bg-slate-800/50 transition duration-150"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="text-sm font-bold text-indigo-600 dark:text-indigo-400 font-mono"
                >
                  #ORD-{{ String(sale.id).padStart(4, "0") }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                  {{ new Date(sale.date).toLocaleDateString() }}
                </div>
                <div class="text-xs text-gray-400">
                  {{
                    new Date(sale.date).toLocaleTimeString([], {
                      hour: "2-digit",
                      minute: "2-digit",
                    })
                  }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  <div
                    class="h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-xs font-bold text-indigo-700 dark:text-indigo-300"
                  >
                    {{ sale.customer?.name?.charAt(0) || "W" }}
                  </div>
                  <div>
                    <div
                      class="text-sm font-medium text-gray-800 dark:text-white"
                    >
                      {{ sale.customer?.name || "Walk-in Customer" }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ sale.customer?.phone || "N/A" }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <div class="text-sm font-bold text-gray-900 dark:text-white">
                  ৳ {{ sale.grand_total }}
                </div>
                <div
                  v-if="sale.due_amount > 0"
                  class="text-xs text-red-500 font-medium"
                >
                  Due: ৳ {{ sale.due_amount }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <span
                  :class="`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ring-1 ring-inset ${getStatusColor(
                    sale.payment_status,
                  )}`"
                >
                  {{ sale.payment_status }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <div
                  class="flex items-center justify-center gap-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity"
                >
                  <button
                    class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg transition"
                    title="View Invoice"
                  >
                    <EyeIcon class="w-5 h-5" />
                  </button>
                  <button
                    class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition"
                    title="Print"
                  >
                    <PrinterIcon class="w-5 h-5" />
                  </button>
                  <button
                    class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition"
                    title="Delete"
                  >
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="!isLoading && sales.length === 0">
              <td colspan="6" class="px-6 py-16 text-center">
                <div class="flex flex-col items-center justify-center">
                  <div
                    class="h-16 w-16 bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4"
                  >
                    <MagnifyingGlassIcon class="h-8 w-8 text-gray-400" />
                  </div>
                  <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    No orders found
                  </h3>
                  <p class="text-gray-500 text-sm mt-1 max-w-sm mx-auto">
                    We couldn't find any sales matching your filters. Try
                    adjusting the dates or search term.
                  </p>
                  <button
                    @click="
                      filters = {
                        search: '',
                        start_date: '',
                        end_date: '',
                        status: '',
                      }
                    "
                    class="mt-4 text-indigo-600 font-medium hover:underline"
                  >
                    Clear Filters
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        class="px-6 py-4 border-t border-gray-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 bg-gray-50 dark:bg-slate-800/50"
      >
        <span class="text-sm text-gray-500 dark:text-gray-400">
          Showing <span class="font-medium">{{ pagination.from || 0 }}</span> to
          <span class="font-medium">{{ pagination.to || 0 }}</span> of
          <span class="font-medium">{{ pagination.total || 0 }}</span> results
        </span>

        <div class="flex gap-2">
          <button
            @click="fetchSales(pagination.current_page - 1)"
            :disabled="!pagination.prev_page_url"
            class="flex items-center gap-1 px-3 py-1.5 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm"
          >
            <ChevronLeftIcon class="w-4 h-4" />
            Previous
          </button>
          <button
            @click="fetchSales(pagination.current_page + 1)"
            :disabled="!pagination.next_page_url"
            class="flex items-center gap-1 px-3 py-1.5 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm"
          >
            Next
            <ChevronRightIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
