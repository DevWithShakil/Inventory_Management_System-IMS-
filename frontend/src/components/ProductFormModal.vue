<script setup>
import { ref, watch, onMounted } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import { XMarkIcon, PhotoIcon, ArrowPathIcon } from "@heroicons/vue/24/outline";

const props = defineProps({
  isOpen: Boolean,
  product: Object,
});

const emit = defineEmits(["close"]);

// --- State ---
const form = ref({
  name: "",
  sku: "",
  category_id: "",
  brand_id: "",
  unit_id: "",
  purchase_price: 0,
  selling_price: 0,
  stock_quantity: 0,
  alert_quantity: 5,
  description: "",
  image: null,
});

const previewImage = ref(null);
const isSubmitting = ref(false);
const categories = ref([]);
const brands = ref([]);
const units = ref([]);

const fetchAttributes = async () => {
  try {
    const [catRes, brandRes, unitRes] = await Promise.all([
      axios.get("/categories"),
      axios.get("/brands"),
      axios.get("/units"),
    ]);
    if (catRes.data.status) categories.value = catRes.data.data;
    if (brandRes.data.status) brands.value = brandRes.data.data;
    if (unitRes.data.status) units.value = unitRes.data.data;
  } catch (error) {
    console.error("Error fetching attributes", error);
  }
};

function generateSKU() {
  return "PRD-" + Math.random().toString(36).substr(2, 8).toUpperCase();
}

const resetForm = () => {
  form.value = {
    name: "",
    sku: generateSKU(),
    category_id: "",
    brand_id: "",
    unit_id: "",
    purchase_price: 0,
    selling_price: 0,
    stock_quantity: 0,
    alert_quantity: 5,
    description: "",
    image: null,
  };
  previewImage.value = null;
};

watch(
  () => props.product,
  (newVal) => {
    if (newVal) {
      // Edit Mode
      form.value = {
        name: newVal.name,
        sku: newVal.sku,
        category_id: newVal.category_id,
        brand_id: newVal.brand_id,
        unit_id: newVal.unit_id,
        purchase_price: newVal.cost_price || newVal.purchase_price || 0,
        selling_price: newVal.selling_price,
        stock_quantity: newVal.stock_quantity,
        alert_quantity: newVal.alert_quantity,
        description: newVal.description,
        image: null,
      };
      previewImage.value = newVal.image
        ? newVal.image.startsWith("http")
          ? newVal.image
          : `http://localhost:8000/storage/${newVal.image}`
        : null;
    } else {
      // Create Mode
      resetForm();
    }
  },
  { immediate: true },
);

const handleFileChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    form.value.image = file;
    previewImage.value = URL.createObjectURL(file);
  }
};

