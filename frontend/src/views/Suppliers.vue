<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useRouter, useRoute } from "vue-router"; // Router Import
import axios from "../axios";
import Swal from "sweetalert2";
import SupplierFormModal from "../components/SupplierFormModal.vue"; // Modal Import
import {
  MagnifyingGlassIcon,
  TruckIcon,
  PencilSquareIcon,
  TrashIcon,
  PlusIcon,
} from "@heroicons/vue/24/outline";

const route = useRoute();
const router = useRouter();

// --- State ---
const suppliers = ref([]);
const isLoading = ref(false);
const searchQuery = ref("");

// Modal State
const showModal = ref(false);
const selectedSupplier = ref(null);

// --- 🔥 URL Watcher (Connection with Sidebar) ---
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
const fetchSuppliers = async () => {
  isLoading.value = true;
  try {
    const response = await axios.get("/suppliers");
    if (response.data.status) {
      suppliers.value = response.data.data;
    }
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const deleteSupplier = async (id) => {
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
      await axios.delete(`/suppliers/${id}`);
      Swal.fire({
        icon: "success",
        title: "Deleted!",
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 1500,
      });
      fetchSuppliers();
    } catch (error) {
      let msg = "Failed to delete.";
      if (
        error.response &&
        (error.response.status === 400 || error.response.status === 500)
      ) {
        msg = "Cannot delete: This supplier has purchase records.";
      } else if (error.response.data.message) {
        msg = error.response.data.message;
      }
      Swal.fire("Error", msg, "error");
    }
  }
};

// --- Computed Filter ---
const filteredSuppliers = computed(() => {
  if (!searchQuery.value) return suppliers.value;
  const q = searchQuery.value.toLowerCase();
  return suppliers.value.filter(
    (s) =>
      s.name.toLowerCase().includes(q) ||
      s.phone.includes(q) ||
      (s.shop_name && s.shop_name.toLowerCase().includes(q)),
  );
});

// --- Handlers ---
const openAddModal = () => {
  selectedSupplier.value = null;
  showModal.value = true;
};

const openEditModal = (s) => {
  selectedSupplier.value = { ...s };
  showModal.value = true;
};

// 🔥 Updated Modal Close: Cleans URL query
const handleModalClose = (refresh) => {
  showModal.value = false;
  selectedSupplier.value = null;

  // Remove '?action=add' from URL
  if (route.query.action) {
    router.replace({ query: null });
  }

  if (refresh) fetchSuppliers();
};

// Initial Load
onMounted(() => fetchSuppliers());
</script>

<template>
  <div class="space-y-6">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
    >
      <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
          Supplier Management
        </h2>
        <p class="text-sm text-gray-500">
          Manage your product suppliers and vendors.
        </p>
      </div>
      <button
        @click="openAddModal"
        class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-md text-sm font-bold transition transform active:scale-95"
      >
        <PlusIcon class="w-5 h-5" /> Add Supplier
      </button>
    </div>

    <div
      class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row items-center gap-4"
    >
      <div class="relative flex-1 w-full">
        <MagnifyingGlassIcon
          class="absolute left-3 top-2.5 h-5 w-5 text-gray-400"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by Name, Phone or Shop..."
          class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
        />
      </div>

      <div
        class="flex items-center gap-2 px-4 py-2 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 rounded-lg font-bold text-sm whitespace-nowrap border border-indigo-100 dark:border-indigo-800/30"
      >
        <TruckIcon class="w-5 h-5" />
        <span>Total Suppliers: {{ suppliers.length }}</span>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm overflow-hidden"
    >
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead
            class="bg-gray-50 dark:bg-slate-800/50 text-xs font-bold text-gray-500 uppercase"
          >
            <tr>
              <th class="px-6 py-4">Supplier Info</th>
              <th class="px-6 py-4">Contact</th>
              <th class="px-6 py-4">Address</th>
              <th class="px-6 py-4 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
            <tr v-if="isLoading">
              <td colspan="4" class="px-6 py-10 text-center text-gray-500">
                <div class="flex justify-center items-center gap-2">
                  <div
                    class="animate-spin rounded-full h-5 w-5 border-b-2 border-indigo-600"
                  ></div>
                  Loading...
                </div>
              </td>
            </tr>

            <tr v-else-if="filteredSuppliers.length === 0">
              <td colspan="4" class="px-6 py-10 text-center text-gray-400">
                No suppliers found matching your search.
              </td>
            </tr>

            <tr
              v-else
              v-for="supplier in filteredSuppliers"
              :key="supplier.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >
              <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                  <div
                    class="h-10 w-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-lg shadow-sm border border-indigo-200"
                  >
                    {{ supplier.name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <h4 class="font-bold text-gray-800 dark:text-white">
                      {{ supplier.name }}
                    </h4>
                    <p
                      class="text-xs text-gray-500 font-bold uppercase tracking-wide"
                    >
                      {{ supplier.shop_name || "N/A" }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                <div class="font-medium">{{ supplier.phone }}</div>
                <div class="text-xs text-gray-400">{{ supplier.email }}</div>
              </td>
              <td
                class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300 truncate max-w-xs"
              >
                {{ supplier.address || "-" }}
              </td>
              <td class="px-6 py-3 text-center">
                <div class="flex justify-center gap-2">
                  <button
                    @click="openEditModal(supplier)"
                    class="p-1.5 text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition"
                    title="Edit"
                  >
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteSupplier(supplier.id)"
                    class="p-1.5 text-red-600 bg-red-50 rounded hover:bg-red-100 transition"
                    title="Delete"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <SupplierFormModal
      :isOpen="showModal"
      :supplier="selectedSupplier"
      @close="handleModalClose"
    />
  </div>
</template>
