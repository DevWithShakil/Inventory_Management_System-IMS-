<script setup>
import { ref, computed, watch } from "vue";
import {
  XMarkIcon,
  BanknotesIcon,
  CreditCardIcon,
  DevicePhoneMobileIcon,
  CheckCircleIcon,
} from "@heroicons/vue/24/outline";

const props = defineProps({
  isOpen: Boolean,
  totalAmount: Number,
  customer: Object,
});

const emit = defineEmits(["close", "submit-payment"]);

// --- State ---
const paymentMethod = ref("cash"); // default
const receivedAmount = ref(0);
const note = ref("");
const isSubmitting = ref(false);

// --- Watchers ---
// মডাল ওপেন হলে Received Amount অটোমেটিক Total Amount এর সমান করে দেওয়া (সুবিধার জন্য)
watch(
  () => props.isOpen,
  (newVal) => {
    if (newVal) {
      receivedAmount.value = props.totalAmount;
      paymentMethod.value = "cash";
      note.value = "";
    }
  },
);

// --- Computeds ---
const changeAmount = computed(() => {
  const change = receivedAmount.value - props.totalAmount;
  return change > 0 ? change : 0;
});

const dueAmount = computed(() => {
  const due = props.totalAmount - receivedAmount.value;
  return due > 0 ? due : 0;
});

// --- Actions ---
const handleConfirm = () => {
  if (receivedAmount.value < 0) return;

  isSubmitting.value = true;

  // প্যারেন্ট কম্পোনেন্টকে ডাটা পাঠানো
  emit("submit-payment", {
    payment_method: paymentMethod.value,
    received_amount: receivedAmount.value,
    change_amount: changeAmount.value,
    due_amount: dueAmount.value, // যদি পার্শিয়াল পেমেন্ট সিস্টেম থাকে
    note: note.value,
  });

  // লোডিং রিসেট হবে প্যারেন্ট থেকে সাকসেস হলে, অথবা ম্যানুয়ালি:
  setTimeout(() => (isSubmitting.value = false), 1000);
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
  >
    <div
      class="bg-white dark:bg-slate-900 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden animate-fade-in-up"
    >
      <div
        class="flex justify-between items-center p-5 border-b border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/50"
      >
        <div>
          <h3 class="font-bold text-xl text-gray-800 dark:text-white">
            Complete Payment
          </h3>
          <p class="text-sm text-gray-500">
            Invoice for {{ customer?.name || "Walk-in Customer" }}
          </p>
        </div>
        <button
          @click="$emit('close')"
          class="p-2 text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-700 rounded-full transition"
        >
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <div class="p-6 space-y-6">
        <div
          class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-xl text-center border border-indigo-100 dark:border-indigo-800"
        >
          <p
            class="text-sm font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wider"
          >
            Total Payable
          </p>
          <h2
            class="text-4xl font-extrabold text-indigo-700 dark:text-indigo-300 mt-1"
          >
            ৳ {{ Number(totalAmount).toLocaleString() }}
          </h2>
        </div>

        <div>
          <label
            class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2"
            >Payment Method</label
          >
          <div class="grid grid-cols-3 gap-3">
            <button
              @click="paymentMethod = 'cash'"
              :class="`flex flex-col items-center justify-center gap-2 p-3 rounded-xl border transition ${
                paymentMethod === 'cash'
                  ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300 dark:ring-indigo-900'
                  : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700'
              }`"
            >
              <BanknotesIcon class="w-6 h-6" />
              <span class="text-xs font-bold">Cash</span>
            </button>

            <button
              @click="paymentMethod = 'card'"
              :class="`flex flex-col items-center justify-center gap-2 p-3 rounded-xl border transition ${
                paymentMethod === 'card'
                  ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300'
                  : 'bg-white dark:bg-slate-800 text-gray-600 border-gray-200 hover:bg-gray-50'
              }`"
            >
              <CreditCardIcon class="w-6 h-6" />
              <span class="text-xs font-bold">Card</span>
            </button>

            <button
              @click="paymentMethod = 'mobile_bank'"
              :class="`flex flex-col items-center justify-center gap-2 p-3 rounded-xl border transition ${
                paymentMethod === 'mobile_bank'
                  ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300'
                  : 'bg-white dark:bg-slate-800 text-gray-600 border-gray-200 hover:bg-gray-50'
              }`"
            >
              <DevicePhoneMobileIcon class="w-6 h-6" />
              <span class="text-xs font-bold">MFS (Bkash)</span>
            </button>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Received Amount</label
            >
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 font-bold"
                >৳</span
              >
              <input
                v-model.number="receivedAmount"
                type="number"
                class="w-full pl-8 pr-4 py-2 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-gray-900 dark:text-white"
                @focus="$event.target.select()"
              />
            </div>
          </div>

          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
            >
              {{ changeAmount > 0 ? "Change / Return" : "Due Amount" }}
            </label>
            <div
              :class="`w-full px-4 py-2 rounded-lg border font-bold text-lg flex items-center justify-between ${
                changeAmount > 0
                  ? 'bg-green-50 text-green-700 border-green-200'
                  : dueAmount > 0
                    ? 'bg-red-50 text-red-700 border-red-200'
                    : 'bg-gray-100 text-gray-500'
              }`"
            >
              <span>৳</span>
              <span>{{
                changeAmount > 0
                  ? changeAmount.toLocaleString()
                  : dueAmount.toLocaleString()
              }}</span>
            </div>
          </div>
        </div>

        <div>
          <label
            class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1"
            >Sale Note (Optional)</label
          >
          <textarea
            v-model="note"
            rows="2"
            placeholder="Type any reference note..."
            class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"
          ></textarea>
        </div>
      </div>

      <div
        class="p-5 border-t border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/50 flex gap-3"
      >
        <button
          @click="$emit('close')"
          class="flex-1 px-4 py-3 bg-white border border-gray-300 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition"
        >
          Cancel
        </button>
        <button
          @click="handleConfirm"
          :disabled="isSubmitting"
          class="flex-[2] px-4 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-lg hover:shadow-indigo-500/30 transition flex justify-center items-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed"
        >
          <span
            v-if="isSubmitting"
            class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"
          ></span>
          <span v-else class="flex items-center gap-2">
            <CheckCircleIcon class="w-5 h-5" /> Confirm Payment
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Simple enter animation */
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
