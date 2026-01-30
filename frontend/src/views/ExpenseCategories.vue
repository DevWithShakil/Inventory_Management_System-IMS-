<script setup>
import { ref, onMounted } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import {
  TagIcon,
  PencilSquareIcon,
  TrashIcon,
  PlusIcon,
  ArrowLeftIcon,
} from "@heroicons/vue/24/outline";
import { useRouter } from "vue-router";

const router = useRouter();
const categories = ref([]);
const loading = ref(false);
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const form = ref({ name: "" });

const fetchCategories = async () => {
  loading.value = true;
  try {
    const res = await axios.get("/expense-categories");
    if (res.data.status) categories.value = res.data.data;
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const saveCategory = async () => {
  if (!form.value.name) return;
  try {
    let res;
    if (isEditing.value) {
      res = await axios.put(
        `/expense-categories/${editingId.value}`,
        form.value,
      );
    } else {
      res = await axios.post("/expense-categories", form.value);
    }

    if (res.data.status) {
      Swal.fire({
        icon: "success",
        title: "Saved!",
        toast: true,
        position: "top-end",
        timer: 1500,
        showConfirmButton: false,
      });
      closeModal();
      fetchCategories();
    }
  } catch (error) {
    Swal.fire("Error", error.response?.data?.message || "Failed", "error");
  }
};

const deleteCategory = async (id) => {
  const result = await Swal.fire({
    title: "Delete?",
    text: "Cannot delete if used in expenses.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
  });
  if (result.isConfirmed) {
    try {
      await axios.delete(`/expense-categories/${id}`);
      Swal.fire("Deleted!", "Category deleted.", "success");
      fetchCategories();
    } catch (error) {
      Swal.fire(
        "Error",
        error.response?.data?.message || "Cannot delete.",
        "error",
      );
    }
  }
};

const openEdit = (cat) => {
  isEditing.value = true;
  editingId.value = cat.id;
  form.value.name = cat.name;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  isEditing.value = false;
  form.value.name = "";
};

onMounted(() => fetchCategories());
</script>

<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button
          @click="router.back()"
          class="p-2 hover:bg-gray-100 rounded-full"
        >
          <ArrowLeftIcon class="w-5 h-5" />
        </button>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
          Expense Categories
        </h2>
      </div>
      <button
        @click="showModal = true"
        class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold text-sm"
      >
        <PlusIcon class="w-5 h-5" /> Add Category
      </button>
    </div>

    <div
      class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-200 dark:border-slate-800 overflow-hidden"
    >
      <table class="w-full text-left">
        <thead
          class="bg-gray-50 dark:bg-slate-800 text-xs uppercase text-gray-500 font-bold"
        >
          <tr>
            <th class="px-6 py-4">Name</th>
            <th class="px-6 py-4 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-slate-800 text-sm">
          <tr v-for="cat in categories" :key="cat.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 font-medium text-gray-700 dark:text-gray-300">
              {{ cat.name }}
            </td>
            <td class="px-6 py-4 text-center flex justify-center gap-2">
              <button
                @click="openEdit(cat)"
                class="p-1.5 text-blue-600 bg-blue-50 rounded"
              >
                <PencilSquareIcon class="w-4 h-4" />
              </button>
              <button
                @click="deleteCategory(cat.id)"
                class="p-1.5 text-red-600 bg-red-50 rounded"
              >
                <TrashIcon class="w-4 h-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
      <div class="bg-white p-6 rounded-xl shadow-lg w-96">
        <h3 class="text-lg font-bold mb-4">
          {{ isEditing ? "Edit Category" : "New Category" }}
        </h3>
        <input
          v-model="form.name"
          class="w-full p-2 border rounded mb-4"
          placeholder="Category Name"
        />
        <div class="flex justify-end gap-2">
          <button
            @click="closeModal"
            class="px-4 py-2 text-sm font-bold text-gray-500"
          >
            Cancel
          </button>
          <button
            @click="saveCategory"
            class="px-4 py-2 text-sm font-bold bg-indigo-600 text-white rounded"
          >
            Save
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