const handleSubmit = async () => {
  if (!form.value.name || !form.value.selling_price) {
    Swal.fire({
      icon: "error",
      title: "Missing Data",
      text: "Name and Selling Price are required!",
    });
    return;
  }

  isSubmitting.value = true;
  const formData = new FormData();

  Object.keys(form.value).forEach((key) => {
    const value = form.value[key];

    // Image logic
    if (key === "image") {
      if (value instanceof File) formData.append(key, value);
      return;
    }

    if (
      (key === "category_id" || key === "brand_id" || key === "unit_id") &&
      !value
    ) {
      return;
    }

    if (value !== null && value !== undefined) {
      formData.append(key, value);
    }
  });

  if (props.product) {
    formData.append("_method", "PUT");
  }

  try {
    let url = props.product ? `/products/${props.product.id}` : "/products";
    const response = await axios.post(url, formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    if (response.data.status) {
      Swal.fire({
        icon: "success",
        title: "Success!",
        toast: true,
        position: "top-end",
        timer: 1500,
        showConfirmButton: false,
      });
      emit("close", true);
    }
  } catch (error) {
    console.error("Submission Error:", error);
    let msg = "Failed to save product.";
    if (error.response && error.response.data.message)
      msg = error.response.data.message;
    Swal.fire({ icon: "error", title: "Error", text: msg });
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(() => {
  fetchAttributes();
});
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm overflow-y-auto"
  >
    <div
      class="bg-white dark:bg-slate-900 w-full max-w-4xl rounded-2xl shadow-2xl animate-fade-in-up my-8"
    >
      <div
        class="flex justify-between items-center p-5 border-b border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/50 rounded-t-2xl"
      >
        <h3 class="font-bold text-xl text-gray-800 dark:text-white">
          {{ product ? "Edit Product" : "Add New Product" }}
        </h3>
        <button
          @click="$emit('close', false)"
          class="p-2 text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-700 rounded-full transition"
        >
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <form
        @submit.prevent="handleSubmit"
        class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6"
      >
        <div class="md:col-span-1 space-y-4">
          <div class="relative group">
            <div
              class="w-full h-64 bg-gray-100 dark:bg-slate-800 border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-xl flex flex-col items-center justify-center overflow-hidden cursor-pointer hover:border-indigo-500 transition"
            >
              <img
                v-if="previewImage"
                :src="previewImage"
                class="w-full h-full object-cover"
              />
              <div
                v-else
                class="flex flex-col items-center text-gray-400 p-4 text-center"
              >
                <PhotoIcon class="w-12 h-12 mb-2" /><span class="text-xs"
                  >Upload Image</span
                >
              </div>
              <input
                type="file"
                accept="image/*"
                @change="handleFileChange"
                class="absolute inset-0 opacity-0 cursor-pointer"
              />
            </div>
          </div>
          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >SKU</label
            >
            <div class="flex gap-2">
              <input
                v-model="form.sku"
                type="text"
                class="flex-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm"
              />
              <button
                type="button"
                @click="form.sku = generateSKU()"
                class="p-2 bg-gray-100 rounded-lg"
              >
                <ArrowPathIcon class="w-5 h-5" />
              </button>
            </div>
          </div>
          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Unit</label
            >
            <select
              v-model="form.unit_id"
              class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm"
            >
              <option value="">Select Unit</option>
              <option v-for="u in units" :key="u.id" :value="u.id">
                {{ u.name }}
              </option>
            </select>
          </div>
        </div>

        <div class="md:col-span-2 space-y-4">
          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Name *</label
            ><input
              v-model="form.name"
              type="text"
              class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm"
              required
            />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label
                class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
                >Category</label
              ><select
                v-model="form.category_id"
                class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm"
              >
                <option value="">Select Category</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">
                  {{ c.name }}
                </option>
              </select>
            </div>
            <div>
              <label
                class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
                >Brand</label
              ><select
                v-model="form.brand_id"
                class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm"
              >
                <option value="">Select Brand</option>
                <option v-for="b in brands" :key="b.id" :value="b.id">
                  {{ b.name }}
                </option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label
                class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
                >Purchase Cost</label
              ><input
                v-model="form.purchase_price"
                type="number"
                class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm"
              />
            </div>
            <div>
              <label
                class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
                >Selling Price *</label
              ><input
                v-model="form.selling_price"
                type="number"
                class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm"
                required
              />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label
                class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
                >Current Stock</label
              ><input
                v-model="form.stock_quantity"
                type="number"
                class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm"
              />
            </div>
            <div>
              <label
                class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
                >Alert Quantity</label
              ><input
                v-model="form.alert_quantity"
                type="number"
                class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm"
              />
            </div>
          </div>
          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Description</label
            ><textarea
              v-model="form.description"
              rows="3"
              class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm"
            ></textarea>
          </div>
        </div>
      </form>
      <div
        class="p-5 border-t border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/50 flex justify-end gap-3 rounded-b-2xl"
      >
        <button
          @click="$emit('close', false)"
          class="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition"
        >
          Cancel
        </button>
        <button
          @click="handleSubmit"
          :disabled="isSubmitting"
          class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-lg"
        >
          {{ product ? "Update" : "Save" }}
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
