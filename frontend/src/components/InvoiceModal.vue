<script setup>
import { computed, ref, nextTick } from "vue";
import {
  XMarkIcon,
  PrinterIcon,
  DocumentTextIcon,
  ReceiptPercentIcon,
} from "@heroicons/vue/24/outline";

const props = defineProps({
  isOpen: Boolean,
  sale: Object,
});

const emit = defineEmits(["close"]);

// --- State ---
const printMode = ref("a4");

const subTotal = computed(() => {
  if (!props.sale?.sale_items) return 0;
  return props.sale.sale_items.reduce((sum, item) => {
    const qty = Number(item.quantity) || 0;
    const price = Number(item.unit_price) || 0;
    return sum + qty * price;
  }, 0);
});

// --- Print Actions ---
const handlePrint = async (mode) => {
  printMode.value = mode;
  await nextTick();
  window.print();
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm print:p-0 print:bg-white"
  >
    <div
      class="bg-white w-full max-w-2xl rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] print:shadow-none print:w-full print:max-w-none print:max-h-none print:overflow-visible"
    >
      <div
        class="flex justify-between items-center p-4 border-b border-gray-100 bg-gray-50 print:hidden flex-shrink-0"
      >
        <h3 class="font-bold text-gray-700">Invoice / Receipt</h3>
        <div class="flex gap-2">
          <button
            @click="handlePrint('thermal')"
            class="flex items-center gap-2 px-3 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition text-sm font-medium"
            title="Print Small Receipt"
          >
            <ReceiptPercentIcon class="w-4 h-4" /> Receipt
          </button>

          <button
            @click="handlePrint('a4')"
            class="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium"
            title="Print A4 Invoice"
          >
            <PrinterIcon class="w-4 h-4" /> Invoice
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
        class="overflow-y-auto print:overflow-visible bg-white"
        id="printable-area"
        :class="printMode === 'thermal' ? 'thermal-layout p-2' : 'p-8'"
      >
        <div
          class="flex justify-between items-start mb-6"
          :class="{
            'flex-col items-center text-center gap-2 mb-2':
              printMode === 'thermal',
          }"
        >
          <div>
            <h1
              class="font-extrabold text-indigo-600 tracking-tight"
              :class="printMode === 'thermal' ? 'text-xl' : 'text-3xl'"
            >
              SmartIMS
            </h1>
            <p
              class="text-gray-500"
              :class="printMode === 'thermal' ? 'text-[10px]' : 'text-sm mt-1'"
            >
              Authorized Dealer & Service Center
            </p>
            <p
              class="text-gray-400"
              :class="printMode === 'thermal' ? 'text-[10px]' : 'text-xs'"
            >
              Dhaka, Bangladesh | +880 1700-000000
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
              {{ printMode === "thermal" ? "CASH MEMO" : "INVOICE" }}
            </h2>
            <p
              class="text-gray-600 font-mono"
              :class="printMode === 'thermal' ? 'text-xs' : 'text-sm mt-1'"
            >
              #{{ String(sale?.id).padStart(4, "0") }}
            </p>
            <p
              class="text-gray-500"
              :class="printMode === 'thermal' ? 'text-[10px]' : 'text-xs mt-1'"
            >
              {{ new Date(sale?.created_at).toLocaleString() }}
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
          <div :class="{ 'mb-1': printMode === 'thermal' }">
            <p
              class="font-bold text-gray-400 uppercase tracking-wider mb-1"
              :class="{
                'text-[10px]': printMode === 'thermal',
                'text-xs': printMode !== 'thermal',
              }"
            >
              Bill To
            </p>
            <h4
              class="font-bold text-gray-800"
              :class="{ 'text-sm': printMode === 'thermal' }"
            >
              {{ sale?.customer?.name || "Walk-in Customer" }}
            </h4>
            <p
              class="text-gray-500"
              :class="{
                'text-[11px]': printMode === 'thermal',
                'text-sm': printMode !== 'thermal',
              }"
            >
              {{ sale?.customer?.phone || "" }}
            </p>
          </div>

          <div v-if="printMode !== 'thermal'" class="text-right">
            <p
              class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1"
            >
              Status
            </p>
            <span class="font-bold text-sm">{{ sale?.payment_status }}</span>
          </div>
        </div>

        <table
          class="w-full text-left mb-4"
          :class="{
            'text-xs': printMode === 'thermal',
            'text-sm': printMode !== 'thermal',
          }"
        >
          <thead>
            <tr
              class="border-b-2 border-gray-200 text-gray-500 uppercase"
              :class="{
                'text-[10px]': printMode === 'thermal',
                'text-xs': printMode !== 'thermal',
              }"
            >
              <th class="py-2">Item</th>
              <th class="py-2 text-center">Qty</th>
              <th class="py-2 text-right">Price</th>
              <th class="py-2 text-right">Total</th>
            </tr>
          </thead>
          <tbody class="text-gray-700">
            <tr
              v-for="(item, index) in sale?.sale_items"
              :key="index"
              class="border-b border-gray-100 border-dashed"
            >
              <td class="py-2">
                <p class="font-bold">{{ item.product?.name }}</p>
              </td>
              <td class="py-2 text-center">{{ item.quantity }}</td>
              <td class="py-2 text-right">
                {{ Number(item.unit_price).toLocaleString() }}
              </td>
              <td class="py-2 text-right font-medium">
                {{
                  (
                    Number(item.quantity) * Number(item.unit_price)
                  ).toLocaleString()
                }}
              </td>
            </tr>
          </tbody>
        </table>

        <div
          class="flex justify-end"
          :class="{ 'text-xs': printMode === 'thermal' }"
        >
          <div :class="printMode === 'thermal' ? 'w-full' : 'w-64 space-y-2'">
            <div class="flex justify-between text-gray-600 mb-1">
              <span>Subtotal:</span>
              <span>৳ {{ subTotal.toLocaleString() }}</span>
            </div>

            <div
              class="flex justify-between text-gray-600 mb-1"
              v-if="sale?.discount > 0"
            >
              <span>Discount:</span>
              <span>- ৳ {{ Number(sale?.discount).toLocaleString() }}</span>
            </div>

            <div
              class="border-t border-gray-200 border-dashed my-1 pt-1 flex justify-between font-bold text-gray-900"
              :class="printMode === 'thermal' ? 'text-sm' : 'text-lg'"
            >
              <span>Total:</span>
              <span>৳ {{ Number(sale?.grand_total).toLocaleString() }}</span>
            </div>

            <div class="flex justify-between text-green-600 font-medium mb-1">
              <span>Paid:</span>
              <span>৳ {{ Number(sale?.paid_amount).toLocaleString() }}</span>
            </div>

            <div
              v-if="sale?.due_amount > 0"
              class="flex justify-between text-red-600 font-bold"
            >
              <span>Due:</span>
              <span>৳ {{ Number(sale?.due_amount).toLocaleString() }}</span>
            </div>
          </div>
        </div>

        <div
          class="text-center text-gray-400 pt-4 border-t border-gray-100 mt-4"
          :class="printMode === 'thermal' ? 'text-[10px]' : 'text-xs mt-12'"
        >
          <p>Thank you!</p>
          <p v-if="printMode === 'thermal'">Software by SmartIMS</p>
          <p v-else>For any queries, contact support@smartims.com</p>

          <div
            v-if="printMode === 'thermal'"
            class="mt-2 flex justify-center opacity-50"
          >
            <span class="font-mono text-[10px]">||| || |||| ||| || ||||</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Standard A4 Styles are handled by Tailwind classes above.
  We specifically tune the @media print for thermal printers here.
*/

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
  }

  /* 🔥 Thermal Mode Specifics */
  .thermal-layout {
    width: 78mm !important; /* Standard Thermal Width (approx 80mm with margins) */
    max-width: 78mm !important;
    padding: 0 !important;
    margin: 0 auto;
    font-family:
      "Courier New", Courier, monospace; /* Monospace looks better on thermal */
    color: black;
  }

  /* Force black text for thermal printers */
  .thermal-layout * {
    color: #000 !important;
  }

  .thermal-layout h1,
  .thermal-layout h2,
  .thermal-layout h4 {
    font-weight: 900;
  }
}
</style>
