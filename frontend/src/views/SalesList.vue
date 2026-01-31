<script setup>
import { ref, onMounted, watch } from "vue";
import axios from "../axios";
import InvoiceModal from "../components/InvoiceModal.vue";
import SalesReturnModal from "../components/SalesReturnModal.vue";
import Swal from "sweetalert2";

import {
  MagnifyingGlassIcon,
  FunnelIcon,
  PrinterIcon,
  EyeIcon,
  TrashIcon,
  PlusIcon,
  ArrowPathIcon,
  DocumentArrowDownIcon,
  ArrowUturnLeftIcon,
  ExclamationTriangleIcon,
  BanknotesIcon,
  CalendarDaysIcon,
  UserCircleIcon,
  ClipboardDocumentListIcon,
} from "@heroicons/vue/24/outline";

// --- State ---
const activeTab = ref("sales"); // 'sales' or 'returns'
const sales = ref([]);
const returns = ref([]); // Store return history
const pagination = ref({});
const isLoading = ref(false);
const staffList = ref([]);
const user = ref(JSON.parse(localStorage.getItem("user") || "{}"));

// Modals
const showInvoiceModal = ref(false);
const showReturnModal = ref(false);
const selectedSale = ref(null);
const isInvoiceLoading = ref(false);

// Filters
const filters = ref({
  search: "",
  start_date: "",
  end_date: "",
  status: "",
  staff_id: "",
});

// Toast
const Toast = Swal.mixin({
  toast: true,
  position: "top-end",
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.onmouseenter = Swal.stopTimer;
    toast.onmouseleave = Swal.resumeTimer;
  },
});

// --- Helpers ---

const getPaymentStatusStyle = (status) => {
  if (!status) return "bg-gray-100 text-gray-600 border-gray-200";
  const s = status.toLowerCase();
  if (s === "paid")
    return "bg-emerald-50 text-emerald-700 border-emerald-200 ring-1 ring-emerald-600/10";
  if (s === "partial")
    return "bg-amber-50 text-amber-700 border-amber-200 ring-1 ring-amber-600/10";
  return "bg-rose-50 text-rose-700 border-rose-200 ring-1 ring-rose-600/10";
};

const getReturnStatus = (sale) => {
  if (!sale.sales_returns || sale.sales_returns.length === 0) return null;

  let hasGood = false;
  let hasBad = false;

  sale.sales_returns.forEach((ret) => {
    if (ret.return_items) {
      ret.return_items.forEach((item) => {
        if (item.return_condition === "good") hasGood = true;
        if (item.return_condition === "bad") hasBad = true;
      });
    }
  });

  if (hasGood && hasBad) {
    return {
      label: "Mixed Return",
      class:
        "bg-orange-50 text-orange-700 border-orange-200 ring-1 ring-orange-600/10",
      icon: ExclamationTriangleIcon,
    };
  }
  if (hasBad) {
    return {
      label: "Returned (Damaged)",
      class: "bg-red-50 text-red-700 border-red-200 ring-1 ring-red-600/10",
      icon: ExclamationTriangleIcon,
    };
  }
  return {
    label: "Returned (Restocked)",
    class: "bg-blue-50 text-blue-700 border-blue-200 ring-1 ring-blue-600/10",
    icon: ArrowUturnLeftIcon,
  };
};

// --- API Calls ---

// 1. Fetch Staff List (Only for Admin)
const fetchStaffList = async () => {
  if (user.value.role === "admin") {
    try {
      const response = await axios.get("/users");
      if (response.data.status) {
        staffList.value = response.data.data;
      }
    } catch (error) {
      console.error("Error fetching staff:", error);
    }
  }
};

// 2. Fetch Sales Data
const fetchSales = async (page = 1) => {
  isLoading.value = true;
  try {
    const response = await axios.get(`/sales`, {
      params: { page: page, ...filters.value },
    });
    if (response.data.status) {
      sales.value = response.data.data.data;
      pagination.value = response.data.data;
    }
  } catch (error) {
    console.error(error);
    Toast.fire({ icon: "error", title: "Failed to load sales data" });
  } finally {
    isLoading.value = false;
  }
};

