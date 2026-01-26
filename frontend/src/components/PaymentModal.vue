<script setup>
import { ref, computed, watch, nextTick } from "vue";
import {
  XMarkIcon,
  BanknotesIcon,
  CreditCardIcon,
  DevicePhoneMobileIcon,
  CheckCircleIcon,
  ArrowRightCircleIcon, // নতুন আইকন
} from "@heroicons/vue/24/outline";

const props = defineProps({
  isOpen: Boolean,
  totalAmount: Number,
  customer: Object,
});

const emit = defineEmits(["close", "submit-payment"]);

// --- State ---
const paymentMethod = ref("cash");
const receivedAmount = ref(0);
const note = ref("");
const amountInput = ref(null);

// --- Watcher: Payment Method Change ---
// কার্ড বা MFS সিলেক্ট করলে অটোমেটিক পুরো অ্যামাউন্ট সেট হবে এবং এডিট করা যাবে না
watch(paymentMethod, (newMethod) => {
  if (newMethod !== "cash") {
    receivedAmount.value = props.totalAmount;
  }
});

// --- Watcher: Reset when modal opens ---
watch(
  () => props.isOpen,
  (newVal) => {
    if (newVal) {
      receivedAmount.value = props.totalAmount;
      paymentMethod.value = "cash";
      note.value = "";

      nextTick(() => {
        if (amountInput.value) amountInput.value.select();
      });
    }
  },
);

// --- Computeds ---
const changeAmount = computed(() => {
  if (paymentMethod.value !== "cash") return 0; // অনলাইন পেমেন্টে চেঞ্জ নেই
  const change = receivedAmount.value - props.totalAmount;
  return change > 0 ? change : 0;
});

const dueAmount = computed(() => {
  if (paymentMethod.value !== "cash") return 0; // অনলাইন পেমেন্টে ডিউ নেই
  const due = props.totalAmount - receivedAmount.value;
  return due > 0 ? due : 0;
});

// --- Action ---
const handleConfirm = () => {
  if (receivedAmount.value < 0) return;

  emit("submit-payment", {
    payment_method: paymentMethod.value,
    received_amount: receivedAmount.value,
    change_amount: changeAmount.value,
    due_amount: dueAmount.value,
    note: note.value,
  });
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
            Invoice for
            <span class="font-bold text-indigo-600">{{
              customer?.name || "Walk-in Customer"
            }}</span>
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
          class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-xl text-center border border-indigo-100 dark:border-indigo-800/30"
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
              :class="`flex flex-col items-center justify-center gap-2 p-3 rounded-xl border transition active:scale-95 ${
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
              :class="`flex flex-col items-center justify-center gap-2 p-3 rounded-xl border transition active:scale-95 ${
                paymentMethod === 'card'
                  ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300'
                  : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700'
              }`"
            >
              <CreditCardIcon class="w-6 h-6" />
              <span class="text-xs font-bold">Card</span>
            </button>

            <button
              @click="paymentMethod = 'mobile_bank'"
              :class="`flex flex-col items-center justify-center gap-2 p-3 rounded-xl border transition active:scale-95 ${
                paymentMethod === 'mobile_bank'
                  ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300'
                  : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700'
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
            >
              {{
                paymentMethod === "cash" ? "Received Amount" : "Payable Amount"
              }}
            </label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 font-bold"
                >৳</span
              >
              <input
                ref="amountInput"
                v-model.number="receivedAmount"
                type="number"
                :disabled="paymentMethod !== 'cash'"
                class="w-full pl-8 pr-4 py-2 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-gray-900 dark:text-white disabled:bg-gray-100 disabled:text-gray-500"
                @focus="$event.target.select()"
                @keyup.enter="handleConfirm"
              />
            </div>
          </div>

          <div v-if="paymentMethod === 'cash'">
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
            >
              {{ changeAmount > 0 ? "Change / Return" : "Due Amount" }}
            </label>
            <div
              :class="`w-full px-4 py-2 rounded-lg border font-bold text-lg flex items-center justify-between ${
                changeAmount > 0
                  ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border-green-200 dark:border-green-800'
                  : dueAmount > 0
                    ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800'
                    : 'bg-gray-100 dark:bg-slate-800 text-gray-500 border-gray-200 dark:border-slate-700'
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

          <div
            v-else
            class="flex items-center text-xs text-gray-500 bg-gray-50 dark:bg-slate-800 p-2 rounded border border-gray-200 dark:border-slate-700"
          >
            You will be redirected to SSLCommerz gateway to complete the
            payment.
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
            class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
          ></textarea>
        </div>
      </div>

      <div
        class="p-5 border-t border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/50 flex gap-3"
      >
        <button
          @click="$emit('close')"
          class="flex-1 px-4 py-3 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition"
        >
          Cancel
        </button>
        <button
          @click="handleConfirm"
          class="flex-[2] px-4 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-lg hover:shadow-indigo-500/30 transition flex justify-center items-center gap-2 transform active:scale-95"
        >
          <component
            :is="
              paymentMethod === 'cash' ? CheckCircleIcon : ArrowRightCircleIcon
            "
            class="w-5 h-5"
          />
          {{ paymentMethod === "cash" ? "Confirm Payment" : "Pay Online" }}
        </button>
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
