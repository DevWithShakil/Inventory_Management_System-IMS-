<script setup>
import { ref, computed, watch } from "vue";
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionChild,
  TransitionRoot,
} from "@headlessui/vue";
import { XMarkIcon, ArrowPathIcon } from "@heroicons/vue/24/outline";
import axios from "../axios";
import Swal from "sweetalert2";

const props = defineProps({
  isOpen: Boolean,
  sale: Object,
});

const emit = defineEmits(["close", "success"]);

const isLoading = ref(false);
const returnItems = ref([]);
const note = ref("");
const deductionAmount = ref(0);

// Initialize form data when sale changes
watch(
  () => props.sale,
  (newSale) => {
    if (newSale && newSale.sale_items) {
      // 🔥 Calculate Discount Ratio (কত পারসেন্ট ডিসকাউন্ট পেয়েছিল)
      // Formula: (Subtotal - (Grand Total - Tax)) / Subtotal
      // অথবা সহজভাবে: Total Discount / Subtotal

      // এখানে আমরা বের করছি ১ টাকার প্রোডাক্টে সে আসলে কত টাকা পেমেন্ট করেছে
      // Paid Ratio = (Grand Total - Tax) / Subtotal (Tax আলাদা হ্যান্ডেল করা ভালো, তবে সহজ করার জন্য আমরা গ্রস রেশিও নিচ্ছি)

      let totalDiscount = Number(newSale.discount || 0); // Coupon + Other Discounts
      // যদি পয়েন্ট ভ্যালু আলাদা ফিল্ডে থাকে তবে যোগ করবেন, না থাকলে grand_total এর লজিক ব্যবহার করব

      const subtotal = newSale.sale_items.reduce(
        (sum, item) => sum + item.unit_price * item.quantity,
        0,
      );

      // ডিসকাউন্ট রেশিও (প্রতি ১ টাকার প্রোডাক্টে কত টাকা ডিসকাউন্ট)
      const discountRatio = subtotal > 0 ? totalDiscount / subtotal : 0;

      returnItems.value = newSale.sale_items.map((item) => {
        const originalPrice = Number(item.unit_price);
        // 🔥 আসল কেনা দাম (Discount বাদ দিয়ে)
        const effectivePrice = originalPrice - originalPrice * discountRatio;

        return {
          product_id: item.product_id,
          product_name: item.product?.name || "Unknown Product",
          original_price: originalPrice,
          unit_price: parseFloat(effectivePrice.toFixed(2)), // Refundable Price (Auto Calculated)
          purchased_qty: item.quantity,
          return_qty: 0,
        };
      });

      note.value = "";
      deductionAmount.value = 0;
    }
  },
  { immediate: true },
);

// Calculate Total Refund Amount
const totalRefundAmount = computed(() => {
  const itemTotal = returnItems.value.reduce((sum, item) => {
    return sum + item.return_qty * item.unit_price;
  }, 0);
  return Math.max(itemTotal - deductionAmount.value, 0);
});

