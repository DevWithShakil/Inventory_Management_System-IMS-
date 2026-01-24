<script setup>
import { ref, watch } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import { XMarkIcon } from "@heroicons/vue/24/outline";

const props = defineProps({
  isOpen: Boolean,
  customer: Object,
});

const emit = defineEmits(["close"]);

const form = ref({
  name: "",
  phone: "",
  email: "",
  address: "",
  points: 0,
});

const isSubmitting = ref(false);

const resetForm = () => {
  form.value = { name: "", phone: "", email: "", address: "", points: 0 };
};

watch(
  () => props.customer,
  (newVal) => {
    if (newVal) {
      form.value = { ...newVal };
    } else {
      resetForm();
    }
  },
  { immediate: true },
);

const handleSubmit = async () => {
  if (!form.value.name || !form.value.phone) {
    Swal.fire("Error", "Name and Phone are required", "error");
    return;
  }

  isSubmitting.value = true;
  try {
    let url = props.customer ? `/customers/${props.customer.id}` : "/customers";
    let method = props.customer ? "put" : "post";

    await axios[method](url, form.value);

    Swal.fire({
      icon: "success",
      title: "Success",
      toast: true,
      position: "top-end",
      timer: 1500,
      showConfirmButton: false,
    });
    emit("close", true);
  } catch (error) {
    console.error(error);
    let msg = "Failed to save.";
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
      class="bg-white dark:bg-slate-900 w-full max-w-md rounded-2xl shadow-xl animate-fade-in-up"
    >
      <div
        class="flex justify-between items-center p-5 border-b border-gray-100 dark:border-slate-800"
      >
        <h3 class="font-bold text-lg text-gray-800 dark:text-white">
          {{ customer ? "Edit Customer" : "Add Customer" }}
        </h3>
        <button
          @click="$emit('close', false)"
          class="text-gray-400 hover:text-gray-600"
        >
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1"
            >Name *</label
          >
          <input
            v-model="form.name"
            type="text"
            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            required
          />
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1"
            >Phone *</label
          >
          <input
            v-model="form.phone"
            type="text"
            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            required
          />
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1"
            >Email</label
          >
          <input
            v-model="form.email"
            type="email"
            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
          />
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1"
            >Address</label
          >
          <textarea
            v-model="form.address"
            rows="2"
            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
          ></textarea>
        </div>

        <div class="pt-2 flex justify-end gap-2">
          <button
            type="button"
            @click="$emit('close', false)"
            class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="isSubmitting"
            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
          >
            {{ isSubmitting ? "Saving..." : "Save Customer" }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
