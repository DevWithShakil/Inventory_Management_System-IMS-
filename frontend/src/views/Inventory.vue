<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import axios from "../axios";
import Swal from "sweetalert2";
import ProductFormModal from "../components/ProductFormModal.vue";
import {
  MagnifyingGlassIcon,
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  FunnelIcon,
  ArrowDownTrayIcon,
  ExclamationTriangleIcon,
  Squares2X2Icon,
  DocumentArrowUpIcon,
} from "@heroicons/vue/24/outline";

const route = useRoute();
const router = useRouter();

// --- State ---
const products = ref([]);
const categories = ref([]);
const isLoading = ref(false);

// Import State
const showImportModal = ref(false);
const selectedFile = ref(null);
const uploading = ref(false);
const fileInput = ref(null);

// Filters State
const filters = ref({
  search: "",
  category_id: "",
  stock_status: "",
});

// Modal State
const showProductModal = ref(false);
const selectedProduct = ref(null);

// ---  URL Watcher (Connection with Sidebar) ---
watch(
  () => route.query.action,
  (newAction) => {
    if (newAction === "add") {
      openAddModal();
    }
  },
  { immediate: true },
);

// --- API Actions ---
const fetchProducts = async () => {
  isLoading.value = true;
  try {
    const response = await axios.get("/products");
    if (response.data.status) {
      products.value = response.data.data;
    }
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const fetchCategories = async () => {
  try {
    const response = await axios.get("/categories");
    if (response.data.status) categories.value = response.data.data;
  } catch (error) {
    console.error(error);
  }
};

const deleteProduct = async (id) => {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "You won't be able to revert this!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, delete it!",
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/products/${id}`);
      Swal.fire({
        icon: "success",
        title: "Deleted!",
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 1500,
      });
      fetchProducts();
    } catch (error) {
      console.error(error);
      let msg = "Something went wrong.";
      if (
        error.response &&
        error.response.data &&
        error.response.data.message
      ) {
        msg = error.response.data.message;
      }
      Swal.fire("Failed!", msg, "error");
    }
  }
};

// --- Import Logic ---
const openImportModal = () => {
  showImportModal.value = true;
  selectedFile.value = null;
  if (fileInput.value) fileInput.value.value = ""; // Reset input
};

const handleFileSelect = (event) => {
  selectedFile.value = event.target.files[0];
};

const uploadFile = async () => {
  if (!selectedFile.value) return;

  uploading.value = true;
  const formData = new FormData();
  formData.append("file", selectedFile.value);

  try {
    const response = await axios.post("/products/import", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    if (response.data.status) {
      Swal.fire("Success", "Products imported successfully!", "success");
      showImportModal.value = false;
      fetchProducts(); // Refresh list
    }
  } catch (error) {
    Swal.fire(
      "Error",
      "Failed to import. Check file format or duplicates.",
      "error",
    );
    console.error(error);
  } finally {
    uploading.value = false;
  }
};

// --- Filtering Logic ---
const filteredProducts = computed(() => {
  return products.value.filter((product) => {
    const searchLower = filters.value.search.toLowerCase();
    const matchesSearch =
      product.name.toLowerCase().includes(searchLower) ||
      (product.sku && product.sku.toLowerCase().includes(searchLower)) ||
      (product.brand?.name &&
        product.brand.name.toLowerCase().includes(searchLower));

    const matchesCategory = filters.value.category_id
      ? product.category_id == filters.value.category_id
      : true;

    let matchesStock = true;
    const stock = product.stock_quantity || 0;
    const alertQty = product.alert_quantity || 5;

    if (filters.value.stock_status === "low") {
      matchesStock = stock <= alertQty && stock > 0;
    } else if (filters.value.stock_status === "out") {
      matchesStock = stock <= 0;
    }

    return matchesSearch && matchesCategory && matchesStock;
  });
});

// --- Handlers ---
const openAddModal = () => {
  selectedProduct.value = null;
  showProductModal.value = true;
};

const openEditModal = (product) => {
  selectedProduct.value = { ...product };
  showProductModal.value = true;
};

// Updated Modal Close: Cleans URL query
const handleModalClose = (shouldRefresh) => {
  showProductModal.value = false;
  selectedProduct.value = null;

  // Remove '?action=add' from URL so it can be triggered again later
  if (route.query.action) {
    router.replace({ query: null });
  }

  if (shouldRefresh) {
    fetchProducts();
  }
};

onMounted(() => {
  fetchProducts();
  fetchCategories();
});
</script>

<template>
  <div class="space-y-6 pb-10">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
    >
      <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
          Inventory Management
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Track stock, manage products and pricing.
        </p>
      </div>
      <div class="flex gap-3">
        <button
          @click="openImportModal"
          class="hidden sm:flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition shadow-md hover:shadow-lg text-sm font-bold"
        >
          <DocumentArrowUpIcon class="w-5 h-5" /> Import Excel
        </button>

        <button
          class="hidden sm:flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm text-sm font-medium"
        >
          <ArrowDownTrayIcon class="w-5 h-5" /> Export
        </button>

        <button
          @click="openAddModal"
          class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-md hover:shadow-lg transition transform active:scale-95 text-sm font-bold"
        >
          <PlusIcon class="w-5 h-5" /> Add Product
        </button>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-4"
    >
      <div class="md:col-span-2 relative">
        <MagnifyingGlassIcon
          class="absolute left-3 top-2.5 h-5 w-5 text-gray-400"
        />
        <input
          v-model="filters.search"
          type="text"
          placeholder="Search by Name, SKU or Brand..."
          class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
        />
      </div>
      <div class="relative">
        <Squares2X2Icon class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
        <select
          v-model="filters.category_id"
          class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none focus:ring-2 focus:ring-indigo-500 appearance-none cursor-pointer"
        >
          <option value="">All Categories</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">
            {{ cat.name }}
          </option>
        </select>
      </div>
      <div class="relative">
        <FunnelIcon class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
        <select
          v-model="filters.stock_status"
          class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none focus:ring-2 focus:ring-indigo-500 appearance-none cursor-pointer"
        >
          <option value="">All Stock Status</option>
          <option value="low">Low Stock</option>
          <option value="out">Out of Stock</option>
        </select>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm overflow-hidden"
    >
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr
              class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-800 text-xs font-bold text-gray-500 uppercase tracking-wider"
            >
              <th class="px-6 py-4">Product</th>
              <th class="px-6 py-4">Category</th>
              <th class="px-6 py-4 text-right">Cost / Price</th>
              <th class="px-6 py-4 text-center">Stock</th>
              <th class="px-6 py-4 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
            <tr v-if="isLoading">
              <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                <div class="flex justify-center items-center gap-2">
                  <div
                    class="animate-spin rounded-full h-5 w-5 border-b-2 border-indigo-600"
                  ></div>
                  Loading...
                </div>
              </td>
            </tr>
            <tr
              v-else
              v-for="product in filteredProducts"
              :key="product.id"
              class="group hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >
              <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                  <div
                    class="h-12 w-12 rounded-lg bg-gray-100 dark:bg-slate-700 flex-shrink-0 overflow-hidden border border-gray-200"
                  >
                    <img
                      v-if="product.image"
                      :src="
                        product.image.startsWith('http')
                          ? product.image
                          : `http://localhost:8000/storage/${product.image}`
                      "
                      class="h-full w-full object-cover"
                      @error="
                        $event.target.src = 'https://placehold.co/100?text=IMG'
                      "
                    />
                    <div
                      v-else
                      class="h-full w-full flex items-center justify-center text-gray-400 text-xs"
                    >
                      No Img
                    </div>
                  </div>
                  <div>
                    <h4 class="text-sm font-bold text-gray-800 dark:text-white">
                      {{ product.name }}
                    </h4>
                    <p class="text-xs text-gray-500 font-mono">
                      {{ product.sku || "No SKU" }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                {{ product.category?.name || "Uncategorized" }}
                <span class="block text-xs text-gray-400">{{
                  product.brand?.name
                }}</span>
              </td>
              <td class="px-6 py-3 text-right">
                <div class="text-sm font-bold text-gray-900 dark:text-white">
                  ৳
                  {{ Number(product.selling_price || 0).toLocaleString() }}
                </div>
                <div class="text-xs text-gray-500">
                  Cost:
                  {{ Number(product.purchase_price || 0).toLocaleString() }}
                </div>
              </td>
              <td class="px-6 py-3 text-center">
                <div class="flex flex-col items-center">
                  <span
                    :class="`px-2.5 py-0.5 rounded-full text-xs font-bold border ${(product.stock_quantity || 0) <= (product.alert_quantity || 5) ? 'bg-red-100 text-red-700 border-red-200' : 'bg-green-100 text-green-700 border-green-200'}`"
                  >
                    {{ product.stock_quantity || 0 }}
                    {{ product.unit?.short_name || "pcs" }}
                  </span>
                  <span
                    v-if="
                      (product.stock_quantity || 0) <=
                      (product.alert_quantity || 5)
                    "
                    class="text-[10px] text-red-500 flex items-center gap-1 mt-1 font-medium"
                  >
                    <ExclamationTriangleIcon class="w-3 h-3" /> Low Stock
                  </span>
                </div>
              </td>
              <td class="px-6 py-3 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button
                    @click="openEditModal(product)"
                    class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition"
                    title="Edit"
                  >
                    <PencilSquareIcon class="w-5 h-5" />
                  </button>
                  <button
                    @click="deleteProduct(product.id)"
                    class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                    title="Delete"
                  >
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!isLoading && filteredProducts.length === 0">
              <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                <div class="flex flex-col items-center">
                  <Squares2X2Icon class="w-12 h-12 opacity-20 mb-2" />
                  <p>No products found matching your filters.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <ProductFormModal
      :isOpen="showProductModal"
      :product="selectedProduct"
      @close="handleModalClose"
    />

    <div
      v-if="showImportModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
      <div
        class="bg-white dark:bg-slate-800 w-full max-w-md rounded-2xl shadow-2xl p-6"
      >
        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">
          Import Products
        </h3>

        <div class="space-y-4">
          <div
            class="border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg p-6 text-center"
          >
            <input
              type="file"
              ref="fileInput"
              accept=".xlsx,.csv"
              class="hidden"
              @change="handleFileSelect"
            />
            <button
              @click="$refs.fileInput.click()"
              class="text-indigo-600 font-medium hover:underline"
            >
              Click to upload
            </button>
            <p class="text-xs text-gray-500 mt-2" v-if="!selectedFile">
              Select an .xlsx or .csv file
            </p>
            <p
              class="text-sm font-bold text-gray-800 dark:text-gray-200 mt-2"
              v-else
            >
              {{ selectedFile.name }}
            </p>
          </div>

          <div class="text-xs text-gray-500">
            <p class="font-bold">Excel Columns Format:</p>
            <p>
              name, sku, category, brand, unit, cost_price, selling_price,
              stock, alert_quantity
            </p>
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <button
            @click="showImportModal = false"
            class="px-4 py-2 text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-700 rounded-lg"
          >
            Cancel
          </button>
          <button
            @click="uploadFile"
            :disabled="!selectedFile || uploading"
            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
          >
            <span
              v-if="uploading"
              class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"
            ></span>
            {{ uploading ? "Importing..." : "Upload & Import" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
