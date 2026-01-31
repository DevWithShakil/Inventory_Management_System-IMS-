<script setup>
import { ref, reactive, nextTick, watch } from "vue";
import axios from "../axios";
import JsBarcode from "jsbarcode";
import {
  MagnifyingGlassIcon,
  PrinterIcon,
  TrashIcon,
  PlusIcon,
  MinusIcon,
  Cog6ToothIcon,
} from "@heroicons/vue/24/outline";

// --- State ---
const searchQuery = ref("");
const searchResults = ref([]);
const selectedProducts = ref([]);
const isPrinting = ref(false);

// Settings
const config = reactive({
  showPrice: true,
  showName: true,
  shopName: "SmartIMS",
  labelWidth: 40,
  labelHeight: 25,
  gap: 2,
  barcodeType: "CODE128",
});

// --- Search Products ---
const searchProducts = async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    return;
  }
  try {
    const response = await axios.get(`/products?search=${searchQuery.value}`);
    if (response.data.status) {
      searchResults.value = response.data.data.data || response.data.data;
    }
  } catch (error) {
    console.error(error);
  }
};

// --- Add to List ---
const addProduct = (product) => {
  const exists = selectedProducts.value.find((p) => p.id === product.id);
  if (exists) {
    exists.printQty++;
  } else {
    selectedProducts.value.push({
      ...product,
      printQty: 1,
      barcodeValue:
        product.code || product.sku || String(product.id).padStart(6, "0"),
    });
  }
  searchQuery.value = "";
  searchResults.value = [];
};

// --- Remove ---
const removeProduct = (index) => {
  selectedProducts.value.splice(index, 1);
};

const renderBarcodes = () => {
  nextTick(() => {
    JsBarcode(".barcode-svg").init();
  });
};

watch(selectedProducts, () => renderBarcodes(), { deep: true });

// --- Print Action ---
const handlePrint = () => {
  renderBarcodes();
  window.print();
};
</script>

