<script setup>
import { computed } from "vue";
import { XMarkIcon, PrinterIcon } from "@heroicons/vue/24/outline";

const props = defineProps({
  isOpen: Boolean,
  sale: Object,
});

const emit = defineEmits(["close"]);

const subTotal = computed(() => {
  if (!props.sale?.sale_items) return 0;
  return props.sale.sale_items.reduce((sum, item) => {
    const qty = Number(item.quantity) || 0;
    const price = Number(item.unit_price) || 0;
    return sum + qty * price;
  }, 0);
});

// Print Function
const printInvoice = () => {
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
        <h3 class="font-bold text-gray-700">Invoice Details</h3>
        <div class="flex gap-2">
          <button
            @click="printInvoice"
            class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium"
          >
            <PrinterIcon class="w-4 h-4" /> Print
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
        class="p-8 overflow-y-auto print:p-0 print:overflow-visible"
        id="printable-area"
      >
        <div class="flex justify-between items-start mb-8">
          <div>
            <h1 class="text-3xl font-extrabold text-indigo-600 tracking-tight">
              SmartIMS
            </h1>
            <p class="text-sm text-gray-500 mt-1">
              Authorized Dealer & Service Center
            </p>
            <p class="text-xs text-gray-400">
              Dhaka, Bangladesh | +880 1700-000000
            </p>
          </div>
          <div class="text-right">
            <h2 class="text-xl font-bold text-gray-800">INVOICE</h2>
            <p class="text-sm text-gray-600 font-mono mt-1">
              #ORD-{{ String(sale?.id).padStart(4, "0") }}
            </p>
            <p class="text-xs text-gray-500 mt-1">
              Date: {{ new Date(sale?.created_at).toLocaleDateString() }}
            </p>
          </div>
        </div>

        <div
          class="flex justify-between mb-8 bg-gray-50 p-4 rounded-lg print:bg-transparent print:p-0 print:border print:border-gray-200"
        >
          <div>
            <p
              class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1"
            >
              Bill To
            </p>
            <h4 class="font-bold text-gray-800">
              {{ sale?.customer?.name || "Walk-in Customer" }}
            </h4>
            <p class="text-sm text-gray-500">
              {{ sale?.customer?.phone || "N/A" }}
            </p>
            <p class="text-sm text-gray-500">
              {{ sale?.customer?.address || "" }}
            </p>
          </div>
          <div class="text-right">
            <p
              class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1"
            >
              Status
            </p>
            <span
              :class="`px-3 py-1 rounded-full text-xs font-bold border ${
                sale?.payment_status === 'Paid'
                  ? 'bg-green-100 text-green-700 border-green-200'
                  : sale?.payment_status === 'Due'
                    ? 'bg-red-100 text-red-700 border-red-200'
                    : 'bg-amber-100 text-amber-700 border-amber-200'
              }`"
            >
              {{ sale?.payment_status }}
            </span>
          </div>
        </div>

        <table class="w-full text-left mb-8">
          <thead>
            <tr
              class="border-b-2 border-gray-200 text-gray-500 text-xs uppercase"
            >
              <th class="py-3">Item Description</th>
              <th class="py-3 text-center">Qty</th>
              <th class="py-3 text-right">Price</th>
              <th class="py-3 text-right">Total</th>
            </tr>
          </thead>
          <tbody class="text-sm text-gray-700">
            <tr
              v-for="(item, index) in sale?.sale_items"
              :key="index"
              class="border-b border-gray-100"
            >
              <td class="py-3">
                <p class="font-bold">{{ item.product?.name }}</p>
                <p class="text-xs text-gray-400">
                  {{ item.product?.sku || "SKU-N/A" }}
                </p>
              </td>
              <td class="py-3 text-center">{{ item.quantity }}</td>
              <td class="py-3 text-right">
                {{ Number(item.unit_price || 0).toLocaleString() }}
              </td>

              <td class="py-3 text-right font-medium">
                {{
                  (
                    Number(item.quantity || 0) * Number(item.unit_price || 0)
                  ).toLocaleString()
                }}
              </td>
            </tr>
          </tbody>
        </table>

        <div class="flex justify-end">
          <div class="w-64 space-y-2">
            <div class="flex justify-between text-sm text-gray-600">
              <span>Subtotal:</span>
              <span>৳ {{ subTotal.toLocaleString() }}</span>
            </div>

            <div class="flex justify-between text-sm text-gray-600">
              <span>Discount:</span>
              <span
                >- ৳ {{ Number(sale?.discount || 0).toLocaleString() }}</span
              >
            </div>

            <div
              class="border-t border-gray-200 my-2 pt-2 flex justify-between font-bold text-lg text-gray-900"
            >
              <span>Total:</span>
              <span
                >৳ {{ Number(sale?.grand_total || 0).toLocaleString() }}</span
              >
            </div>

            <div
              class="flex justify-between text-sm text-green-600 font-medium"
            >
              <span>Paid:</span>
              <span
                >৳ {{ Number(sale?.paid_amount || 0).toLocaleString() }}</span
              >
            </div>

            <div
              v-if="sale?.due_amount > 0"
              class="flex justify-between text-sm text-red-600 font-bold"
            >
              <span>Due:</span>
              <span
                >৳ {{ Number(sale?.due_amount || 0).toLocaleString() }}</span
              >
            </div>
          </div>
        </div>

        <div
          class="mt-12 text-center text-xs text-gray-400 pt-8 border-t border-gray-100"
        >
          <p>Thank you for your business!</p>
          <p>For any queries, contact support@smartims.com</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media print {
  /* Hide everything else when printing */
  body * {
    visibility: hidden;
  }
  /* Only show the modal content */
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
    padding: 20px; /* Optional: print padding */
  }
}
</style>
