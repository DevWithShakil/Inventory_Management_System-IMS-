<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import axios from "../axios";
import Swal from "sweetalert2";

// Components
import CustomerFormModal from "../components/CustomerFormModal.vue";
import CustomerHistoryModal from "../components/CustomerHistoryModal.vue";
import TransactionReceiptModal from "../components/TransactionReceiptModal.vue";

import {
  MagnifyingGlassIcon,
  UserPlusIcon,
  PencilSquareIcon,
  TrashIcon,
  UserIcon,
  PhoneIcon,
  GiftIcon,
  EyeIcon,
  BanknotesIcon,
  XMarkIcon,
  CreditCardIcon,
  DevicePhoneMobileIcon,
  CheckCircleIcon,
  ArrowRightCircleIcon,
} from "@heroicons/vue/24/outline";

const route = useRoute();
const router = useRouter();

// --- State ---
const customers = ref([]);
const isLoading = ref(false);
const searchQuery = ref("");

// Modals State
const showModal = ref(false);
const selectedCustomer = ref(null);

// History Modal State
const showHistoryModal = ref(false);
const historyLoading = ref(false);
const customerHistory = ref([]);
const historyCustomer = ref(null);

// Receipt Modal State
const showReceiptModal = ref(false);
const selectedTrxId = ref(null);

// Date Helper
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
  customer_id: null,
  customer_name: "",
  current_due: 0,
  amount: "",
  date: getLocalDate(),
  payment_method: "cash",
  note: "",
});

watch(
  () => route.query.action,
  (newAction) => {
    if (newAction === "add") openAddModal();
  },
  { immediate: true },
);

// --- API Actions ---

const fetchCustomers = async () => {
  isLoading.value = true;
  try {
    const response = await axios.get("/customers");
    if (response.data.status) {
      customers.value = response.data.data.data || response.data.data;
    }
  } catch (error) {
    console.error("Error fetching customers:", error);
  } finally {
    isLoading.value = false;
  }
};

const deleteCustomer = async (id) => {
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
      await axios.delete(`/customers/${id}`);
      Swal.fire({
        icon: "success",
        title: "Deleted!",
        timer: 1500,
        showConfirmButton: false,
      });
      fetchCustomers();
    } catch (error) {
      Swal.fire("Error", "Failed to delete.", "error");
    }
  }
};

const viewHistory = async (customer) => {
  historyCustomer.value = customer;
  showHistoryModal.value = true;
  historyLoading.value = true;
  customerHistory.value = [];

  try {
    const response = await axios.get(`/customers/${customer.id}/history`);
    if (response.data.status) {
      const result = response.data.data;
      if (Array.isArray(result)) {
        customerHistory.value = result;
      } else if (result && result.data && Array.isArray(result.data)) {
        customerHistory.value = result.data;
      } else {
        customerHistory.value = [];
      }
    }
  } catch (error) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "Failed to load history",
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 3000,
    });
  } finally {
    historyLoading.value = false;
  }
};

// --- Payment Logic ---

const openPayModal = (customer) => {
  payForm.value = {
    customer_id: customer.id,
    customer_name: customer.name,
    current_due: customer.balance,
    amount: "",
    date: getLocalDate(),
    payment_method: "cash",
    note: "",
  };
  showPayModal.value = true;
};

const setPaymentMethod = (method) => {
  payForm.value.payment_method = method;
};

