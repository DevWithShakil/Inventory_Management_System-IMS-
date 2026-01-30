<script setup>
import { defineProps, defineEmits, ref } from "vue";
import {
  XMarkIcon,
  DocumentTextIcon,
  ArrowPathIcon,
  EyeIcon,
} from "@heroicons/vue/24/outline";

// 🔥 আপনার দেওয়া ফাইনাল ইনভয়েস মোডাল ইম্পোর্ট
import InvoiceModal from "./InvoiceModal.vue";

const props = defineProps({
  isOpen: Boolean,
  customer: Object,
  historyData: Array,
  loading: Boolean,
});

const emit = defineEmits(["close"]);

// --- Invoice Modal State ---
const showInvoiceModal = ref(false);
const selectedSale = ref(null);

// ইনভয়েস ওপেন করার ফাংশন
const openInvoice = (saleData) => {
  selectedSale.value = saleData;
  showInvoiceModal.value = true;
};

// তারিখ ফরম্যাট হেল্পার
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
            Purchase History
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
          No purchase history found for this customer.
        </div>

        <table v-else class="w-full text-left border-collapse">
          <thead class="bg-gray-50 dark:bg-slate-800 sticky top-0 z-10">
            <tr class="text-xs font-bold text-gray-500 uppercase">
              <th class="px-6 py-4">Date</th>
              <th class="px-6 py-4">Invoice No</th>
              <th class="px-6 py-4 text-right">Amount</th>
              <th class="px-6 py-4 text-center">Paid</th>
              <th class="px-6 py-4 text-center">Status</th>
              <th class="px-6 py-4 text-center">Invoice</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800 text-sm">
            <tr
              v-for="sale in historyData"
              :key="sale.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >
              <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                {{ formatDate(sale.date) }}
              </td>

              <td class="px-6 py-4 font-bold text-indigo-600">
                {{ sale.invoice_no }}
              </td>

              <td
                class="px-6 py-4 text-right font-bold text-gray-800 dark:text-white"
              >
                ৳ {{ Number(sale.grand_total).toLocaleString() }}
              </td>

              <td class="px-6 py-4 text-center text-emerald-600 font-medium">
                ৳ {{ Number(sale.paid_amount).toLocaleString() }}
              </td>

              <td class="px-6 py-4 text-center">
                <span
                  v-if="sale.sales_returns && sale.sales_returns.length > 0"
                  class="px-2 py-1 bg-rose-100 text-rose-600 rounded text-xs font-bold"
                >
                  Returned
                </span>
                <span
                  v-else-if="sale.due_amount > 0"
                  class="px-2 py-1 bg-orange-100 text-orange-600 rounded text-xs font-bold"
                >
                  Due
                </span>
                <span
                  v-else
                  class="px-2 py-1 bg-emerald-100 text-emerald-600 rounded text-xs font-bold"
                >
                  Completed
                </span>
              </td>

              <td class="px-6 py-4 text-center">
                <button
                  @click="openInvoice(sale)"
                  class="p-2 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition group"
                  title="View Invoice / Receipt"
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
        <span class="text-xs text-gray-500">
          Showing last {{ historyData ? historyData.length : 0 }} transactions
        </span>
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
    :sale="selectedSale"
    @close="showInvoiceModal = false"
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
