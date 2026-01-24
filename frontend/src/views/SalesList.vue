<script setup>
import { ref, onMounted, watch } from "vue";
import axios from "../axios";
import InvoiceModal from "../components/InvoiceModal.vue"; // ইনভয়েস মডাল ইম্পোর্ট
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
  ArrowPathIcon,
  DocumentArrowDownIcon,
} from "@heroicons/vue/24/outline";

// --- State Management ---
const sales = ref([]);
const pagination = ref({});
const isLoading = ref(false);

// Modal State
const showInvoiceModal = ref(false);
const selectedSale = ref(null);
const isInvoiceLoading = ref(false);

// Filters State
const filters = ref({
  search: "",
  start_date: "",
  end_date: "",
  status: "",
});

// --- Helpers ---

// Status Color Logic
const getStatusColor = (status) => {
  if (!status) return "bg-gray-50 text-gray-700 border-gray-200";
  const s = status.toLowerCase();

  if (s === "paid")
    return "bg-emerald-50 text-emerald-700 border-emerald-200 ring-1 ring-emerald-600/20";
  if (s === "partial")
    return "bg-amber-50 text-amber-700 border-amber-200 ring-1 ring-amber-600/20";
  if (s === "due" || s === "unpaid")
    return "bg-red-50 text-red-700 border-red-200 ring-1 ring-red-600/20";

  return "bg-gray-50 text-gray-700 border-gray-200";
};

// --- API Actions ---

// Fetch Sales List
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
    console.error("Failed to load sales:", error);
  } finally {
    isLoading.value = false;
  }
};

// Open Invoice Modal
const openInvoice = async (id) => {
  isInvoiceLoading.value = true; // (Optional: You can show a global loader if needed)
  try {
    const response = await axios.get(`/sales/${id}`);
    if (response.data.status) {
      selectedSale.value = response.data.data;

      // Due calculation fallback (if backend doesn't send it)
      if (selectedSale.value.grand_total && selectedSale.value.paid_amount) {
        selectedSale.value.due_amount =
          selectedSale.value.grand_total - selectedSale.value.paid_amount;
      }

      showInvoiceModal.value = true;
    }
  } catch (error) {
    console.error("Error fetching invoice:", error);
    alert("Unable to load invoice details.");
  } finally {
    isInvoiceLoading.value = false;
  }
};

// Reset Filters
const resetFilters = () => {
  filters.value = { search: "", start_date: "", end_date: "", status: "" };
  fetchSales(1);
};

// --- Watchers ---

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

// Initial Load
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
          Manage invoices, track payments, and view reports.
        </p>
      </div>
      <div class="flex gap-3">
        <button
          class="hidden sm:flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition shadow-sm text-sm font-medium"
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
      <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
        <div class="md:col-span-4">
          <label class="block text-xs font-semibold text-gray-500 mb-1"
            >SEARCH</label
          >
          <div class="relative">
            <MagnifyingGlassIcon
              class="absolute left-3 top-2.5 h-5 w-5 text-gray-400"
            />
            <input
              v-model="filters.search"
              type="text"
              placeholder="Invoice ID, Name or Phone..."
              class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
            />
          </div>
        </div>

        <div class="md:col-span-4 grid grid-cols-2 gap-2">
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1"
              >FROM</label
            >
            <input
              v-model="filters.start_date"
              type="date"
              class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none text-gray-600 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1"
              >TO</label
            >
            <input
              v-model="filters.end_date"
              type="date"
              class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none text-gray-600 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>

        <div class="md:col-span-3">
          <label class="block text-xs font-semibold text-gray-500 mb-1"
            >STATUS</label
          >
          <div class="relative">
            <FunnelIcon class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
            <select
              v-model="filters.status"
              class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none text-gray-600 dark:text-gray-300 focus:ring-2 focus:ring-indigo-500 cursor-pointer appearance-none"
            >
              <option value="">All Status</option>
              <option value="paid">Paid</option>
              <option value="partial">Partial</option>
              <option value="due">Due</option>
            </select>
          </div>
        </div>

        <div class="md:col-span-1">
          <button
            @click="resetFilters"
            class="w-full py-2 bg-gray-100 hover:bg-gray-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-gray-600 dark:text-gray-300 rounded-lg transition flex items-center justify-center"
            title="Reset Filters"
          >
            <ArrowPathIcon
              class="w-5 h-5"
              :class="{ 'animate-spin': isLoading }"
            />
          </button>
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
                Invoice
              </th>
              <th
                class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider"
              >
                Date
              </th>
              <th
                class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider"
              >
                Customer
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
                    class="animate-spin rounded-full h-5 w-5 border-b-2 border-indigo-600"
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
                  #{{ String(sale.id).padStart(4, "0") }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                  {{ new Date(sale.created_at).toLocaleDateString() }}
                </div>
                <div class="text-xs text-gray-400">
                  {{
                    new Date(sale.created_at).toLocaleTimeString([], {
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
                    {{ sale.customer?.name?.charAt(0).toUpperCase() || "W" }}
                  </div>
                  <div>
                    <div
                      class="text-sm font-medium text-gray-800 dark:text-white"
                    >
                      {{ sale.customer?.name || "Walk-in Customer" }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ sale.customer?.phone || "" }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <div class="text-sm font-bold text-gray-900 dark:text-white">
                  ৳ {{ Number(sale.grand_total).toLocaleString() }}
                </div>
                <div
                  v-if="sale.due_amount > 0"
                  class="text-[10px] text-red-500 font-bold bg-red-50 dark:bg-red-900/20 px-1.5 py-0.5 rounded inline-block mt-1"
                >
                  Due: {{ Number(sale.due_amount).toLocaleString() }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <span
                  :class="`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${getStatusColor(sale.payment_status)}`"
                >
                  {{ sale.payment_status }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <div class="flex items-center justify-center gap-2">
                  <button
                    @click="openInvoice(sale.id)"
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
                    class="h-14 w-14 bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-3"
                  >
                    <MagnifyingGlassIcon class="h-6 w-6 text-gray-400" />
                  </div>
                  <h3
                    class="text-base font-medium text-gray-900 dark:text-white"
                  >
                    No invoices found
                  </h3>
                  <p class="text-gray-500 text-sm mt-1">
                    Try adjusting your search or filters.
                  </p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="pagination.total > 0"
        class="px-6 py-4 border-t border-gray-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 bg-gray-50 dark:bg-slate-800/50"
      >
        <span class="text-sm text-gray-500 dark:text-gray-400">
          Showing <b>{{ pagination.from }}</b> to <b>{{ pagination.to }}</b> of
          <b>{{ pagination.total }}</b> results
        </span>
        <div class="flex gap-2">
          <button
            @click="fetchSales(pagination.current_page - 1)"
            :disabled="!pagination.prev_page_url"
            class="flex items-center gap-1 px-3 py-1.5 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm"
          >
            <ChevronLeftIcon class="w-4 h-4" /> Previous
          </button>
          <button
            @click="fetchSales(pagination.current_page + 1)"
            :disabled="!pagination.next_page_url"
            class="flex items-center gap-1 px-3 py-1.5 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm"
          >
            Next <ChevronRightIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <InvoiceModal
      :isOpen="showInvoiceModal"
      :sale="selectedSale"
      @close="showInvoiceModal = false"
    />
  </div>
</template>
