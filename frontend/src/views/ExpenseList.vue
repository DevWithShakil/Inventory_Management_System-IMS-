<script setup>
import { ref, onMounted, computed, watch } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import { useRouter } from "vue-router";
import {
  BanknotesIcon,
  PlusIcon,
  MagnifyingGlassIcon,
  TrashIcon,
  TagIcon,
  DocumentTextIcon,
  XMarkIcon,
  ArrowPathIcon,
  PencilSquareIcon,
  PaperClipIcon,
  PhotoIcon,
  Cog6ToothIcon,
} from "@heroicons/vue/24/outline";

const router = useRouter();

// --- State ---
const expenses = ref([]);
const categories = ref([]);
const loading = ref(false);
const submitting = ref(false);

// Modal States
const showModal = ref(false);
const showCategoryModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

// User Role
const user = JSON.parse(localStorage.getItem("user") || "{}");
const isAdmin = user.value?.role === "admin" || user.role === "admin";

// Filters
const filters = ref({
  search: "",
  start_date: "",
  end_date: "",
  category_id: "",
});

// Pagination
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
});

// Main Form (Expense)
const form = ref({
  expense_category_id: "",
  date: new Date().toISOString().substr(0, 10), // Today
  amount: "",
  reference_no: "",
  description: "",
  attachment: null, // For File
});

const attachmentPreview = ref(null);

// Category Form (Quick Add)
const categoryForm = ref({ name: "" });

// --- Helpers ---
const getImageUrl = (path) => {
  if (!path) return null;
  return `http://localhost:8000/storage/${path}`;
};

// --- API Actions ---

// 1. Fetch Expenses
const fetchExpenses = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      search: filters.value.search,
      start_date: filters.value.start_date,
      end_date: filters.value.end_date,
      category_id: filters.value.category_id,
    };

    const response = await axios.get("/expenses", { params });
    if (response.data.status) {
      expenses.value = response.data.data.data;
      pagination.value = {
        current_page: response.data.data.current_page,
        last_page: response.data.data.last_page,
        total: response.data.data.total,
      };
    }
  } catch (error) {
    console.error("Error fetching expenses:", error);
  } finally {
    loading.value = false;
  }
};

// 2. Fetch Categories
const fetchCategories = async () => {
  try {
    const response = await axios.get("/expense-categories");
    if (response.data.status) {
      categories.value = response.data.data;
    }
  } catch (error) {
    console.error("Error fetching categories:", error);
  }
};

// 3. File Handler
const handleFileChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    // Validate size (e.g., max 2MB)
    if (file.size > 2 * 1024 * 1024) {
      Swal.fire("Error", "File size must be less than 2MB", "error");
      return;
    }
    form.value.attachment = file;
    // Create Preview
    const reader = new FileReader();
    reader.onload = (e) => {
      attachmentPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
};

