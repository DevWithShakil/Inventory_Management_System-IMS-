<script setup>
import { ref, watch, nextTick } from "vue";
import axios from "../axios";
import {
  XMarkIcon,
  PrinterIcon,
  ReceiptPercentIcon,
} from "@heroicons/vue/24/outline";

const props = defineProps({
  isOpen: Boolean,
  trxId: [String, Number],
});

const emit = defineEmits(["close"]);

const transaction = ref(null);
const settings = ref(null);
const printMode = ref("a4");
const loading = ref(false);

// 1. Fetch Settings
const fetchSettings = async () => {
  try {
    const response = await axios.get("/settings");
    if (response.data.status) settings.value = response.data.data;
  } catch (error) {
    console.error("Failed to load settings", error);
  }
};

// 2. Watcher for Opening Modal
watch(
  () => props.trxId,
  async (newVal) => {
    if (newVal && props.isOpen) {
      await fetchSettings();
      fetchTransaction(newVal);
    }
  },
);

// 3. Fetch Transaction Data
const fetchTransaction = async (id) => {
  loading.value = true;
  transaction.value = null;
  try {
    const response = await axios.get(`/transactions/${id}`);
    if (response.data.status) {
      transaction.value = response.data.data;
    }
  } catch (error) {
    console.error("Error loading receipt", error);
  } finally {
    loading.value = false;
  }
};

// 4. Date Formatter (UTC to Local Time)
const formatDate = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);

  // Invalid date check
  if (isNaN(date.getTime())) return dateString;

  return date.toLocaleString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: true,
  });
};

const getLogoUrl = (path) => {
  if (!path) return null;
  if (path.startsWith("http")) return path;
  return `http://localhost:8000/storage/${path}`;
};

