<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import { useRouter } from "vue-router";
import {
  PlusIcon,
  EyeIcon,
  TrashIcon,
  MagnifyingGlassIcon,
  XMarkIcon,
  CalendarDaysIcon,
  BanknotesIcon,
  FunnelIcon,
  ArrowPathIcon,
  ShoppingBagIcon,
  ClipboardDocumentCheckIcon,
} from "@heroicons/vue/24/outline";

const router = useRouter();

// --- State ---
const purchases = ref([]);
const isLoading = ref(false);

// Filters
const searchQuery = ref("");
const startDate = ref("");
const endDate = ref("");
const statusFilter = ref(""); // 'completed', 'pending'

// Modal State
const showModal = ref(false);
const selectedPurchase = ref(null);

// --- API Actions ---

const fetchPurchases = async () => {
  isLoading.value = true;
  try {
    const response = await axios.get("/purchases");
    if (response.data.status) {
      purchases.value = response.data.data;
    }
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const deletePurchase = async (id) => {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "This will delete the invoice and REVERSE the stock quantity!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, delete it!",
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/purchases/${id}`);
      Swal.fire("Deleted!", "Purchase deleted & stock reversed.", "success");
      fetchPurchases();
    } catch (error) {
      let msg = "Failed to delete.";
      if (error.response && error.response.data.message)
        msg = error.response.data.message;
      Swal.fire("Error", msg, "error");
    }
  }
};

// --- Helper Functions ---
const resetFilters = () => {
  searchQuery.value = "";
  startDate.value = "";
  endDate.value = "";
  statusFilter.value = "";
};

const openDetailsModal = (purchase) => {
  selectedPurchase.value = purchase;
  showModal.value = true;
};

// --- Computed Logic ---

// 1. Advanced Filtering
const filteredPurchases = computed(() => {
  return purchases.value.filter((p) => {
    // A. Search Query (Fix for Null values)
    const q = searchQuery.value.toLowerCase().trim();
    const ref = (p.reference_no || p.invoice_no || "").toLowerCase();
    const sup = (p.supplier?.name || "").toLowerCase();
    const matchesSearch = ref.includes(q) || sup.includes(q);

    // B. Date Range Filter
    let matchesDate = true;
    if (startDate.value) matchesDate = matchesDate && p.date >= startDate.value;
    if (endDate.value) matchesDate = matchesDate && p.date <= endDate.value;

    // C. Status Filter
    let matchesStatus = true;
    if (statusFilter.value) matchesStatus = p.status === statusFilter.value;

    return matchesSearch && matchesDate && matchesStatus;
  });
});

// 2. Summary Statistics (Based on Filtered Data)
const stats = computed(() => {
  const data = filteredPurchases.value;
  const totalAmount = data.reduce(
    (sum, p) => sum + Number(p.grand_total || 0),
    0,
  );
  const totalCount = data.length;

  // Today's Purchase
  const today = new Date().toISOString().slice(0, 10);
  const todayAmount = data
    .filter((p) => p.date === today)
    .reduce((sum, p) => sum + Number(p.grand_total || 0), 0);

  return { totalAmount, totalCount, todayAmount };
});

onMounted(() => fetchPurchases());
</script>

<template>
  <div class="space-y-6 pb-10">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
    >
      <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
          Purchase History
        </h2>
        <p class="text-sm text-gray-500">
          Manage invoices, stock entries and expenses.
        </p>
      </div>
      <button
        @click="router.push('/purchases/create')"
        class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-md font-bold text-sm transition transform active:scale-95"
      >
        <PlusIcon class="w-5 h-5" /> New Purchase
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div
        class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm flex items-center gap-4"
      >
        <div
          class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-full text-indigo-600"
        >
          <BanknotesIcon class="w-8 h-8" />
        </div>
        <div>
          <p class="text-sm text-gray-500 font-medium">Total Spent</p>
          <h3 class="text-2xl font-bold text-gray-800 dark:text-white">
            ৳ {{ stats.totalAmount.toLocaleString() }}
          </h3>
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm flex items-center gap-4"
      >
        <div
          class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-full text-blue-600"
        >
          <ClipboardDocumentCheckIcon class="w-8 h-8" />
        </div>
        <div>
          <p class="text-sm text-gray-500 font-medium">Total Invoices</p>
          <h3 class="text-2xl font-bold text-gray-800 dark:text-white">
            {{ stats.totalCount }}
          </h3>
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm flex items-center gap-4"
      >
        <div
          class="p-3 bg-green-50 dark:bg-green-900/30 rounded-full text-green-600"
        >
          <ShoppingBagIcon class="w-8 h-8" />
        </div>
        <div>
          <p class="text-sm text-gray-500 font-medium">Purchased Today</p>
          <h3 class="text-2xl font-bold text-gray-800 dark:text-white">
            ৳ {{ stats.todayAmount.toLocaleString() }}
          </h3>
        </div>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm"
    >
      <div class="flex flex-col md:flex-row gap-4 items-end md:items-center">
        <div class="relative flex-1 w-full">
          <MagnifyingGlassIcon
            class="absolute left-3 top-2.5 h-5 w-5 text-gray-400"
          />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by Invoice No or Supplier..."
            class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"
          />
        </div>

        <div class="flex items-center gap-2 w-full md:w-auto">
          <div class="flex flex-col">
            <span class="text-[10px] text-gray-400 font-bold uppercase"
              >From</span
            >
            <input
              v-model="startDate"
              type="date"
              class="px-3 py-1.5 text-sm border rounded-lg bg-gray-50 dark:bg-slate-800 dark:border-slate-700 outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          <div class="flex flex-col">
            <span class="text-[10px] text-gray-400 font-bold uppercase"
              >To</span
            >
            <input
              v-model="endDate"
              type="date"
              class="px-3 py-1.5 text-sm border rounded-lg bg-gray-50 dark:bg-slate-800 dark:border-slate-700 outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>

        <button
          @click="resetFilters"
          class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition"
          title="Reset Filters"
        >
          <ArrowPathIcon class="w-6 h-6" />
        </button>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm overflow-hidden"
    >
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead
            class="bg-gray-50 dark:bg-slate-800/50 text-xs font-bold text-gray-500 uppercase"
          >
            <tr>
              <th class="px-6 py-4">Date</th>
              <th class="px-6 py-4">Reference No</th>
              <th class="px-6 py-4">Supplier</th>
              <th class="px-6 py-4 text-right">Grand Total</th>
              <th class="px-6 py-4 text-center">Status</th>
              <th class="px-6 py-4 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
            <tr v-if="isLoading">
              <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                <div class="flex justify-center items-center gap-2">
                  <div
                    class="animate-spin rounded-full h-5 w-5 border-b-2 border-indigo-600"
                  ></div>
                  Loading...
                </div>
              </td>
            </tr>
            <tr v-else-if="filteredPurchases.length === 0">
              <td colspan="6" class="px-6 py-10 text-center text-gray-400">
                No purchases found.
              </td>
            </tr>
            <tr
              v-else
              v-for="purchase in filteredPurchases"
              :key="purchase.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >
              <td
                class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap"
              >
                {{ purchase.date }}
              </td>
              <td class="px-6 py-3">
                <span
                  class="px-2 py-1 bg-gray-100 dark:bg-slate-700 rounded text-xs font-mono font-bold text-indigo-600"
                >
                  {{ purchase.reference_no || purchase.invoice_no || "N/A" }}
                </span>
              </td>
              <td
                class="px-6 py-3 text-sm font-bold text-gray-700 dark:text-gray-200"
              >
                {{ purchase.supplier?.name }}
                <div class="text-[10px] text-gray-400 font-normal">
                  {{ purchase.supplier?.company_name }}
                </div>
              </td>
              <td
                class="px-6 py-3 text-right font-bold text-gray-800 dark:text-white"
              >
                ৳ {{ Number(purchase.grand_total).toLocaleString() }}
              </td>
              <td class="px-6 py-3 text-center">
                <span
                  class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700 uppercase"
                >
                  {{ purchase.status }}
                </span>
              </td>
              <td class="px-6 py-3 text-center">
                <div class="flex justify-center gap-2">
                  <button
                    @click="openDetailsModal(purchase)"
                    class="p-1.5 text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition"
                    title="View Details"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deletePurchase(purchase.id)"
                    class="p-1.5 text-red-600 bg-red-50 rounded hover:bg-red-100 transition"
                    title="Delete"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div
      v-if="showModal && selectedPurchase"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
    >
      <div
        class="bg-white dark:bg-slate-900 w-full max-w-2xl rounded-2xl shadow-2xl animate-fade-in-up border border-gray-200 dark:border-slate-800 overflow-hidden flex flex-col max-h-[90vh]"
      >
        <div
          class="flex justify-between items-center p-5 border-b border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/50"
        >
          <div>
            <h3
              class="font-bold text-lg text-gray-800 dark:text-white flex items-center gap-2"
            >
              <BanknotesIcon class="w-5 h-5 text-indigo-600" />
              Purchase Invoice
            </h3>
            <p class="text-xs text-gray-500 font-mono mt-1">
              {{ selectedPurchase.reference_no }}
            </p>
          </div>
          <button
            @click="showModal = false"
            class="text-gray-400 hover:text-red-500 transition"
          >
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>

        <div class="p-6 space-y-6 overflow-y-auto">
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="space-y-1">
              <p class="text-xs text-gray-400 uppercase font-bold">
                Supplier Info
              </p>
              <p class="font-bold text-gray-800 dark:text-white text-base">
                {{ selectedPurchase.supplier?.name }}
              </p>
              <p class="text-gray-500">
                {{ selectedPurchase.supplier?.company_name }}
              </p>
              <p class="text-gray-500">
                {{ selectedPurchase.supplier?.phone }}
              </p>
            </div>
            <div class="text-right space-y-1">
              <p class="text-xs text-gray-400 uppercase font-bold">
                Purchase Info
              </p>
              <p class="font-bold text-gray-800 dark:text-white">
                {{ selectedPurchase.date }}
              </p>
              <p
                class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full inline-block font-bold"
              >
                {{ selectedPurchase.status }}
              </p>
            </div>
          </div>

          <div
            class="border rounded-lg overflow-hidden border-gray-200 dark:border-slate-700"
          >
            <table class="w-full text-left text-sm">
              <thead
                class="bg-gray-50 dark:bg-slate-800 font-bold text-gray-600"
              >
                <tr>
                  <th class="p-3">Product Name</th>
                  <th class="p-3 text-right">Unit Cost</th>
                  <th class="p-3 text-center">Qty</th>
                  <th class="p-3 text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                <tr v-for="item in selectedPurchase.items" :key="item.id">
                  <td class="p-3 font-medium">{{ item.product?.name }}</td>
                  <td class="p-3 text-right">
                    ৳ {{ Number(item.unit_cost).toLocaleString() }}
                  </td>
                  <td class="p-3 text-center">{{ item.quantity }}</td>
                  <td class="p-3 text-right font-bold">
                    ৳ {{ Number(item.subtotal).toLocaleString() }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex justify-end">
            <div class="w-full md:w-1/2 space-y-2 text-right text-sm">
              <div class="flex justify-between text-gray-500">
                <span>Subtotal</span>
                <span
                  >৳
                  {{ Number(selectedPurchase.subtotal).toLocaleString() }}</span
                >
              </div>
              <div class="flex justify-between text-gray-500">
                <span>Tax</span>
                <span
                  >+ ৳ {{ Number(selectedPurchase.tax).toLocaleString() }}</span
                >
              </div>
              <div class="flex justify-between text-gray-500">
                <span>Discount</span>
                <span
                  >- ৳
                  {{ Number(selectedPurchase.discount).toLocaleString() }}</span
                >
              </div>
              <div
                class="flex justify-between text-lg font-bold text-indigo-600 border-t pt-2 dark:border-slate-700"
              >
                <span>Grand Total</span>
                <span
                  >৳
                  {{
                    Number(selectedPurchase.grand_total).toLocaleString()
                  }}</span
                >
              </div>
            </div>
          </div>
        </div>

        <div class="p-4 bg-gray-50 dark:bg-slate-800/50 text-right">
          <button
            @click="showModal = false"
            class="px-5 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold hover:bg-gray-50 transition"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