// 4. Save Expense (FormData for File Upload)
const saveExpense = async () => {
  if (
    !form.value.expense_category_id ||
    !form.value.amount ||
    !form.value.date
  ) {
    Swal.fire("Error", "Please fill in all required fields", "error");
    return;
  }

  submitting.value = true;
  try {
    const formData = new FormData();
    formData.append("expense_category_id", form.value.expense_category_id);
    formData.append("date", form.value.date);
    formData.append("amount", form.value.amount);
    formData.append("reference_no", form.value.reference_no || "");
    formData.append("description", form.value.description || "");

    // Only append attachment if it's a new file
    if (form.value.attachment instanceof File) {
      formData.append("attachment", form.value.attachment);
    }

    let response;

    if (isEditing.value) {
      // Laravel PUT with files requires POST + _method
      formData.append("_method", "PUT");
      response = await axios.post(`/expenses/${editingId.value}`, formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    } else {
      response = await axios.post("/expenses", formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    }

    if (response.data.status) {
      Swal.fire({
        icon: "success",
        title: isEditing.value ? "Updated!" : "Saved!",
        toast: true,
        position: "top-end",
        timer: 2000,
        showConfirmButton: false,
      });
      closeModal();
      fetchExpenses();
    }
  } catch (error) {
    Swal.fire(
      "Error",
      error.response?.data?.message || "Failed to process",
      "error",
    );
  } finally {
    submitting.value = false;
  }
};

// 5. Save New Category (Quick Add)
const saveCategory = async () => {
  if (!categoryForm.value.name) return;
  try {
    const res = await axios.post("/expense-categories", categoryForm.value);
    if (res.data.status) {
      Swal.fire({
        icon: "success",
        title: "Category Created",
        toast: true,
        position: "top-end",
        timer: 1500,
        showConfirmButton: false,
      });
      fetchCategories();
      categoryForm.value.name = "";
      showCategoryModal.value = false;
    }
  } catch (error) {
    Swal.fire("Error", "Failed to create category", "error");
  }
};

// 6. Delete Expense
const deleteExpense = async (id) => {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "This cannot be undone!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, delete it!",
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/expenses/${id}`);
      Swal.fire({
        icon: "success",
        title: "Deleted!",
        toast: true,
        position: "top-end",
        timer: 1500,
        showConfirmButton: false,
      });
      fetchExpenses();
    } catch (error) {
      Swal.fire("Error", "Failed to delete.", "error");
    }
  }
};

// --- Helper Functions ---

const openEditModal = (expense) => {
  isEditing.value = true;
  editingId.value = expense.id;

  form.value = {
    expense_category_id: expense.expense_category_id,
    date: expense.date,
    amount: expense.amount,
    reference_no: expense.reference_no,
    description: expense.description,
    attachment: expense.attachment, // Store old path
  };

  if (expense.attachment) {
    attachmentPreview.value = getImageUrl(expense.attachment);
  } else {
    attachmentPreview.value = null;
  }

  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  isEditing.value = false;
  editingId.value = null;
  attachmentPreview.value = null;

  form.value = {
    expense_category_id: "",
    date: new Date().toISOString().substr(0, 10),
    amount: "",
    reference_no: "",
    description: "",
    attachment: null,
  };
};

const resetFilters = () => {
  filters.value = { search: "", start_date: "", end_date: "", category_id: "" };
  fetchExpenses();
};

const totalInView = computed(() => {
  return expenses.value.reduce((sum, item) => sum + Number(item.amount), 0);
});

// Watch Filters
watch(
  filters,
  () => {
    fetchExpenses(1);
  },
  { deep: true },
);

onMounted(() => {
  fetchExpenses();
  fetchCategories();
});
</script>

<template>
  <div class="space-y-6">
    <div
      class="flex flex-col md:flex-row md:items-center justify-between gap-4"
    >
      <div>
        <h2
          class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2"
        >
          <BanknotesIcon class="w-8 h-8 text-indigo-600" />
          Expense Management
        </h2>
        <p class="text-sm text-gray-500 mt-1">
          Track and manage your business expenses.
        </p>
      </div>
      <div class="flex gap-2">
        <div
          class="hidden md:flex flex-col items-end px-4 border-r border-gray-300 mr-2"
        >
          <span class="text-xs text-gray-500 uppercase font-bold"
            >Total in View</span
          >
          <span class="text-xl font-bold text-rose-600"
            >৳ {{ totalInView.toLocaleString() }}</span
          >
        </div>

        <button
          v-if="isAdmin"
          @click="router.push('/expense-categories')"
          class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg shadow-sm font-bold text-sm"
        >
          <Cog6ToothIcon class="w-5 h-5" /> Manage Categories
        </button>

        <button
          @click="showModal = true"
          class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-md transition transform active:scale-95 font-bold"
        >
          <PlusIcon class="w-5 h-5" /> Add Expense
        </button>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm"
    >
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="relative">
          <MagnifyingGlassIcon
            class="absolute left-3 top-3 h-5 w-5 text-gray-400"
          />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Search description or ref..."
            class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none"
          />
        </div>

        <div class="relative">
          <TagIcon class="absolute left-3 top-3 h-5 w-5 text-gray-400" />
          <select
            v-model="filters.category_id"
            class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none appearance-none"
          >
            <option value="">All Categories</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
              {{ cat.name }}
            </option>
          </select>
        </div>

        <div class="flex gap-2">
          <input
            v-model="filters.start_date"
            type="date"
            class="w-full px-3 py-2.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none"
          />
          <input
            v-model="filters.end_date"
            type="date"
            class="w-full px-3 py-2.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none"
          />
        </div>

        <button
          @click="resetFilters"
          class="flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-300 text-gray-600 hover:bg-gray-100 rounded-lg transition font-medium text-sm ml-3"
        >
          <ArrowPathIcon class="w-4 h-4" /> Reset
        </button>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm overflow-hidden"
    >
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr
              class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-800 text-xs font-bold text-gray-500 uppercase"
            >
              <th class="px-6 py-4">Date</th>
              <th class="px-6 py-4">Reference</th>
              <th class="px-6 py-4">Category</th>
              <th class="px-6 py-4">Description</th>
              <th class="px-6 py-4 text-right">Amount</th>
              <th class="px-6 py-4 text-center">Added By</th>
              <th class="px-6 py-4 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800 text-sm">
            <tr v-if="loading">
              <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                <div class="flex justify-center items-center gap-2">
                  <div
                    class="animate-spin rounded-full h-5 w-5 border-b-2 border-indigo-600"
                  ></div>
                  Loading...
                </div>
              </td>
            </tr>

            <tr v-else-if="expenses.length === 0">
              <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                <div class="flex flex-col items-center">
                  <DocumentTextIcon class="w-10 h-10 mb-2 opacity-50" />
                  <p>No expenses found matching filters.</p>
                </div>
              </td>
            </tr>

            <tr
              v-else
              v-for="expense in expenses"
              :key="expense.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >
              <td
                class="px-6 py-4 text-gray-600 dark:text-gray-300 font-medium whitespace-nowrap"
              >
                {{ new Date(expense.date).toLocaleDateString() }}
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <span class="text-gray-500 font-mono text-xs">{{
                    expense.reference_no || "-"
                  }}</span>
                  <a
                    v-if="expense.attachment"
                    :href="getImageUrl(expense.attachment)"
                    target="_blank"
                    class="text-blue-500 hover:text-blue-700"
                    title="View Receipt"
                  >
                    <PhotoIcon class="w-4 h-4" />
                  </a>
                </div>
              </td>
              <td class="px-6 py-4">
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-700"
                >
                  {{ expense.category?.name || "Uncategorized" }}
                </span>
              </td>
              <td
                class="px-6 py-4 text-gray-600 max-w-xs truncate"
                :title="expense.description"
              >
                {{ expense.description || "No description" }}
              </td>
              <td class="px-6 py-4 text-right font-bold text-rose-600">
                ৳ {{ Number(expense.amount).toLocaleString() }}
              </td>
              <td class="px-6 py-4 text-center text-xs text-gray-500">
                {{ expense.creator?.name }}
              </td>
              <td class="px-6 py-4 text-center">
                <div class="flex justify-center gap-2">
                  <button
                    @click="openEditModal(expense)"
                    class="p-1.5 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded transition"
                    title="Edit Expense"
                  >
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>

                  <button
                    v-if="isAdmin"
                    @click="deleteExpense(expense.id)"
                    class="p-1.5 text-red-600 bg-red-50 hover:bg-red-100 rounded transition"
                    title="Delete Expense"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
          <tfoot
            v-if="expenses.length > 0"
            class="bg-gray-50 dark:bg-slate-800 border-t border-gray-200"
          >
            <tr>
              <td
                colspan="4"
                class="px-6 py-4 text-right font-bold text-gray-600 uppercase text-xs"
              >
                Page Total:
              </td>
              <td
                class="px-6 py-4 text-right font-bold text-rose-600 text-base"
              >
                ৳ {{ totalInView.toLocaleString() }}
              </td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div
        class="p-4 flex justify-between items-center border-t border-gray-200 dark:border-slate-800"
      >
        <span class="text-xs text-gray-500"
          >Showing page {{ pagination.current_page }} of
          {{ pagination.last_page }}</span
        >
        <div class="flex gap-1">
          <button
            @click="fetchExpenses(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="px-3 py-1 border rounded hover:bg-gray-100 disabled:opacity-50 text-sm"
          >
            Prev
          </button>
          <button
            @click="fetchExpenses(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="px-3 py-1 border rounded hover:bg-gray-100 disabled:opacity-50 text-sm"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
      <div
        class="bg-white dark:bg-slate-900 w-full max-w-lg rounded-2xl shadow-xl overflow-hidden"
      >
        <div
          class="px-6 py-4 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center"
        >
          <h3 class="text-lg font-bold text-gray-800 dark:text-white">
            {{ isEditing ? "Edit Expense" : "Add New Expense" }}
          </h3>
          <button @click="closeModal" class="text-gray-400 hover:text-red-500">
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1"
                >Date <span class="text-red-500">*</span></label
              >
              <input
                v-model="form.date"
                type="date"
                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
                required
              />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1"
                >Reference No</label
              >
              <input
                v-model="form.reference_no"
                type="text"
                placeholder="e.g. BILL-123"
                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1"
              >Category <span class="text-red-500">*</span></label
            >
            <div class="flex gap-2">
              <select
                v-model="form.expense_category_id"
                class="flex-1 p-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
              >
                <option value="" disabled>Select Category</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
              <button
                @click="showCategoryModal = true"
                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-indigo-600 font-bold text-xs border border-gray-200"
                title="Add New Category"
                type="button"
              >
                <PlusIcon class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1"
              >Amount <span class="text-red-500">*</span></label
            >
            <div class="relative">
              <span class="absolute left-3 top-2 text-gray-500">৳</span>
              <input
                v-model="form.amount"
                type="number"
                placeholder="0.00"
                class="w-full pl-8 p-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-bold"
                required
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1"
              >Receipt / Voucher (Optional)</label
            >
            <div class="flex items-center gap-4">
              <label
                class="cursor-pointer flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm text-gray-600 border border-gray-200 transition"
              >
                <PaperClipIcon class="w-4 h-4" />
                <span>{{
                  form.attachment ? "Change File" : "Upload File"
                }}</span>
                <input
                  type="file"
                  class="hidden"
                  @change="handleFileChange"
                  accept="image/*"
                />
              </label>

              <div
                v-if="attachmentPreview"
                class="relative h-12 w-12 rounded overflow-hidden border border-gray-300"
              >
                <img
                  :src="attachmentPreview"
                  class="h-full w-full object-cover"
                />
                <button
                  v-if="
                    !isEditing || (isEditing && form.attachment instanceof File)
                  "
                  @click="
                    attachmentPreview = null;
                    form.attachment = null;
                  "
                  class="absolute top-0 right-0 bg-red-500 text-white p-0.5 rounded-bl text-xs"
                >
                  <XMarkIcon class="w-3 h-3" />
                </button>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1"
              >Description</label
            >
            <textarea
              v-model="form.description"
              rows="2"
              placeholder="Expense details..."
              class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
            ></textarea>
          </div>
        </div>

        <div
          class="px-6 py-4 bg-gray-50 dark:bg-slate-800 flex justify-end gap-3"
        >
          <button
            @click="closeModal"
            class="px-4 py-2 text-gray-600 font-bold hover:bg-gray-200 rounded-lg text-sm"
          >
            Cancel
          </button>
          <button
            @click="saveExpense"
            :disabled="submitting"
            class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 disabled:opacity-50 text-sm flex items-center gap-2"
          >
            <span
              v-if="submitting"
              class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"
            ></span>
            {{ isEditing ? "Update Expense" : "Save Expense" }}
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="showCategoryModal"
      class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/20 backdrop-blur-sm"
    >
      <div class="bg-white p-6 rounded-xl shadow-lg w-80">
        <h4 class="font-bold text-gray-800 mb-3">New Category</h4>
        <input
          v-model="categoryForm.name"
          type="text"
          placeholder="Category Name (e.g. Rent)"
          class="w-full p-2 border rounded mb-4 text-sm focus:ring-2 focus:ring-indigo-500 outline-none"
          @keyup.enter="saveCategory"
        />
        <div class="flex justify-end gap-2">
          <button
            @click="showCategoryModal = false"
            class="px-3 py-1.5 text-xs font-bold text-gray-500 hover:bg-gray-100 rounded"
          >
            Cancel
          </button>
          <button
            @click="saveCategory"
            class="px-3 py-1.5 text-xs font-bold bg-indigo-600 text-white rounded hover:bg-indigo-700"
          >
            Save
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