const handlePrint = async (mode) => {
  printMode.value = mode;
  await nextTick();
  window.print();
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm print:p-0 print:bg-white"
  >
    <div
      class="bg-white w-full max-w-2xl rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] print:shadow-none print:w-full print:max-w-none print:max-h-none print:overflow-visible"
    >
      <div
        class="flex justify-between items-center p-4 border-b border-gray-100 bg-gray-50 print:hidden flex-shrink-0"
      >
        <h3 class="font-bold text-gray-700">Payment Receipt</h3>
        <div class="flex gap-2">
          <button
            @click="handlePrint('thermal')"
            class="flex items-center gap-2 px-3 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition text-sm font-medium"
          >
            <ReceiptPercentIcon class="w-4 h-4" /> Thermal
          </button>
          <button
            @click="handlePrint('a4')"
            class="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium"
          >
            <PrinterIcon class="w-4 h-4" /> A4
          </button>
          <button
            @click="$emit('close')"
            class="p-2 text-gray-500 hover:bg-gray-200 rounded-lg transition"
          >
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
      </div>

      <div
        id="printable-area"
        class="overflow-y-auto bg-white print:overflow-visible"
        :class="printMode === 'thermal' ? 'thermal-layout p-2' : 'p-8'"
      >
        <div v-if="loading" class="text-center py-20 text-gray-500">
          Loading...
        </div>

        <div v-else-if="transaction">
          <div
            class="flex justify-between items-start mb-6"
            :class="{
              'flex-col items-center text-center gap-2 mb-2':
                printMode === 'thermal',
            }"
          >
            <div
              :class="{ 'flex flex-col items-center': printMode === 'thermal' }"
            >
              <img
                v-if="settings?.logo"
                :src="getLogoUrl(settings.logo)"
                class="object-contain mb-2"
                :class="printMode === 'thermal' ? 'h-12 w-auto' : 'h-16 w-auto'"
                alt="Logo"
              />
              <h1
                class="font-extrabold text-indigo-600 tracking-tight"
                :class="printMode === 'thermal' ? 'text-xl' : 'text-3xl'"
              >
                {{ settings?.company_name || "Smart IMS" }}
              </h1>
              <p
                class="text-gray-500 whitespace-pre-line"
                :class="
                  printMode === 'thermal' ? 'text-[10px]' : 'text-sm mt-1'
                "
              >
                {{ settings?.company_address || "Dhaka, Bangladesh" }}
              </p>
              <p
                class="text-gray-400"
                :class="printMode === 'thermal' ? 'text-[10px]' : 'text-xs'"
              >
                {{ settings?.company_phone }}
              </p>
            </div>

            <div
              :class="{
                'text-center w-full border-t border-b border-dashed py-1 my-1':
                  printMode === 'thermal',
                'text-right': printMode !== 'thermal',
              }"
            >
              <h2
                class="font-bold text-gray-800"
                :class="printMode === 'thermal' ? 'text-sm' : 'text-xl'"
              >
                MONEY RECEIPT
              </h2>
              <p
                class="text-gray-600 font-mono"
                :class="printMode === 'thermal' ? 'text-xs' : 'text-sm mt-1'"
              >
                #{{ transaction.trx_id }}
              </p>

              <p
                class="text-gray-500"
                :class="
                  printMode === 'thermal' ? 'text-[10px]' : 'text-xs mt-1'
                "
              >
                Date: {{ formatDate(transaction.date) }}
              </p>
            </div>
          </div>

          <div
            class="mb-6"
            :class="
              printMode === 'thermal'
                ? 'text-xs border-b border-dashed pb-2'
                : 'flex justify-between bg-gray-50 p-4 rounded-lg print:bg-transparent print:p-0 print:border print:border-gray-200'
            "
          >
            <div>
              <p
                class="font-bold text-gray-400 uppercase tracking-wider mb-1"
                :class="{
                  'text-[10px]': printMode === 'thermal',
                  'text-xs': printMode !== 'thermal',
                }"
              >
                {{
                  transaction.type === "credit" ? "Received From" : "Paid To"
                }}
              </p>
              <h4
                class="font-bold text-gray-800"
                :class="{ 'text-sm': printMode === 'thermal' }"
              >
                {{
                  transaction.type === "credit"
                    ? transaction.customer?.name
                    : transaction.supplier?.name
                }}
              </h4>
              <p
                class="text-gray-500"
                :class="{
                  'text-[11px]': printMode === 'thermal',
                  'text-sm': printMode !== 'thermal',
                }"
              >
                {{
                  transaction.type === "credit"
                    ? transaction.customer?.phone
                    : transaction.supplier?.phone
                }}
              </p>
            </div>
            <div v-if="printMode !== 'thermal'" class="text-right">
              <p
                class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1"
              >
                Payment Method
              </p>
              <span
                class="font-bold text-sm uppercase bg-gray-200 px-2 py-1 rounded"
                >{{ transaction.payment_method }}</span
              >
            </div>
          </div>

          <div class="mb-6">
            <h3
              class="font-bold border-b-2 border-gray-800 mb-2 pb-1 flex justify-between items-end"
            >
              <span
                :class="
                  printMode === 'thermal'
                    ? 'text-xs'
                    : 'text-sm text-gray-800 uppercase'
                "
                >Payment Allocation</span
              >
            </h3>

            <table
              class="w-full text-left border-collapse"
              :class="{
                'text-xs': printMode === 'thermal',
                'text-sm': printMode !== 'thermal',
              }"
            >
              <thead>
                <tr
                  class="bg-gray-100 text-gray-600 font-bold border-b border-gray-300"
                  :class="{
                    'text-[9px]': printMode === 'thermal',
                    'text-xs': printMode !== 'thermal',
                  }"
                >
                  <th class="py-2 pl-2">Inv. Date</th>
                  <th class="py-2">Invoice No</th>
                  <th class="py-2" v-if="printMode !== 'thermal'">
                    Items / Details
                  </th>
                  <th class="py-2 pr-2 text-right">Adjusted</th>
                </tr>
              </thead>
              <tbody class="text-gray-700">
                <template
                  v-if="
                    transaction.meta_data && transaction.meta_data.length > 0
                  "
                >
                  <tr
                    v-for="(inv, index) in transaction.meta_data"
                    :key="index"
                    class="border-b border-gray-100 border-dashed"
                  >
                    <td class="py-2 pl-2 whitespace-nowrap align-top">
                      {{ formatDate(inv.date) }}
                    </td>

                    <td
                      class="py-2 align-top font-mono text-indigo-700 font-bold"
                    >
                      {{ inv.invoice_no }}
                    </td>

                    <td
                      class="py-2 align-top text-gray-500"
                      v-if="printMode !== 'thermal'"
                    >
                      <p class="line-clamp-2 text-xs">
                        {{ inv.products || "General Due" }}
                      </p>
                    </td>

                    <td class="py-2 pr-2 text-right font-bold align-top">
                      {{ settings?.currency_symbol || "৳" }}
                      {{ Number(inv.amount).toLocaleString() }}
                    </td>
                  </tr>
                </template>

                <tr v-else>
                  <td colspan="4" class="py-4 text-center text-gray-400 italic">
                    General Balance Adjustment
                  </td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                <tr>
                  <td
                    :colspan="printMode === 'thermal' ? 2 : 3"
                    class="py-2 pl-2 text-right font-bold text-gray-600 uppercase text-xs"
                  >
                    Total Paid:
                  </td>
                  <td class="py-2 pr-2 text-right font-bold text-xl text-black">
                    {{ settings?.currency_symbol || "৳" }}
                    {{ Number(transaction.amount).toLocaleString() }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="flex justify-end mt-4 mb-8">
            <div
              class="bg-gray-100 p-3 rounded-lg border border-gray-200 w-64 text-right"
              :class="printMode === 'thermal' ? 'w-full' : ''"
            >
              <p class="text-xs text-gray-500 mb-1">
                Previous Dues:
                <span class="font-bold"
                  >৳
                  {{ Number(transaction.prev_balance).toLocaleString() }}</span
                >
              </p>
              <div class="border-t border-gray-300 my-1"></div>
              <p class="text-sm font-bold text-gray-800">
                Remaining Due:
                <span
                  :class="
                    Number(transaction.curr_balance) > 0
                      ? 'text-red-600'
                      : 'text-green-600'
                  "
                  >৳
                  {{ Number(transaction.curr_balance).toLocaleString() }}</span
                >
              </p>
            </div>
          </div>

          <div
            class="text-center text-gray-400 pt-4 mt-8"
            :class="printMode === 'thermal' ? 'text-[10px]' : 'text-xs mt-12'"
          >
            <p>
              {{
                settings?.invoice_footer_text || "Thank you for your business!"
              }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media print {
  body * {
    visibility: hidden;
  }
  #printable-area,
  #printable-area * {
    visibility: visible;
  }
  #printable-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    background: white;
  }
  .thermal-layout {
    width: 78mm !important;
    max-width: 78mm !important;
    padding: 0 !important;
    margin: 0 auto;
    font-family: "Courier New", Courier, monospace;
  }
}
</style>
