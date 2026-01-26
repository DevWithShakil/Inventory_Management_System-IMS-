<script setup>
import { ref, onMounted, computed, watch } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import {
  TagIcon,
  SwatchIcon,
  ScaleIcon,
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
} from "@heroicons/vue/24/outline";

// --- State ---
const activeTab = ref("categories");
const items = ref([]);
const isLoading = ref(false);
const showModal = ref(false);
const isSubmitting = ref(false);

// Form State
const form = ref({
  id: null,
  name: "",
  short_name: "",
});

// --- Configuration ---
const tabConfig = computed(() => ({
  categories: {
    title: "Category",
    api: "/categories",
    icon: TagIcon,
    placeholder: "Mobile, Laptop, etc.",
  },
  brands: {
    title: "Brand",
    api: "/brands",
    icon: SwatchIcon,
    placeholder: "Samsung, Apple, etc.",
  },
  units: {
    title: "Unit",
    api: "/units",
    icon: ScaleIcon,
    placeholder: "Piece, Kg, Litre, etc.",
  },
}));

const currentConfig = computed(() => tabConfig.value[activeTab.value]);

// --- API Actions ---

// 1. Fetch Data
const fetchData = async () => {
  isLoading.value = true;
  items.value = [];
  try {
    const response = await axios.get(currentConfig.value.api);
    if (response.data.status) {
      items.value = response.data.data;
    }
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

// 2. Save Data (Create / Update)
const handleSubmit = async () => {
  if (!form.value.name) {
    Swal.fire("Error", "Name is required!", "error");
    return;
  }

  isSubmitting.value = true;
  try {
    const url = form.value.id
      ? `${currentConfig.value.api}/${form.value.id}`
      : currentConfig.value.api;

    const method = form.value.id ? "put" : "post";

    await axios[method](url, form.value);

    Swal.fire({
      icon: "success",
      title: "Saved!",
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 1500,
    });
    closeModal();
    fetchData();
  } catch (error) {
    let msg = "Failed to save.";
    if (error.response && error.response.data.message)
      msg = error.response.data.message;
    Swal.fire("Error", msg, "error");
  } finally {
    isSubmitting.value = false;
  }
};

// 3. Delete Data
const deleteItem = async (id) => {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "You cannot revert this!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, delete it!",
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`${currentConfig.value.api}/${id}`);
      Swal.fire({
        icon: "success",
        title: "Deleted!",
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 1500,
      });
      fetchData();
    } catch (error) {
      // Foreign Key Error Handle
      let msg = "Failed to delete.";
      if (
        error.response &&
        (error.response.status === 400 || error.response.status === 500)
      ) {
        msg = "Cannot delete this item as it is used in products.";
      }
      Swal.fire("Error", msg, "error");
    }
  }
};

// --- Handlers ---
const openAddModal = () => {
  form.value = { id: null, name: "", short_name: "" };
  showModal.value = true;
};

const openEditModal = (item) => {
  form.value = {
    id: item.id,
    name: item.name,
    short_name: item.short_name || "",
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

// Watch Tab Change
watch(activeTab, () => {
  fetchData();
});

onMounted(() => fetchData());
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
          Attributes & Catalog
        </h2>
        <p class="text-sm text-gray-500">
          Manage your product categories, brands and units.
        </p>
      </div>
    </div>

    <div
      class="flex flex-wrap gap-2 border-b border-gray-200 dark:border-slate-700 pb-1"
    >
      <button
        v-for="(config, key) in tabConfig"
        :key="key"
        @click="activeTab = key"
        :class="[
          'flex items-center gap-2 px-5 py-2.5 rounded-t-lg font-medium transition text-sm',
          activeTab === key
            ? 'bg-indigo-600 text-white shadow-md'
            : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700',
        ]"
      >
        <component :is="config.icon" class="w-5 h-5" />
        {{ config.title }}s
      </button>
    </div>

    <div
      class="bg-white dark:bg-slate-900 rounded-b-xl rounded-tr-xl border border-gray-200 dark:border-slate-800 shadow-sm p-6 min-h-[400px]"
    >
      <div class="flex justify-between items-center mb-6">
        <h3
          class="text-lg font-bold text-gray-700 dark:text-white flex items-center gap-2"
        >
          Manage {{ currentConfig.title }}s
          <span
            class="px-2 py-0.5 bg-gray-100 dark:bg-slate-700 rounded-full text-xs text-gray-500"
            >{{ items.length }}</span
          >
        </h3>
        <button
          @click="openAddModal"
          class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-sm text-sm font-bold transition transform active:scale-95"
        >
          <PlusIcon class="w-5 h-5" /> Add {{ currentConfig.title }}
        </button>
      </div>

      <div v-if="isLoading" class="flex justify-center py-10">
        <div
          class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"
        ></div>
      </div>

      <div
        v-else
        class="overflow-hidden rounded-lg border border-gray-100 dark:border-slate-700"
      >
        <table class="w-full text-left border-collapse">
          <thead
            class="bg-gray-50 dark:bg-slate-800/50 text-xs font-bold text-gray-500 uppercase"
          >
            <tr>
              <th class="px-6 py-3">ID</th>
              <th class="px-6 py-3">Name</th>
              <th v-if="activeTab === 'units'" class="px-6 py-3">Short Name</th>
              <th class="px-6 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
            <tr v-if="items.length === 0">
              <td
                colspan="4"
                class="px-6 py-8 text-center text-gray-400 text-sm"
              >
                No {{ activeTab }} found.
              </td>
            </tr>
            <tr
              v-for="(item, index) in items"
              :key="item.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/50"
            >
              <td class="px-6 py-3 text-sm text-gray-500 font-mono">
                #{{ index + 1 }}
              </td>
              <td class="px-6 py-3 font-medium text-gray-800 dark:text-white">
                {{ item.name }}
              </td>
              <td
                v-if="activeTab === 'units'"
                class="px-6 py-3 text-sm text-gray-600"
              >
                {{ item.short_name || "-" }}
              </td>
              <td class="px-6 py-3 text-right">
                <div class="flex justify-end gap-2">
                  <button
                    @click="openEditModal(item)"
                    class="p-1.5 text-blue-600 bg-blue-50 rounded hover:bg-blue-100"
                  >
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteItem(item.id)"
                    class="p-1.5 text-red-600 bg-red-50 rounded hover:bg-red-100"
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

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
    >
      <div
        class="bg-white dark:bg-slate-900 w-full max-w-sm rounded-2xl shadow-xl animate-fade-in-up"
      >
        <div
          class="flex justify-between items-center p-5 border-b border-gray-100 dark:border-slate-800"
        >
          <h3 class="font-bold text-lg text-gray-800 dark:text-white">
            {{ form.id ? "Edit" : "Add" }} {{ currentConfig.title }}
          </h3>
          <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
            ✕
          </button>
        </div>
        <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Name</label
            >
            <input
              v-model="form.name"
              type="text"
              class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
              :placeholder="currentConfig.placeholder"
              required
            />
          </div>

          <div v-if="activeTab === 'units'">
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Short Name (Symbol)</label
            >
            <input
              v-model="form.short_name"
              type="text"
              class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
              placeholder="e.g. kg, pc"
            />
          </div>

          <div class="pt-2 flex justify-end gap-2">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
            >
              {{ isSubmitting ? "Saving..." : "Save" }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