// 3. 🔥 Fetch Returns History
const fetchReturns = async (page = 1) => {
  isLoading.value = true;
  try {
    const response = await axios.get("/sales-returns", { params: { page } });
    if (response.data.status) {
      returns.value = response.data.data.data;
      pagination.value = response.data.data;
    }
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

// 4. Handle Tab Switching
const switchTab = (tab) => {
  activeTab.value = tab;
  if (tab === "sales") fetchSales(1);
  else fetchReturns(1);
};

const loadSaleDetails = async (id, type = "invoice") => {
  isInvoiceLoading.value = true;
  try {
    const response = await axios.get(`/sales/${id}`);
    if (response.data.status) {
      selectedSale.value = response.data.data;
      if (type === "invoice") showInvoiceModal.value = true;
      else if (type === "return") showReturnModal.value = true;
    }
  } catch (error) {
    Toast.fire({ icon: "error", title: "Unable to load details" });
  } finally {
    isInvoiceLoading.value = false;
  }
};

const deleteSale = async (id) => {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "This record will be permanently deleted.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#4f46e5",
    cancelButtonColor: "#ef4444",
    confirmButtonText: "Yes, delete it!",
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/sales/${id}`);
      if (response.data.status) {
        Toast.fire({ icon: "success", title: "Deleted successfully" });
        fetchSales(pagination.value.current_page || 1);
      }
    } catch (error) {
      Swal.fire("Error", "Cannot delete sale with existing returns.", "error");
    }
  }
};

const resetFilters = () => {
  filters.value = {
    search: "",
    start_date: "",
    end_date: "",
    status: "",
    staff_id: "",
  };
  fetchSales(1);
};

const exportToCSV = () => {
  // Export logic
};

// Watchers
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
    () => filters.value.staff_id,
    () => filters.value.start_date,
    () => filters.value.end_date,
  ],
  () => {
    if (activeTab.value === "sales") fetchSales(1);
  },
);

onMounted(() => {
  fetchSales();
  fetchStaffList();
});
</script>

<template>
  <div class="space-y-6 pb-10">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
    >
      <div>
        <h2
          class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight"
        >
          Transaction Management
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          Monitor sales invoices and track return audits.
        </p>
      </div>
      <div class="flex gap-3">
        <button
          @click="exportToCSV"
          class="btn-secondary hidden sm:flex items-center gap-2"
        >
          <DocumentArrowDownIcon class="w-5 h-5" /> <span>Export</span>
        </button>
        <router-link to="/pos" class="btn-primary flex items-center gap-2">
          <PlusIcon class="w-5 h-5" /> <span>New Sale</span>
        </router-link>
      </div>
    </div>

    <div class="border-b border-gray-200 dark:border-slate-700">
      <nav class="-mb-px flex gap-6" aria-label="Tabs">
        <button
          @click="switchTab('sales')"
          :class="
            activeTab === 'sales'
              ? 'border-indigo-500 text-indigo-600'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
          "
          class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm gap-2 transition"
        >
          <ClipboardDocumentListIcon
            class="w-5 h-5"
            :class="
              activeTab === 'sales'
                ? 'text-indigo-500'
                : 'text-gray-400 group-hover:text-gray-500'
            "
          />
          <span>All Invoices</span>
        </button>

        <button
          @click="switchTab('returns')"
          :class="
            activeTab === 'returns'
              ? 'border-rose-500 text-rose-600'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
          "
          class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm gap-2 transition"
        >
          <ArrowUturnLeftIcon
            class="w-5 h-5"
            :class="
              activeTab === 'returns'
                ? 'text-rose-500'
                : 'text-gray-400 group-hover:text-gray-500'
            "
          />
          <span>Returns History (Audit)</span>
        </button>
      </nav>
    </div>

    <div v-if="activeTab === 'sales'">
      <div
        class="bg-white dark:bg-slate-900 p-4 rounded-lg border border-gray-200 dark:border-slate-800 shadow-sm mb-6"
      >
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1 relative">
            <div
              class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
            >
              <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
            </div>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Search by Invoice, Name, Phone..."
              class="input-field pl-10"
            />
          </div>

          <div class="flex gap-2">
            <div class="relative">
              <input
                v-model="filters.start_date"
                type="date"
                class="input-field pl-9 w-40"
              />
              <CalendarDaysIcon
                class="h-5 w-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none"
              />
            </div>
            <span class="self-center text-gray-400">-</span>
            <div class="relative">
              <input
                v-model="filters.end_date"
                type="date"
                class="input-field pl-9 w-40"
              />
              <CalendarDaysIcon
                class="h-5 w-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none"
              />
            </div>
          </div>

          <div v-if="user.role === 'admin'" class="w-full md:w-48 relative">
            <div
              class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
            >
              <UserCircleIcon class="h-5 w-5 text-gray-400" />
            </div>
            <select
              v-model="filters.staff_id"
              class="input-field pl-10 appearance-none cursor-pointer"
            >
              <option value="">All Staff</option>
              <option
                v-for="staff in staffList"
                :key="staff.id"
                :value="staff.id"
              >
                {{ staff.name }}
              </option>
            </select>
          </div>

          <div class="w-full md:w-48 relative">
            <FunnelIcon
              class="h-5 w-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none"
            />
            <select
              v-model="filters.status"
              class="input-field pl-10 appearance-none cursor-pointer"
            >
              <option value="">All Transactions</option>
              <optgroup label="Payment Status">
                <option value="paid">Paid</option>
                <option value="partial">Partial</option>
                <option value="due">Due</option>
              </optgroup>
              <optgroup label="Return Status">
                <option value="returned">All Returns</option>
                <option value="returned_good">Restocked (Good)</option>
                <option value="returned_bad">Damaged (Bad Stock)</option>
              </optgroup>
            </select>
          </div>

          <button
            @click="resetFilters"
            class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition border border-gray-200 dark:border-slate-700"
          >
            <ArrowPathIcon
              class="w-5 h-5"
              :class="{ 'animate-spin': isLoading }"
            />
          </button>
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 shadow-sm overflow-hidden"
      >
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr
                class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-800"
              >
                <th class="table-head">Invoice</th>
                <th class="table-head">Customer</th>
                <th class="table-head">Sold By</th>
                <th class="table-head text-right">Amount</th>
                <th class="table-head text-center">Current Status</th>
                <th class="table-head text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
              <tr v-if="isLoading">
                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                  <span class="inline-block animate-spin mr-2">⟳</span> Loading
                  records...
                </td>
              </tr>
              <tr
                v-else
                v-for="sale in sales"
                :key="sale.id"
                class="group hover:bg-gray-50 dark:hover:bg-slate-800/50 transition duration-150"
              >
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center gap-3">
                    <div
                      class="h-10 w-10 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center text-indigo-600 dark:text-indigo-400"
                    >
                      <BanknotesIcon class="w-6 h-6" />
                    </div>
                    <div>
                      <span
                        class="block text-sm font-bold text-gray-900 dark:text-white font-mono tracking-tight"
                      >
                        {{
                          sale.invoice_no ||
                          "#INV-" + String(sale.id).padStart(4, "0")
                        }}
                      </span>
                      <span class="text-xs text-gray-500">{{
                        new Date(sale.date).toLocaleDateString()
                      }}</span>
                    </div>
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  <div
                    class="text-sm font-medium text-gray-900 dark:text-white"
                  >
                    {{ sale.customer?.name || "Walk-in Customer" }}
                  </div>
                  <div class="text-xs text-gray-500 font-mono mt-0.5">
                    {{ sale.customer?.phone || "N/A" }}
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center gap-2">
                    <div
                      class="h-6 w-6 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 text-xs font-bold"
                    >
                      {{ sale.user?.name?.charAt(0) || "A" }}
                    </div>
                    <div>
                      <div
                        class="text-sm font-medium text-gray-800 dark:text-gray-200"
                      >
                        {{ sale.user?.name || "Admin" }}
                      </div>
                      <div
                        class="text-[10px] text-gray-500 uppercase tracking-wide"
                      >
                        {{ sale.user?.role || "System" }}
                      </div>
                    </div>
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="flex flex-col items-end">
                    <span
                      class="text-sm font-bold text-gray-900 dark:text-white"
                      >৳ {{ Number(sale.grand_total).toLocaleString() }}</span
                    >
                    <span
                      v-if="sale.sales_returns_sum_refund_amount > 0"
                      class="text-[11px] text-rose-600 font-medium mt-0.5 flex items-center gap-1 bg-rose-50 px-1.5 py-0.5 rounded"
                    >
                      <ArrowUturnLeftIcon class="w-3 h-3" /> Refunded: ৳
                      {{
                        Number(
                          sale.sales_returns_sum_refund_amount,
                        ).toLocaleString()
                      }}
                    </span>
                    <span
                      v-else-if="sale.due_amount > 0"
                      class="text-[11px] text-amber-600 font-medium mt-0.5 bg-amber-50 px-1.5 py-0.5 rounded"
                    >
                      Due: ৳ {{ Number(sale.due_amount).toLocaleString() }}
                    </span>
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <span
                    v-if="getReturnStatus(sale)"
                    :class="`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border shadow-sm ${getReturnStatus(sale).class}`"
                  >
                    <component
                      :is="getReturnStatus(sale).icon"
                      class="w-3.5 h-3.5"
                    />
                    {{ getReturnStatus(sale).label }}
                  </span>
                  <span
                    v-else
                    :class="`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border shadow-sm ${getPaymentStatusStyle(sale.payment_status)}`"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                    {{ sale.payment_status }}
                  </span>
                </td>

                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <div
                    class="flex justify-center items-center gap-1 opacity-60 group-hover:opacity-100 transition duration-200"
                  >
                    <button
                      @click="loadSaleDetails(sale.id, 'invoice')"
                      class="action-btn text-gray-500 hover:text-indigo-600 hover:bg-indigo-50"
                      title="View"
                    >
                      <EyeIcon class="w-5 h-5" />
                    </button>
                    <button
                      @click="loadSaleDetails(sale.id, 'invoice')"
                      class="action-btn text-gray-500 hover:text-blue-600 hover:bg-blue-50"
                      title="Print"
                    >
                      <PrinterIcon class="w-5 h-5" />
                    </button>
                    <button
                      @click="loadSaleDetails(sale.id, 'return')"
                      class="action-btn text-gray-500 hover:text-orange-600 hover:bg-orange-50"
                      title="Return"
                    >
                      <ArrowUturnLeftIcon class="w-5 h-5" />
                    </button>
                    <button
                      v-if="user.role === 'admin'"
                      @click="deleteSale(sale.id)"
                      class="action-btn text-gray-500 hover:text-red-600 hover:bg-red-50"
                      title="Delete"
                    >
                      <TrashIcon class="w-5 h-5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="pagination.total > 0"
          class="px-6 py-4 border-t border-gray-200 dark:border-slate-800 flex justify-between items-center bg-gray-50 dark:bg-slate-800/50"
        >
          <span class="text-sm text-gray-500"
            >Showing <b>{{ pagination.from }}</b
            >-<b>{{ pagination.to }}</b> of <b>{{ pagination.total }}</b></span
          >
          <div class="flex gap-2">
            <button
              @click="fetchSales(pagination.current_page - 1)"
              :disabled="!pagination.prev_page_url"
              class="pagination-btn"
            >
              Prev
            </button>
            <button
              @click="fetchSales(pagination.current_page + 1)"
              :disabled="!pagination.next_page_url"
              class="pagination-btn"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <div
      v-else
      class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 shadow-sm overflow-hidden"
    >
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr
              class="bg-rose-50 dark:bg-rose-900/20 border-b border-rose-100 dark:border-rose-800/50"
            >
              <th class="px-6 py-3 text-xs font-bold text-rose-600 uppercase">
                Return Info
              </th>
              <th class="px-6 py-3 text-xs font-bold text-rose-600 uppercase">
                Original Invoice
              </th>
              <th class="px-6 py-3 text-xs font-bold text-rose-600 uppercase">
                Processed By
              </th>
              <th
                class="px-6 py-3 text-xs font-bold text-rose-600 uppercase text-right"
              >
                Refund Amount
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
            <tr
              v-for="ret in returns"
              :key="ret.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >
              <td class="px-6 py-4">
                <span
                  class="block font-bold text-gray-800 dark:text-gray-200"
                  >{{ ret.return_no }}</span
                >
                <span class="text-xs text-gray-500">{{
                  new Date(ret.created_at).toLocaleString()
                }}</span>
              </td>
              <td class="px-6 py-4">
                <span
                  class="font-mono text-indigo-600 bg-indigo-50 px-2 py-1 rounded text-xs font-bold"
                  >{{ ret.sale?.invoice_no }}</span
                >
                <div class="text-xs text-gray-500 mt-1">
                  Customer: {{ ret.customer?.name || "Walk-in" }}
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <UserCircleIcon class="w-5 h-5 text-gray-400" />
                  <div>
                    <p
                      class="text-sm font-bold text-gray-700 dark:text-gray-200"
                    >
                      {{ ret.user?.name || "System/Admin" }}
                    </p>
                    <p class="text-[10px] text-gray-500 uppercase">
                      Staff ID: {{ ret.user?.id }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 text-right font-bold text-rose-600">
                ৳ {{ Number(ret.refund_amount).toLocaleString() }}
              </td>
            </tr>
            <tr v-if="returns.length === 0">
              <td colspan="4" class="p-8 text-center text-gray-400">
                No return records found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="pagination.total > 0"
        class="px-6 py-4 border-t flex justify-between items-center bg-gray-50 dark:bg-slate-800/50"
      >
        <span class="text-sm text-gray-500"
          >Showing page {{ pagination.current_page }} of
          {{ pagination.last_page }}</span
        >
        <div class="flex gap-2">
          <button
            @click="fetchReturns(pagination.current_page - 1)"
            :disabled="!pagination.prev_page_url"
            class="pagination-btn"
          >
            Prev
          </button>
          <button
            @click="fetchReturns(pagination.current_page + 1)"
            :disabled="!pagination.next_page_url"
            class="pagination-btn"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <InvoiceModal
      :isOpen="showInvoiceModal"
      :sale="selectedSale"
      @close="showInvoiceModal = false"
    />
    <SalesReturnModal
      :isOpen="showReturnModal"
      :sale="selectedSale"
      @close="showReturnModal = false"
      @success="fetchSales"
    />
  </div>
</template>

<style scoped>
/* Utility Classes for Cleaner Template */
.input-field {
  @apply w-full py-2.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition text-gray-700 dark:text-gray-200;
}
.btn-primary {
  @apply px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-sm hover:shadow transition text-sm font-medium;
}
.btn-secondary {
  @apply px-4 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition shadow-sm text-sm font-medium;
}
.table-head {
  @apply px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-slate-800/50;
}
.action-btn {
  @apply p-2 rounded-lg transition duration-200;
}
.pagination-btn {
  @apply px-3 py-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded text-sm hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed text-gray-600 dark:text-gray-300 transition;
}
</style>