const submitPayment = async () => {
  if (!payForm.value.amount || payForm.value.amount <= 0) {
    Swal.fire("Error", "Please enter a valid amount", "error");
    return;
  }

  if (Number(payForm.value.amount) > Number(payForm.value.current_due)) {
    Swal.fire(
      "Warning",
      `Amount cannot exceed due (৳ ${payForm.value.current_due})`,
      "warning",
    );
    return;
  }

  submittingPay.value = true;

  try {
    const response = await axios.post("/transactions", {
      type: "customer_pay",
      customer_id: payForm.value.customer_id,
      amount: payForm.value.amount,
      date: payForm.value.date,
      payment_method: payForm.value.payment_method,
      note: payForm.value.note,
    });

    if (response.data.status) {
      // 1. Check for Redirect URL (Online Payment)
      const redirectUrl = response.data.gateway_url || response.data.url;
      if (redirectUrl) {
        window.location.href = redirectUrl;
        return; // Stop here, browser will redirect
      }

      // 2. Fallback Error for Online Payment
      if (payForm.value.payment_method !== "cash") {
        Swal.fire(
          "Error",
          "Payment Gateway initialization failed. No redirect URL received.",
          "error",
        );
        submittingPay.value = false;
        return;
      }

      // 3. Cash Payment Success Logic
      showPayModal.value = false;
      await fetchCustomers();

      Swal.fire({
        icon: "success",
        title: "Payment Collected!",
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
      submittingPay.value = false;
    }
  } catch (error) {
    submittingPay.value = false;
    let msg = "Failed to collect payment";
    if (error.response && error.response.data && error.response.data.message) {
      msg = error.response.data.message;
    }
    Swal.fire("Error", msg, "error");
  }
};

// --- Helpers ---
const filteredCustomers = computed(() => {
  if (!searchQuery.value) return customers.value;
  const query = searchQuery.value.toLowerCase();
  return customers.value.filter(
    (c) =>
      c.name.toLowerCase().includes(query) ||
      c.phone.includes(query) ||
      (c.email && c.email.toLowerCase().includes(query)),
  );
});

const openAddModal = () => {
  selectedCustomer.value = null;
  showModal.value = true;
};
const openEditModal = (c) => {
  selectedCustomer.value = { ...c };
  showModal.value = true;
};
const handleModalClose = (refresh) => {
  showModal.value = false;
  selectedCustomer.value = null;
  if (route.query.action) router.replace({ query: null });
  if (refresh) fetchCustomers();
};

// 🔥 UPDATED onMounted to Handle SSLCommerz Return
onMounted(() => {
  fetchCustomers();

  // Check URL parameters for Payment Status
  const { payment, trx_id, message } = route.query;

  if (payment === "success") {
    Swal.fire({
      icon: "success",
      title: "Online Payment Successful!",
      text: `Transaction ID: ${trx_id}`,
      showCancelButton: true,
      confirmButtonText: "Print Receipt",
      cancelButtonText: "Close",
      confirmButtonColor: "#4f46e5",
    }).then((result) => {
      if (result.isConfirmed && trx_id) {
        // Open Receipt Modal
        selectedTrxId.value = trx_id;
        showReceiptModal.value = true;
      }
    });
  } else if (payment === "failed" || payment === "error") {
    Swal.fire({
      icon: "error",
      title: "Payment Failed",
      text: message || "Transaction could not be completed.",
      confirmButtonColor: "#d33",
    });
  } else if (payment === "cancel") {
    Swal.fire({
      icon: "info",
      title: "Payment Cancelled",
      text: "You cancelled the payment process.",
      confirmButtonColor: "#6b7280",
    });
  }

  // Clean URL if payment params exist (prevents alert on refresh)
  if (payment) {
    router.replace({ query: null });
  }
});
</script>

<template>
  <div class="space-y-6">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
    >
      <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
          Customer Management
        </h2>
        <p class="text-sm text-gray-500">
          Manage customers, loyalty points, dues and purchase history.
        </p>
      </div>
      <button
        @click="openAddModal"
        class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-md transition transform active:scale-95 text-sm font-bold"
      >
        <UserPlusIcon class="w-5 h-5" /> Add Customer
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
          placeholder="Search by Name or Phone..."
          class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
        />
      </div>
      <div
        class="flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg font-bold text-sm"
      >
        <UserIcon class="w-5 h-5" /> Total Customers: {{ customers.length }}
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
              <th class="px-6 py-4">Customer Info</th>
              <th class="px-6 py-4">Contact</th>
              <th class="px-6 py-4 text-center">Points</th>
              <th class="px-6 py-4 text-right">Total Spent</th>
              <th class="px-6 py-4 text-right">Balance / Due</th>
              <th class="px-6 py-4 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
            <tr v-if="isLoading">
              <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                Loading...
              </td>
            </tr>
            <tr v-else-if="filteredCustomers.length === 0">
              <td colspan="6" class="px-6 py-10 text-center text-gray-400">
                No customers found.
              </td>
            </tr>
            <tr
              v-else
              v-for="customer in filteredCustomers"
              :key="customer.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >
              <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                  <div
                    class="h-10 w-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-lg"
                  >
                    {{ customer.name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <h4 class="font-bold text-gray-800 dark:text-white">
                      {{ customer.name }}
                    </h4>
                    <p class="text-xs text-gray-500">ID: {{ customer.id }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-3">
                <div
                  class="flex flex-col text-sm text-gray-600 dark:text-gray-300"
                >
                  <span class="flex items-center gap-1"
                    ><PhoneIcon class="w-3 h-3" /> {{ customer.phone }}</span
                  >
                  <span v-if="customer.email" class="text-xs text-gray-400">{{
                    customer.email
                  }}</span>
                </div>
              </td>
              <td class="px-6 py-3 text-center">
                <span
                  class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-xs font-bold flex items-center justify-center gap-1 w-max mx-auto"
                >
                  <GiftIcon class="w-3 h-3" /> {{ customer.reward_points || 0 }}
                </span>
              </td>
              <td
                class="px-6 py-3 text-right font-bold text-gray-800 dark:text-white"
              >
                ৳ {{ Number(customer.total_spent || 0).toLocaleString() }}
              </td>
              <td class="px-6 py-3 text-right font-bold">
                <span
                  :class="
                    Number(customer.balance) > 0
                      ? 'text-rose-600'
                      : 'text-emerald-600'
                  "
                >
                  {{ Number(customer.balance) > 0 ? "Due: " : "" }} ৳
                  {{ Number(customer.balance).toLocaleString() }}
                </span>
              </td>
              <td class="px-6 py-3 text-center">
                <div class="flex justify-center gap-2">
                  <button
                    v-if="Number(customer.balance) > 0"
                    @click="openPayModal(customer)"
                    class="flex items-center gap-1 px-2 py-1.5 bg-emerald-600 text-white rounded text-xs font-bold hover:bg-emerald-700 transition shadow"
                    title="Collect Due"
                  >
                    <BanknotesIcon class="w-3.5 h-3.5" /> Pay
                  </button>
                  <button
                    @click="viewHistory(customer)"
                    class="p-1.5 text-indigo-600 bg-indigo-50 rounded hover:bg-indigo-100 transition"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="openEditModal(customer)"
                    class="p-1.5 text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition"
                  >
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteCustomer(customer.id)"
                    class="p-1.5 text-red-600 bg-red-50 rounded hover:bg-red-100 transition"
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

    <CustomerFormModal
      :isOpen="showModal"
      :customer="selectedCustomer"
      @close="handleModalClose"
    />
    <CustomerHistoryModal
      :isOpen="showHistoryModal"
      :customer="historyCustomer"
      :historyData="customerHistory"
      :loading="historyLoading"
      @close="showHistoryModal = false"
    />
    <TransactionReceiptModal
      :isOpen="showReceiptModal"
      :trxId="selectedTrxId"
      @close="showReceiptModal = false"
    />

    <div
      v-if="showPayModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
    >
      <div
        class="bg-white dark:bg-slate-900 w-full max-w-md rounded-2xl shadow-xl overflow-hidden animate-fade-in-up flex flex-col max-h-[90vh]"
      >
        <div
          class="px-5 py-3 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center bg-gray-50 dark:bg-slate-800/50 flex-shrink-0"
        >
          <div>
            <h3 class="text-base font-bold text-gray-800 dark:text-white">
              Collect Payment
            </h3>
            <p class="text-[10px] text-gray-500">
              For:
              <span class="font-bold text-indigo-600">{{
                payForm.customer_name
              }}</span>
            </p>
          </div>
          <button
            @click="showPayModal = false"
            class="text-gray-400 hover:text-red-500 transition"
          >
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <div class="p-4 space-y-4 overflow-y-auto flex-1">
          <div
            class="bg-rose-50 dark:bg-rose-900/20 p-3 rounded-xl text-center border border-rose-100 dark:border-rose-800/30"
          >
            <p
              class="text-[10px] font-medium text-rose-600 dark:text-rose-400 uppercase tracking-wider"
            >
              Current Due Amount
            </p>
            <h2
              class="text-2xl font-extrabold text-rose-700 dark:text-rose-300"
            >
              ৳ {{ Number(payForm.current_due).toLocaleString() }}
            </h2>
          </div>

          <div>
            <label
              class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2"
              >Payment Method</label
            >
            <div class="grid grid-cols-3 gap-2">
              <button
                @click="setPaymentMethod('cash')"
                :class="`flex flex-col items-center justify-center gap-1 p-2 rounded-lg border transition active:scale-95 ${payForm.payment_method === 'cash' ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300' : 'bg-white dark:bg-slate-800 text-gray-600 border-gray-200 hover:bg-gray-50'}`"
              >
                <BanknotesIcon class="w-5 h-5" /><span
                  class="text-[10px] font-bold"
                  >Cash</span
                >
              </button>
              <button
                @click="setPaymentMethod('card')"
                :class="`flex flex-col items-center justify-center gap-1 p-2 rounded-lg border transition active:scale-95 ${payForm.payment_method === 'card' ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300' : 'bg-white dark:bg-slate-800 text-gray-600 border-gray-200 hover:bg-gray-50'}`"
              >
                <CreditCardIcon class="w-5 h-5" /><span
                  class="text-[10px] font-bold"
                  >Card</span
                >
              </button>
              <button
                @click="setPaymentMethod('mobile_bank')"
                :class="`flex flex-col items-center justify-center gap-1 p-2 rounded-lg border transition active:scale-95 ${payForm.payment_method === 'mobile_bank' ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300' : 'bg-white dark:bg-slate-800 text-gray-600 border-gray-200 hover:bg-gray-50'}`"
              >
                <DevicePhoneMobileIcon class="w-5 h-5" /><span
                  class="text-[10px] font-bold"
                  >MFS</span
                >
              </button>
            </div>
          </div>

          <div class="space-y-3">
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1"
                >{{
                  payForm.payment_method === "cash"
                    ? "Amount to Receive"
                    : "Amount to Pay Online"
                }}
                <span class="text-red-500">*</span></label
              >
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-500 font-bold"
                  >৳</span
                >
                <input
                  v-model="payForm.amount"
                  type="number"
                  placeholder="Enter Amount"
                  class="w-full pl-8 p-2 border border-gray-300 rounded-lg font-bold text-base text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none"
                />
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1"
                >Date</label
              >
              <input
                v-model="payForm.date"
                type="date"
                class="w-full p-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none"
              />
            </div>

            <div
              v-if="payForm.payment_method !== 'cash'"
              class="flex items-center gap-2 p-2 bg-blue-50 text-blue-700 text-[10px] rounded-lg border border-blue-100"
            >
              <ArrowRightCircleIcon class="w-4 h-4 flex-shrink-0" />
              <p>
                You will be redirected to the secure gateway to complete the
                payment.
              </p>
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1"
                >Note (Optional)</label
              >
              <textarea
                v-model="payForm.note"
                rows="2"
                class="w-full p-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none"
                placeholder="Reference..."
              ></textarea>
            </div>
          </div>
        </div>

        <div
          class="px-5 py-3 bg-gray-50 dark:bg-slate-800 border-t border-gray-100 dark:border-slate-800 flex justify-end gap-2 flex-shrink-0"
        >
          <button
            @click="showPayModal = false"
            class="px-4 py-2 text-gray-600 font-bold hover:bg-gray-200 rounded-lg text-xs transition"
          >
            Cancel
          </button>
          <button
            @click="submitPayment"
            :disabled="submittingPay"
            class="px-5 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 disabled:opacity-50 text-xs flex items-center gap-2 shadow hover:shadow-indigo-500/30 transition transform active:scale-95"
          >
            <component
              :is="
                payForm.payment_method === 'cash'
                  ? CheckCircleIcon
                  : ArrowRightCircleIcon
              "
              class="w-4 h-4"
              v-if="!submittingPay"
            />
            <span
              v-if="submittingPay"
              class="animate-spin h-3 w-3 border-2 border-white border-t-transparent rounded-full"
            ></span>
            {{
              payForm.payment_method === "cash"
                ? "Confirm Payment"
                : "Pay Online"
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in-up {
  animation: fadeInUp 0.3s ease-out forwards;
}
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
</style>