// Submit Return Request
const submitReturn = async () => {
  const itemsToReturn = returnItems.value
    .filter((item) => item.return_qty > 0)
    .map((item) => ({
      product_id: item.product_id,
      quantity: item.return_qty,
      unit_price: item.unit_price, // Sending the discounted price
    }));

  if (itemsToReturn.length === 0) {
    Swal.fire("Error", "Please select at least one item to return.", "warning");
    return;
  }

  isLoading.value = true;
  try {
    const payload = {
      sale_id: props.sale.id,
      date: new Date().toISOString().split("T")[0],
      items: itemsToReturn,
      deduction_amount: deductionAmount.value,
      note: note.value,
    };

    const response = await axios.post("/sales-returns", payload);

    if (response.data.status) {
      Swal.fire({
        icon: "success",
        title: "Return Processed!",
        text: response.data.message, // Backend will say if points restored
        timer: 2000,
        showConfirmButton: false,
      });
      emit("success");
      emit("close");
    }
  } catch (error) {
    console.error(error);
    Swal.fire(
      "Error",
      error.response?.data?.message || "Failed to process return.",
      "error",
    );
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <TransitionRoot as="template" :show="isOpen">
    <Dialog as="div" class="relative z-50" @close="$emit('close')">
      <TransitionChild
        as="template"
        enter="ease-out duration-300"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="ease-in duration-200"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div
          class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        />
      </TransitionChild>

      <div class="fixed inset-0 z-10 overflow-y-auto">
        <div
          class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0"
        >
          <DialogPanel
            class="relative transform overflow-hidden rounded-lg bg-white dark:bg-slate-900 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl"
          >
            <div
              class="bg-gray-50 dark:bg-slate-800 px-4 py-3 sm:px-6 flex justify-between items-center border-b border-gray-200 dark:border-slate-700"
            >
              <DialogTitle
                as="h3"
                class="text-base font-semibold leading-6 text-gray-900 dark:text-white flex items-center gap-2"
              >
                <ArrowPathIcon class="h-5 w-5 text-indigo-600" /> Return
                Products (Invoice #{{ sale?.id }})
              </DialogTitle>
              <button
                @click="$emit('close')"
                class="text-gray-400 hover:text-gray-500"
              >
                <XMarkIcon class="h-6 w-6" />
              </button>
            </div>

            <div class="px-4 py-5 sm:p-6">
              <div
                class="mb-4 p-3 bg-blue-50 text-blue-700 text-xs rounded border border-blue-100"
              >
                ℹ️ <b>Note:</b> Return prices are auto-adjusted based on
                discounts/coupons applied during sale.
              </div>

              <div class="space-y-4">
                <div
                  class="overflow-x-auto border rounded-lg border-gray-200 dark:border-slate-700"
                >
                  <table
                    class="min-w-full divide-y divide-gray-200 dark:divide-slate-700"
                  >
                    <thead class="bg-gray-50 dark:bg-slate-800">
                      <tr>
                        <th
                          class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                        >
                          Product
                        </th>
                        <th
                          class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase"
                        >
                          Sold Price
                        </th>
                        <th
                          class="px-4 py-2 text-right text-xs font-medium text-indigo-600 uppercase"
                        >
                          Return Price
                        </th>
                        <th
                          class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase"
                        >
                          Qty
                        </th>
                        <th
                          class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase"
                        >
                          Return
                        </th>
                      </tr>
                    </thead>
                    <tbody
                      class="divide-y divide-gray-200 dark:divide-slate-700 bg-white dark:bg-slate-900"
                    >
                      <tr v-for="(item, index) in returnItems" :key="index">
                        <td
                          class="px-4 py-2 text-sm text-gray-900 dark:text-white"
                        >
                          {{ item.product_name }}
                        </td>
                        <td
                          class="px-4 py-2 text-sm text-right text-gray-400 line-through"
                        >
                          ৳{{ item.original_price }}
                        </td>
                        <td
                          class="px-4 py-2 text-sm text-right font-bold text-gray-800 dark:text-white"
                        >
                          ৳{{ item.unit_price }}
                        </td>
                        <td class="px-4 py-2 text-sm text-center text-gray-600">
                          {{ item.purchased_qty }}
                        </td>
                        <td class="px-4 py-2 text-center">
                          <input
                            type="number"
                            v-model.number="item.return_qty"
                            min="0"
                            :max="item.purchased_qty"
                            class="w-16 px-2 py-1 text-sm border rounded text-center focus:ring-indigo-500 dark:bg-slate-800 dark:border-slate-600"
                          />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div
                  class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t dark:border-slate-700"
                >
                  <div>
                    <label
                      class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                      >Reason / Note</label
                    >
                    <textarea
                      v-model="note"
                      rows="2"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-slate-800 dark:border-slate-600"
                      placeholder="Why returning?"
                    ></textarea>
                  </div>
                  <div class="space-y-2">
                    <div class="flex justify-between items-center">
                      <label class="text-sm text-gray-600 dark:text-gray-400"
                        >Deduction (৳)</label
                      >
                      <input
                        type="number"
                        v-model.number="deductionAmount"
                        class="w-24 px-2 py-1 text-right text-sm border rounded dark:bg-slate-800 dark:border-slate-600"
                      />
                    </div>
                    <div
                      class="flex justify-between items-center text-lg font-bold text-indigo-600 dark:text-indigo-400 border-t pt-2 dark:border-slate-700"
                    >
                      <span>Refund Amount:</span>
                      <span>৳ {{ totalRefundAmount.toLocaleString() }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div
              class="bg-gray-50 dark:bg-slate-800 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6"
            >
              <button
                type="button"
                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto disabled:opacity-50"
                @click="submitReturn"
                :disabled="isLoading || totalRefundAmount <= 0"
              >
                <span v-if="isLoading" class="animate-spin mr-2">⏳</span>
                Confirm Return
              </button>
              <button
                type="button"
                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-slate-700 dark:text-white dark:ring-slate-600"
                @click="$emit('close')"
              >
                Cancel
              </button>
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
