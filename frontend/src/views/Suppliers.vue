<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import axios from "../axios";
import Swal from "sweetalert2";
import {
  MagnifyingGlassIcon,
  UserPlusIcon,
  PencilSquareIcon,
  TrashIcon,
  TruckIcon,
  PhoneIcon,
  BanknotesIcon,
  XMarkIcon,
  ClockIcon,
} from "@heroicons/vue/24/outline";

// Components
import SupplierFormModal from "../components/SupplierFormModal.vue";
import SupplierHistoryModal from "../components/SupplierHistoryModal.vue";
import TransactionReceiptModal from "../components/TransactionReceiptModal.vue";

const route = useRoute();
const router = useRouter();

// --- State ---
const suppliers = ref([]);
const isLoading = ref(false);
const searchQuery = ref("");
const showModal = ref(false);
const selectedSupplier = ref(null);

// History Modal State
const showHistoryModal = ref(false);
const historySupplier = ref(null);

// Receipt Modal State
const showReceiptModal = ref(false);
const selectedTrxId = ref(null);

// 🔥 ১. Local Date পাওয়ার ফাংশন (Timezone Fix)
const getLocalDate = () => {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, "0");
  const day = String(today.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

// Payment Modal State
const showPayModal = ref(false);
const submittingPay = ref(false);
const payForm = ref({
  supplier_id: null,
  supplier_name: "",
  current_balance: 0,
  amount: "",
  date: getLocalDate(), // 🔥 ফিক্স: এখন লোকাল ডেট আসবে
  payment_method: "cash",
  note: "",
});

watch(
  () => route.query.action,
  (newAction) => {
    if (newAction === "add") {
      openAddModal();
    }
  },
  { immediate: true },
);

// --- API Actions ---

// 1. Fetch Suppliers
const fetchSuppliers = async () => {
  isLoading.value = true;
  try {
    const response = await axios.get("/suppliers");
    if (response.data.status) {
      suppliers.value = response.data.data.data || response.data.data;
    }
  } catch (error) {
    console.error("Error fetching suppliers:", error);
  } finally {
    isLoading.value = false;
  }
};

// 2. Delete Supplier
const deleteSupplier = async (id) => {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "You won't be able to revert this!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, delete it!",
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/suppliers/${id}`);
      Swal.fire({
        icon: "success",
        title: "Deleted!",
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 1500,
      });
      fetchSuppliers();
    } catch (error) {
      Swal.fire("Error", "Failed to delete supplier.", "error");
    }
  }
};

// 3. Payment Functions

const openPayModal = (supplier) => {
  payForm.value = {
    supplier_id: supplier.id,
    supplier_name: supplier.name,
    current_balance: supplier.balance,
    amount: "",
    date: getLocalDate(), // 🔥 ফিক্স: ওপেন করার সময়ও সঠিক তারিখ সেট হবে
    payment_method: "cash",
    note: "",
  };
  showPayModal.value = true;
};

const submitPayment = async () => {
  if (!payForm.value.amount || payForm.value.amount <= 0) {
    Swal.fire("Error", "Please enter a valid amount", "error");
    return;
  }

  // Frontend Validation
  if (Number(payForm.value.amount) > Number(payForm.value.current_balance)) {
    Swal.fire(
      "Warning",
      `Amount cannot exceed due (৳ ${payForm.value.current_balance})`,
      "warning",
    );
    return;
  }

  submittingPay.value = true;
  try {
    const response = await axios.post("/transactions", {
      type: "supplier_pay",
      supplier_id: payForm.value.supplier_id,
      amount: payForm.value.amount,
      date: payForm.value.date,
      payment_method: payForm.value.payment_method,
      note: payForm.value.note,
    });

    if (response.data.status) {
      showPayModal.value = false;
      await fetchSuppliers(); // Refresh list

      Swal.fire({
        icon: "success",
        title: "Payment Successful!",
        text: "Transaction recorded successfully.",
        showCancelButton: true,
        confirmButtonText: "Print Receipt",
        cancelButtonText: "Close",
        confirmButtonColor: "#4f46e5",
      }).then((result) => {
        if (result.isConfirmed) {
          if (response.data.data && response.data.data.trx_id) {
            selectedTrxId.value = response.data.data.trx_id;
            showReceiptModal.value = true;
          }
        }
      });
    }
  } catch (error) {
    let msg = "Failed to pay supplier";
    if (error.response && error.response.data && error.response.data.message) {
      msg = error.response.data.message;
    }
    Swal.fire("Error", msg, "error");
  } finally {
    submittingPay.value = false;
  }
};

// 4. History Functions
const openHistoryModal = (supplier) => {
  historySupplier.value = supplier;
  showHistoryModal.value = true;
};

// --- Computed Filter ---
const filteredSuppliers = computed(() => {
  if (!searchQuery.value) return suppliers.value;
  const query = searchQuery.value.toLowerCase();
  return suppliers.value.filter(
    (s) =>
      s.name.toLowerCase().includes(query) ||
      s.phone.includes(query) ||
      (s.email && s.email.toLowerCase().includes(query)) ||
      (s.shop_name && s.shop_name.toLowerCase().includes(query)),
  );
});

// --- Modal Handlers ---
const openAddModal = () => {
  selectedSupplier.value = null;
  showModal.value = true;
};

const openEditModal = (supplier) => {
  selectedSupplier.value = { ...supplier };
  showModal.value = true;
};

const handleModalClose = (refresh) => {
  showModal.value = false;
  selectedSupplier.value = null;
  if (route.query.action) router.replace({ query: null });
  if (refresh) fetchSuppliers();
};

onMounted(() => fetchSuppliers());
</script>

<template>
  <div class="space-y-6">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
    >
      <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
          Supplier Management
        </h2>
        <p class="text-sm text-gray-500">
          Manage your vendors, track payables and purchase history.
        </p>
      </div>
      <button
        @click="openAddModal"
        class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-md transition transform active:scale-95 text-sm font-bold"
      >
        <UserPlusIcon class="w-5 h-5" /> Add Supplier
      </button>
    </div>

    <div
      class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row gap-4"
    >
      <div class="relative flex-1">
        <MagnifyingGlassIcon
          class="absolute left-3 top-2.5 h-5 w-5 text-gray-400"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by Name, Shop or Phone..."
          class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
        />
      </div>
      <div
        class="flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg font-bold text-sm"
      >
        <TruckIcon class="w-5 h-5" /> Total Suppliers: {{ suppliers.length }}
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm overflow-hidden"
    >
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr
              class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-800 text-xs font-bold text-gray-500 uppercase"
            >
              <th class="px-6 py-4">Supplier Info</th>
              <th class="px-6 py-4">Contact</th>
              <th class="px-6 py-4">Shop Name</th>
              <th class="px-6 py-4 text-right">Balance / Payable</th>
              <th class="px-6 py-4 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
            <tr v-if="isLoading">
              <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                <div class="flex justify-center items-center gap-2">
                  <div
                    class="animate-spin rounded-full h-5 w-5 border-b-2 border-indigo-600"
                  ></div>
                  Loading...
                </div>
              </td>
            </tr>

            <tr v-else-if="filteredSuppliers.length === 0">
              <td colspan="5" class="px-6 py-10 text-center text-gray-400">
                No suppliers found matching your search.
              </td>
            </tr>

            <tr
              v-else
              v-for="supplier in filteredSuppliers"
              :key="supplier.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >
              <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                  <div
                    class="h-10 w-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-lg"
                  >
                    {{ supplier.name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <h4 class="font-bold text-gray-800 dark:text-white">
                      {{ supplier.name }}
                    </h4>
                    <p class="text-xs text-gray-500">ID: {{ supplier.id }}</p>
                  </div>
                </div>
              </td>

              <td class="px-6 py-3">
                <div
                  class="flex flex-col text-sm text-gray-600 dark:text-gray-300"
                >
                  <span class="flex items-center gap-1"
                    ><PhoneIcon class="w-3 h-3" /> {{ supplier.phone }}</span
                  >
                  <span v-if="supplier.email" class="text-xs text-gray-400">{{
                    supplier.email
                  }}</span>
                </div>
              </td>

              <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">
                {{ supplier.shop_name || "N/A" }}
              </td>

              <td class="px-6 py-3 text-right font-bold">
                <span
                  :class="
                    Number(supplier.balance) > 0
                      ? 'text-rose-600'
                      : 'text-emerald-600'
                  "
                >
                  {{ Number(supplier.balance) > 0 ? "Due: " : "" }}
                  ৳ {{ Number(supplier.balance).toLocaleString() }}
                </span>
              </td>

              <td class="px-6 py-3 text-center">
                <div class="flex justify-center gap-2">
                  <button
                    v-if="Number(supplier.balance) > 0"
                    @click="openPayModal(supplier)"
                    class="flex items-center gap-1 px-2 py-1.5 bg-rose-600 text-white rounded text-xs font-bold hover:bg-rose-700 transition shadow"
                    title="Pay Supplier"
                  >
                    <BanknotesIcon class="w-3.5 h-3.5" /> Pay
                  </button>
                  <button
                    @click="openHistoryModal(supplier)"
                    class="p-1.5 text-purple-600 bg-purple-50 rounded hover:bg-purple-100 transition"
                    title="Payment History"
                  >
                    <ClockIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="openEditModal(supplier)"
                    class="p-1.5 text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition"
                    title="Edit Supplier"
                  >
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteSupplier(supplier.id)"
                    class="p-1.5 text-red-600 bg-red-50 rounded hover:bg-red-100 transition"
                    title="Delete Supplier"
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

    <SupplierFormModal
      :isOpen="showModal"
      :supplier="selectedSupplier"
      @close="handleModalClose"
    />

    <SupplierHistoryModal
      :isOpen="showHistoryModal"
      :supplier="historySupplier"
      @close="showHistoryModal = false"
    />

    <TransactionReceiptModal
      :isOpen="showReceiptModal"
      :trxId="selectedTrxId"
      @close="showReceiptModal = false"
    />

    <div
      v-if="showPayModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
      <div
        class="bg-white dark:bg-slate-900 w-full max-w-md rounded-2xl shadow-xl overflow-hidden"
      >
        <div
          class="px-6 py-4 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center bg-rose-50 dark:bg-rose-900/20"
        >
          <div>
            <h3 class="text-lg font-bold text-gray-800 dark:text-white">
              Pay Supplier
            </h3>
            <p class="text-xs text-gray-500">
              Vendor:
              <span class="font-bold text-rose-600">{{
                payForm.supplier_name
              }}</span>
            </p>
          </div>
          <button
            @click="showPayModal = false"
            class="text-gray-400 hover:text-red-500"
          >
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div
            class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border border-gray-200"
          >
            <span class="text-sm font-medium text-gray-600"
              >Total Payable Amount:</span
            >
            <span class="text-lg font-bold text-rose-700"
              >৳ {{ Number(payForm.current_balance).toLocaleString() }}</span
            >
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1"
                >Date</label
              >
              <input
                v-model="payForm.date"
                type="date"
                class="w-full p-2 border rounded-lg text-sm focus:ring-2 focus:ring-rose-500 outline-none"
              />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1"
                >Method</label
              >
              <select
                v-model="payForm.payment_method"
                class="w-full p-2 border rounded-lg text-sm focus:ring-2 focus:ring-rose-500 outline-none"
              >
                <option value="cash">Cash</option>
                <option value="bank">Bank Transfer</option>
                <option value="bkash">Bkash</option>
                <option value="nagad">Nagad</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1"
              >Payment Amount <span class="text-red-500">*</span></label
            >
            <div class="relative">
              <span class="absolute left-3 top-2 text-gray-500 font-bold"
                >৳</span
              >
              <input
                v-model="payForm.amount"
                type="number"
                placeholder="Enter Amount"
                class="w-full pl-8 p-2 border rounded-lg font-bold text-lg text-rose-600 focus:ring-2 focus:ring-rose-500 outline-none"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1"
              >Note (Optional)</label
            >
            <textarea
              v-model="payForm.note"
              rows="2"
              class="w-full p-2 border rounded-lg text-sm focus:ring-2 focus:ring-rose-500 outline-none"
              placeholder="e.g. Paid for Purchase #101"
            ></textarea>
          </div>
        </div>

        <div
          class="px-6 py-4 bg-gray-50 dark:bg-slate-800 flex justify-end gap-3"
        >
          <button
            @click="showPayModal = false"
            class="px-4 py-2 text-gray-600 font-bold hover:bg-gray-200 rounded-lg text-sm"
          >
            Cancel
          </button>
          <button
            @click="submitPayment"
            :disabled="submittingPay"
            class="px-6 py-2 bg-rose-600 text-white font-bold rounded-lg hover:bg-rose-700 disabled:opacity-50 text-sm flex items-center gap-2"
          >
            <span
              v-if="submittingPay"
              class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"
            ></span>
            Confirm Payment
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
