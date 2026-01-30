<script setup>
import { ref, watch, computed } from "vue";
import axios from "../axios";
import {
  XMarkIcon,
  PrinterIcon,
  BanknotesIcon,
  CalendarDaysIcon,
} from "@heroicons/vue/24/outline";

// Import Receipt Modal
import TransactionReceiptModal from "./TransactionReceiptModal.vue";

const props = defineProps({
  isOpen: Boolean,
  supplier: Object,
});

const emit = defineEmits(["close"]);

const transactions = ref([]);
const loading = ref(false);

// State for Receipt Modal
const showReceiptModal = ref(false);
const selectedTrxId = ref(null);

watch(
  () => props.supplier,
  async (newVal) => {
    if (newVal && props.isOpen) {
      fetchHistory(newVal.id);
    }
  },
);

const fetchHistory = async (supplierId) => {
  loading.value = true;
  try {
    const response = await axios.get(
      `/transactions?supplier_id=${supplierId}&type=debit`,
    );
    if (response.data.status) {
      transactions.value = response.data.data.data;
    }
  } catch (error) {
    console.error("Error fetching history", error);
  } finally {
    loading.value = false;
  }
};

// Open Receipt inside Modal
const openReceipt = (trxId) => {
  selectedTrxId.value = trxId;
  showReceiptModal.value = true;
};

const totalPaid = computed(() => {
  return transactions.value.reduce((sum, t) => sum + Number(t.amount), 0);
});

// 🔥 Date & Time Formatter Function
const formatDateTime = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);

  return date.toLocaleString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: true,
  });
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
  >
    <div
      class="bg-white dark:bg-slate-900 w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
    >
      <div
        class="px-6 py-4 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center bg-gray-50 dark:bg-slate-800/50"
      >
        <div>
          <h3
            class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"
          >
            <BanknotesIcon class="w-5 h-5 text-indigo-600" />
            Payment History
          </h3>
          <p class="text-xs text-gray-500 mt-0.5">
            Supplier:
            <span class="font-bold text-indigo-600">{{ supplier?.name }}</span>
          </p>
        </div>
        <button
          @click="$emit('close')"
          class="text-gray-400 hover:text-red-500 transition"
        >
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <div
        class="px-6 py-3 bg-indigo-50 dark:bg-indigo-900/20 flex justify-between items-center border-b border-indigo-100 dark:border-indigo-800"
      >
        <span class="text-sm font-bold text-indigo-700 dark:text-indigo-300"
          >Total Paid Amount:</span
        >
        <span class="text-lg font-bold text-indigo-700 dark:text-indigo-300"
          >৳ {{ totalPaid.toLocaleString() }}</span
        >
      </div>

      <div class="p-0 overflow-y-auto flex-1">
        <table class="w-full text-left text-sm">
          <thead
            class="bg-gray-100 dark:bg-slate-800 text-gray-600 font-bold sticky top-0 z-10"
          >
            <tr>
              <th class="px-6 py-3">Date & Time</th>
              <th class="px-6 py-3">TRX ID</th>
              <th class="px-6 py-3">Method</th>
              <th class="px-6 py-3 text-right">Amount</th>
              <th class="px-6 py-3 text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
            <tr v-if="loading">
              <td colspan="5" class="text-center py-10 text-gray-500">
                Loading history...
              </td>
            </tr>

            <tr v-else-if="transactions.length === 0">
              <td colspan="5" class="text-center py-10 text-gray-400">
                No payment records found.
              </td>
            </tr>

            <tr
              v-for="trx in transactions"
              :key="trx.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >
              <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                <div class="flex items-center gap-2">
                  <CalendarDaysIcon class="w-4 h-4 text-gray-400" />
                  <span class="font-medium">{{
                    formatDateTime(trx.created_at || trx.date)
                  }}</span>
                </div>
              </td>
              <td class="px-6 py-3 font-mono text-xs text-indigo-600 font-bold">
                {{ trx.trx_id }}
              </td>
              <td class="px-6 py-3 uppercase text-xs font-bold text-gray-500">
                {{ trx.payment_method }}
              </td>
              <td class="px-6 py-3 text-right font-bold text-emerald-600">
                ৳ {{ Number(trx.amount).toLocaleString() }}
              </td>
              <td class="px-6 py-3 text-center">
                <button
                  @click="openReceipt(trx.trx_id)"
                  class="flex items-center gap-1 px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded text-xs font-bold transition mx-auto shadow-sm"
                  title="View Receipt"
                >
                  <PrinterIcon class="w-3.5 h-3.5" /> Receipt
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        class="p-4 border-t border-gray-100 dark:border-slate-800 text-right bg-gray-50 dark:bg-slate-900"
      >
        <button
          @click="$emit('close')"
          class="px-5 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold hover:bg-gray-100 transition text-sm"
        >
          Close
        </button>
      </div>
    </div>

    <TransactionReceiptModal
      :isOpen="showReceiptModal"
      :trxId="selectedTrxId"
      @close="showReceiptModal = false"
    />
  </div>
</template>
