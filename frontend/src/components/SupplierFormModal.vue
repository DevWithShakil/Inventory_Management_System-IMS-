<script setup>
import { ref, watch } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import { XMarkIcon } from "@heroicons/vue/24/outline";

const props = defineProps({
  isOpen: Boolean,
  supplier: Object, // Edit এর জন্য ডাটা আসবে, Add এর জন্য null
});

const emit = defineEmits(["close"]);

const isSubmitting = ref(false);

const form = ref({
  id: null,
  name: "",
  phone: "",
  email: "",
  address: "",
  shop_name: "",
});

// Watcher: মডাল ওপেন হলে বা supplier prop চেঞ্জ হলে ফর্ম আপডেট হবে
watch(
  () => props.supplier,
  (newVal) => {
    if (newVal) {
      form.value = { ...newVal }; // Edit Mode
    } else {
      // Add Mode (Reset Form)
      form.value = {
        id: null,
        name: "",
        phone: "",
        email: "",
        address: "",
        shop_name: "",
      };
    }
  },
  { immediate: true },
);

const handleSubmit = async () => {
  if (!form.value.name || !form.value.phone) {
    Swal.fire("Error", "Name and Phone are required!", "error");
    return;
  }

  isSubmitting.value = true;
  try {
    const url = form.value.id ? `/suppliers/${form.value.id}` : "/suppliers";
    const method = form.value.id ? "put" : "post";

    await axios[method](url, form.value);

    Swal.fire({
      icon: "success",
      title: "Saved!",
      toast: true,
      position: "top-end",
      timer: 1500,
      showConfirmButton: false,
    });

    emit("close", true); // true means refresh data
  } catch (error) {
    console.error(error);
    let msg = "Failed to save supplier.";
    if (error.response && error.response.data.message)
      msg = error.response.data.message;
    Swal.fire("Error", msg, "error");
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
  >
    <div
      class="bg-white dark:bg-slate-900 w-full max-w-md rounded-2xl shadow-xl animate-fade-in-up border border-gray-100 dark:border-slate-800"
    >
      <div
        class="flex justify-between items-center p-5 border-b border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/50 rounded-t-2xl"
      >
        <h3 class="font-bold text-lg text-gray-800 dark:text-white">
          {{ form.id ? "Edit" : "Add" }} Supplier
        </h3>
        <button
          @click="$emit('close', false)"
          class="text-gray-400 hover:text-red-500 transition p-1 rounded-full hover:bg-gray-200 dark:hover:bg-slate-700"
        >
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div>
          <label
            class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
            >Name *</label
          >
          <input
            v-model="form.name"
            type="text"
            class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition"
            required
          />
        </div>
        <div>
          <label
            class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
            >Shop Name</label
          >
          <input
            v-model="form.shop_name"
            type="text"
            class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition"
          />
        </div>
        <div>
          <label
            class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
            >Phone *</label
          >
          <input
            v-model="form.phone"
            type="text"
            class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition"
            required
          />
        </div>
        <div>
          <label
            class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
            >Email</label
          >
          <input
            v-model="form.email"
            type="email"
            class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition"
          />
        </div>
        <div>
          <label
            class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
            >Address</label
          >
          <textarea
            v-model="form.address"
            rows="2"
            class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition"
          ></textarea>
        </div>

        <div
          class="pt-4 flex justify-end gap-3 border-t border-gray-100 dark:border-slate-800 mt-2"
        >
          <button
            type="button"
            @click="$emit('close', false)"
            class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 font-medium transition"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="isSubmitting"
            class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 shadow-lg font-bold transition disabled:opacity-50"
          >
            {{ isSubmitting ? "Saving..." : "Save Supplier" }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
