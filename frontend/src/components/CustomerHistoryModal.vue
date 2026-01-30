<script setup>
import { defineProps, defineEmits, ref } from "vue";
import {
  XMarkIcon,
  DocumentTextIcon,
  ArrowPathIcon,
  EyeIcon,
  BanknotesIcon,
} from "@heroicons/vue/24/outline";

import InvoiceModal from "./InvoiceModal.vue";
import TransactionReceiptModal from "./TransactionReceiptModal.vue";

const props = defineProps({
  isOpen: Boolean,
  customer: Object,
  historyData: {
    type: Array,
    default: () => [],
  },
  loading: Boolean,
});

const emit = defineEmits(["close"]);

// --- Modal States ---
const showInvoiceModal = ref(false);
const showReceiptModal = ref(false);
const selectedItem = ref(null);
const selectedTrxId = ref(null);

// 🔥 Smart Open Logic
const openDocument = (item) => {
  // ১. যদি trx_id থাকে, মানে এটি পেমেন্ট -> Receipt Modal ওপেন করো
  if (item.trx_id) {
    selectedTrxId.value = item.trx_id;
    showReceiptModal.value = true;
  }
  // ২. অন্যথায় এটি সেল -> Invoice Modal ওপেন করো
  else {
    selectedItem.value = item;
    showInvoiceModal.value = true;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  return new Date(dateString).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
  >
    <div
      class="bg-white dark:bg-slate-900 w-full max-w-4xl rounded-2xl shadow-2xl flex flex-col max-h-[90vh]"
    >
      <div
        class="p-6 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center"
      >
        <div>
          <h3
            class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2"
          >
            <DocumentTextIcon class="w-6 h-6 text-indigo-600" />
            Customer History
          </h3>
          <p class="text-sm text-gray-500 mt-1" v-if="customer">
            Customer:
            <span class="font-bold text-indigo-600">{{ customer.name }}</span>
          </p>
        </div>
        <button
          @click="emit('close')"
          class="p-2 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-full transition"
        >
          <XMarkIcon class="w-6 h-6 text-gray-500" />
        </button>
      </div>

      <div class="p-0 overflow-y-auto custom-scrollbar flex-1">
        <div v-if="loading" class="p-10 text-center text-gray-500">
          <ArrowPathIcon class="w-8 h-8 mx-auto animate-spin mb-2" />
          Loading history...
        </div>

        <div
          v-else-if="!historyData || historyData.length === 0"
          class="p-10 text-center text-gray-400"
        >
          No history found for this customer.
        </div>

        <table v-else class="w-full text-left border-collapse">
          <thead class="bg-gray-50 dark:bg-slate-800 sticky top-0 z-10">
            <tr class="text-xs font-bold text-gray-500 uppercase">
              <th class="px-6 py-4">Date</th>
              <th class="px-6 py-4">Ref No</th>
              <th class="px-6 py-4">Type</th>
              <th class="px-6 py-4 text-right">Amount</th>
              <th class="px-6 py-4 text-center">Status</th>
              <th class="px-6 py-4 text-center">View</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800 text-sm">
            <tr
              v-for="item in historyData"
              :key="item.id || item.trx_id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >
              <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                {{ formatDate(item.date) }}
              </td>

              <td class="px-6 py-4 font-bold font-mono text-xs">
                <span v-if="item.invoice_no" class="text-indigo-600">{{
                  item.invoice_no
                }}</span>
                <span v-else-if="item.trx_id" class="text-emerald-600">{{
                  item.trx_id
                }}</span>
              </td>

              <td class="px-6 py-4">
                <span
                  v-if="item.invoice_no"
                  class="flex items-center gap-1 text-xs font-bold text-gray-600"
                >
                  <DocumentTextIcon class="w-4 h-4" /> Sale
                </span>
                <span
                  v-else
                  class="flex items-center gap-1 text-xs font-bold text-emerald-600"
                >
                  <BanknotesIcon class="w-4 h-4" /> Payment
                </span>
              </td>

              <td
                class="px-6 py-4 text-right font-bold text-gray-800 dark:text-white"
              >
                <span v-if="item.invoice_no"
                  >৳ {{ Number(item.grand_total).toLocaleString() }}</span
                >
                <span v-else class="text-emerald-600"
                  >৳ {{ Number(item.amount).toLocaleString() }}</span
                >
              </td>

              <td class="px-6 py-4 text-center">
                <template v-if="item.invoice_no">
                  <span
                    v-if="item.sales_returns && item.sales_returns.length > 0"
                    class="px-2 py-1 bg-rose-100 text-rose-600 rounded text-xs font-bold"
                    >Returned</span
                  >
                  <span
                    v-else-if="Number(item.due_amount) > 0"
                    class="px-2 py-1 bg-orange-100 text-orange-600 rounded text-xs font-bold"
                    >Due</span
                  >
                  <span
                    v-else
                    class="px-2 py-1 bg-emerald-100 text-emerald-600 rounded text-xs font-bold"
                    >Paid</span
                  >
                </template>
                <template v-else>
                  <span
                    class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold uppercase"
                    >Success</span
                  >
                </template>
              </td>

              <td class="px-6 py-4 text-center">
                <button
                  @click="openDocument(item)"
                  class="p-2 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition group"
                  title="View Document"
                >
                  <EyeIcon
                    class="w-5 h-5 group-hover:scale-110 transition-transform"
                  />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        class="p-4 border-t border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-900 rounded-b-2xl flex justify-between items-center"
      >
        <span class="text-xs text-gray-500"
          >Showing last {{ historyData ? historyData.length : 0 }} records</span
        >
        <button
          @click="emit('close')"
          class="px-4 py-2 bg-gray-200 dark:bg-slate-800 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-bold hover:bg-gray-300 transition"
        >
          Close
        </button>
      </div>
    </div>
  </div>

  <InvoiceModal
    :isOpen="showInvoiceModal"
    :sale="selectedItem"
    @close="showInvoiceModal = false"
  />

  <TransactionReceiptModal
    :isOpen="showReceiptModal"
    :trxId="selectedTrxId"
    @close="showReceiptModal = false"
  />
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 10px;
}
</style>