<template>
  <div
    class="flex flex-col h-screen overflow-hidden bg-gray-50 dark:bg-slate-900"
  >
    <div
      class="bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 p-4 flex justify-between items-center print:hidden"
    >
      <div>
        <h2
          class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2"
        >
          <span class="p-2 bg-indigo-100 text-indigo-600 rounded-lg">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
              class="w-6 h-6"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"
              />
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z"
              />
            </svg>
          </span>
          Barcode Generator
        </h2>
      </div>
      <button
        @click="handlePrint"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-bold flex items-center gap-2 shadow-lg transition"
      >
        <PrinterIcon class="w-5 h-5" /> Print Labels
      </button>
    </div>

    <div class="flex flex-1 overflow-hidden">
      <div
        class="w-1/3 bg-white dark:bg-slate-800 border-r border-gray-200 dark:border-slate-700 p-4 overflow-y-auto print:hidden flex flex-col gap-6"
      >
        <div class="relative">
          <label
            class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2"
            >Find Product</label
          >
          <div class="relative">
            <MagnifyingGlassIcon
              class="w-5 h-5 absolute left-3 top-3 text-gray-400"
            />
            <input
              v-model="searchQuery"
              @input="searchProducts"
              type="text"
              class="w-full pl-10 p-2.5 border rounded-lg bg-gray-50 dark:bg-slate-700 dark:border-slate-600 focus:ring-2 focus:ring-indigo-500 outline-none"
              placeholder="Scan barcode or type name..."
            />
          </div>

          <div
            v-if="searchResults.length > 0"
            class="absolute z-10 w-full bg-white dark:bg-slate-700 shadow-xl rounded-lg mt-1 max-h-60 overflow-y-auto border border-gray-100"
          >
            <div
              v-for="prod in searchResults"
              :key="prod.id"
              @click="addProduct(prod)"
              class="p-3 hover:bg-indigo-50 dark:hover:bg-slate-600 cursor-pointer border-b border-gray-100 dark:border-slate-600 last:border-0"
            >
              <p class="font-bold text-sm">{{ prod.name }}</p>
              <div
                class="flex justify-between text-xs text-gray-500 dark:text-gray-300 mt-1"
              >
                <span>Code: {{ prod.code }}</span>
                <span>Stock: {{ prod.stock_quantity }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-50 dark:bg-slate-700/50 p-4 rounded-xl space-y-3">
          <h3
            class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
          >
            <Cog6ToothIcon class="w-4 h-4" /> Label Settings
          </h3>

          <div class="flex items-center gap-2">
            <input
              type="checkbox"
              v-model="config.showName"
              id="chkName"
              class="w-4 h-4 text-indigo-600 rounded"
            />
            <label for="chkName" class="text-sm">Show Product Name</label>
          </div>

          <div class="flex items-center gap-2">
            <input
              type="checkbox"
              v-model="config.showPrice"
              id="chkPrice"
              class="w-4 h-4 text-indigo-600 rounded"
            />
            <label for="chkPrice" class="text-sm">Show Price</label>
          </div>

          <div>
            <label class="text-xs font-bold text-gray-500">Shop Name</label>
            <input
              type="text"
              v-model="config.shopName"
              class="w-full p-1.5 text-sm border rounded mt-1"
            />
          </div>
        </div>

        <div class="flex-1">
          <h3 class="font-bold text-gray-700 dark:text-gray-300 mb-3">
            Print Queue ({{ selectedProducts.length }})
          </h3>
          <div class="space-y-2">
            <div
              v-if="selectedProducts.length === 0"
              class="text-center text-gray-400 py-4 text-sm italic"
            >
              No products added to queue.
            </div>
            <div
              v-for="(item, idx) in selectedProducts"
              :key="idx"
              class="bg-white dark:bg-slate-700 p-3 rounded-lg border border-gray-200 dark:border-slate-600 shadow-sm flex justify-between items-center"
            >
              <div class="flex-1 min-w-0 pr-2">
                <p class="font-bold text-sm truncate">{{ item.name }}</p>
                <p class="text-xs text-gray-500">{{ item.barcodeValue }}</p>
              </div>
              <div class="flex items-center gap-2">
                <input
                  type="number"
                  v-model="item.printQty"
                  class="w-16 p-1 text-center border rounded text-sm font-bold"
                  min="1"
                />
                <button
                  @click="removeProduct(idx)"
                  class="text-red-500 hover:bg-red-50 p-1.5 rounded-full"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div
        class="flex-1 bg-gray-200 dark:bg-slate-900 p-8 overflow-y-auto flex justify-center"
      >
        <div
          id="print-area"
          class="bg-white shadow-2xl p-4 min-h-[500px] w-full max-w-[210mm]"
        >
          <div class="flex flex-wrap gap-2 content-start">
            <template v-for="item in selectedProducts" :key="item.id">
              <div
                v-for="n in item.printQty"
                :key="n"
                class="barcode-label border border-gray-200 rounded flex flex-col items-center justify-center text-center overflow-hidden relative"
                :style="{
                  width: '40mm', // Standard Thermal Label Width
                  height: '25mm', // Standard Thermal Label Height
                  padding: '2mm',
                }"
              >
                <p
                  class="text-[8px] font-bold uppercase tracking-wide truncate w-full"
                >
                  {{ config.shopName }}
                </p>

                <p
                  v-if="config.showName"
                  class="text-[9px] leading-tight truncate w-full px-1 my-0.5"
                >
                  {{ item.name.substring(0, 20) }}
                </p>

                <svg
                  class="barcode-svg"
                  :jsbarcode-value="item.barcodeValue"
                  jsbarcode-format="CODE128"
                  jsbarcode-width="1.5"
                  jsbarcode-height="25"
                  jsbarcode-displayValue="true"
                  jsbarcode-fontSize="10"
                  jsbarcode-margin="0"
                ></svg>

                <p
                  v-if="config.showPrice"
                  class="text-[10px] font-extrabold mt-0.5"
                >
                  Tk. {{ Math.round(item.selling_price) }}
                </p>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
@media print {
  body * {
    visibility: hidden;
    overflow: hidden;
  }

  #print-area,
  #print-area * {
    visibility: visible;
    overflow: visible;
  }

  #print-area {
    position: fixed;
    left: 0;
    top: 0;
    width: 100vw;
    min-height: 100vh;
    z-index: 9999;
    background: white;
    margin: 0;
    padding: 5mm;

    display: flex !important;
    flex-wrap: wrap !important;
    align-content: flex-start !important;
    gap: 2mm;
  }

  .barcode-label {
    break-inside: avoid;
    page-break-inside: avoid;
    border: 1px solid #ddd;
  }
  ::-webkit-scrollbar {
    display: none;
  }
}
</style>

<style scoped>
.barcode-svg {
  max-width: 100%;
  height: auto;
}
</style>
