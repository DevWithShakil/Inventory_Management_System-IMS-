<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useRouter, useRoute } from "vue-router"; // Router import
import axios from "../axios";
import Swal from "sweetalert2";
import CustomerFormModal from "../components/CustomerFormModal.vue";
import {
  MagnifyingGlassIcon,
  UserPlusIcon,
  PencilSquareIcon,
  TrashIcon,
  UserIcon,
  PhoneIcon,
  GiftIcon,
  EyeIcon,
} from "@heroicons/vue/24/outline";

const route = useRoute();
const router = useRouter();

// --- State ---
const customers = ref([]);
const isLoading = ref(false);
const searchQuery = ref("");

// Modal State
const showModal = ref(false);
const selectedCustomer = ref(null);

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
const fetchCustomers = async () => {
  isLoading.value = true;
  try {
    const response = await axios.get("/customers");
    if (response.data.status) {
      customers.value = response.data.data;
    }
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const deleteCustomer = async (id) => {
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
      await axios.delete(`/customers/${id}`);
      Swal.fire({
        icon: "success",
        title: "Deleted!",
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 1500,
      });
      fetchCustomers();
    } catch (error) {
      let msg = "Failed to delete.";
      if (error.response && error.response.status === 400)
        msg = error.response.data.message;
      Swal.fire("Error", msg, "error");
    }
  }
};

// --- History Feature (Placeholder) ---
const viewHistory = (customer) => {
  Swal.fire({
    title: `History: ${customer.name}`,
    text: "Order history feature will be available once Sales Module is ready!",
    icon: "info",
  });
};

// --- Computed Filter ---
const filteredCustomers = computed(() => {
  if (!searchQuery.value) return customers.value;
  const query = searchQuery.value.toLowerCase();
  return customers.value.filter(
    (c) =>
      c.name.toLowerCase().includes(query) ||
      c.phone.includes(query) ||
      (c.email && c.email.toLowerCase().includes(query)),
  );
});

// --- Handlers ---
const openAddModal = () => {
  selectedCustomer.value = null;
  showModal.value = true;
};

const openEditModal = (customer) => {
  selectedCustomer.value = { ...customer };
  showModal.value = true;
};

//  Updated Modal Close: Cleans URL query
const handleModalClose = (refresh) => {
  showModal.value = false;
  selectedCustomer.value = null;

  // Remove '?action=add' from URL
  if (route.query.action) {
    router.replace({ query: null });
  }

  if (refresh) fetchCustomers();
};

onMounted(() => fetchCustomers());
</script>

<template>
  <div class="space-y-6">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
    >
      <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
          Customer Management
        </h2>
        <p class="text-sm text-gray-500">
          Manage customers, loyalty points and history.
        </p>
      </div>
      <button
        @click="openAddModal"
        class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-md transition transform active:scale-95 text-sm font-bold"
      >
        <UserPlusIcon class="w-5 h-5" /> Add Customer
      </button>
    </div>

    <div
      class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row gap-4"
    >
      <div class="relative flex-1">
        <MagnifyingGlassIcon
          class="absolute left-3 top-2.5 h-5 w-5 text-gray-400"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by Name or Phone..."
          class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
        />
      </div>
      <div
        class="flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg font-bold text-sm"
      >
        <UserIcon class="w-5 h-5" /> Total Customers: {{ customers.length }}
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
              <th class="px-6 py-4">Customer Info</th>
              <th class="px-6 py-4">Contact</th>
              <th class="px-6 py-4 text-center">Loyalty Points</th>
              <th class="px-6 py-4 text-right">Total Spent</th>
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

            <tr v-else-if="filteredCustomers.length === 0">
              <td colspan="5" class="px-6 py-10 text-center text-gray-400">
                No customers found.
              </td>
            </tr>

            <tr
              v-else
              v-for="customer in filteredCustomers"
              :key="customer.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >
              <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                  <div
                    class="h-10 w-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-lg"
                  >
                    {{ customer.name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <h4 class="font-bold text-gray-800 dark:text-white">
                      {{ customer.name }}
                    </h4>
                    <p class="text-xs text-gray-500">ID: {{ customer.id }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-3">
                <div
                  class="flex flex-col text-sm text-gray-600 dark:text-gray-300"
                >
                  <span class="flex items-center gap-1"
                    ><PhoneIcon class="w-3 h-3" /> {{ customer.phone }}</span
                  >
                  <span v-if="customer.email" class="text-xs text-gray-400">{{
                    customer.email
                  }}</span>
                </div>
              </td>
              <td class="px-6 py-3 text-center">
                <span
                  class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-xs font-bold flex items-center justify-center gap-1 w-max mx-auto"
                >
                  <GiftIcon class="w-3 h-3" /> {{ customer.points || 0 }} pts
                </span>
              </td>
              <td
                class="px-6 py-3 text-right font-bold text-gray-800 dark:text-white"
              >
                ৳ {{ Number(customer.total_spent || 0).toLocaleString() }}
              </td>
              <td class="px-6 py-3 text-center">
                <div class="flex justify-center gap-2">
                  <button
                    @click="viewHistory(customer)"
                    class="p-1.5 text-indigo-600 bg-indigo-50 rounded hover:bg-indigo-100 transition"
                    title="View History"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="openEditModal(customer)"
                    class="p-1.5 text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition"
                    title="Edit"
                  >
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteCustomer(customer.id)"
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

    <CustomerFormModal
      :isOpen="showModal"
      :customer="selectedCustomer"
      @close="handleModalClose"
    />
  </div>
</template>
