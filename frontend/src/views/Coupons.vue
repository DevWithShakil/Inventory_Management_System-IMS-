<script setup>
import { ref, computed, onMounted, watch } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import {
  TicketIcon,
  PlusIcon,
  MagnifyingGlassIcon,
  TrashIcon,
  UserGroupIcon,
  UserIcon,
  ClipboardDocumentIcon,
  CalendarIcon,
  XMarkIcon,
  CheckCircleIcon,
  GlobeAltIcon,
} from "@heroicons/vue/24/outline";

// --- State ---
const coupons = ref([]);
const allCustomers = ref([]); // সব কাস্টমার এখানে লোড হবে
const isLoading = ref(false);
const searchQuery = ref("");
const showModal = ref(false);
const isSubmitting = ref(false);

// --- New State for Custom Selection ---
const isGlobal = ref(true); // বাই ডিফল্ট গ্লোবাল থাকবে
const customerSearchQuery = ref("");
const showCustomerResults = ref(false);
const selectedCustomer = ref(null);

// Form State
const form = ref({
  code: "",
  type: "fixed",
  value: "",
  min_purchase: 0,
  expires_at: "",
  usage_limit: "",
  customer_id: null,
});

// --- API Actions ---
const fetchCoupons = async () => {
  isLoading.value = true;
  try {
    const response = await axios.get("/coupons");
    if (response.data.status) {
      coupons.value = response.data.data || [];
    }
  } catch (error) {
    console.error("Error fetching coupons:", error);
    coupons.value = [];
  } finally {
    isLoading.value = false;
  }
};

const fetchCustomers = async () => {
  try {
    const response = await axios.get("/customers");
    if (response.data.status) {
      allCustomers.value = response.data.data;
    }
  } catch (error) {
    console.error(error);
  }
};

// --- Customer Search Logic (Local Filter) ---
const filteredCustomerSearch = computed(() => {
  if (!customerSearchQuery.value) return [];
  const query = customerSearchQuery.value.toLowerCase();
  return allCustomers.value.filter(
    (c) => c.name.toLowerCase().includes(query) || c.phone.includes(query),
  );
});

const selectCustomer = (customer) => {
  selectedCustomer.value = customer;
  form.value.customer_id = customer.id;
  isGlobal.value = false;
  customerSearchQuery.value = "";
  showCustomerResults.value = false;
};

const removeSelectedCustomer = () => {
  selectedCustomer.value = null;
  form.value.customer_id = null;
};

// Toggle Global/Private
const toggleGlobal = () => {
  isGlobal.value = !isGlobal.value;
  if (isGlobal.value) {
    // গ্লোবাল হলে কাস্টমার ক্লিয়ার করে দিব
    removeSelectedCustomer();
  }
};

const createCoupon = async () => {
  if (!form.value.code || !form.value.value) {
    Swal.fire("Error", "Please fill required fields", "warning");
    return;
  }

  // যদি প্রাইভেট হয় কিন্তু কাস্টমার সিলেক্ট না করে
  if (!isGlobal.value && !form.value.customer_id) {
    Swal.fire(
      "Error",
      "Please select a customer for private coupon",
      "warning",
    );
    return;
  }

  isSubmitting.value = true;
  try {
    const payload = { ...form.value };

    // নিশ্চিত হওয়া যে গ্লোবাল হলে customer_id নাল যাচ্ছে
    if (isGlobal.value) {
      payload.customer_id = null;
    }

    const response = await axios.post("/coupons", payload);

    if (response.data.status) {
      Swal.fire({
        icon: "success",
        title: "Created!",
        text: "Coupon created successfully",
        toast: true,
        position: "top-end",
        timer: 2000,
        showConfirmButton: false,
      });
      fetchCoupons();
      closeModal();
    }
  } catch (error) {
    Swal.fire("Error", error.response?.data?.message || "Failed", "error");
  } finally {
    isSubmitting.value = false;
  }
};

const deleteCoupon = async (id) => {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "You won't be able to revert this!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#EF4444",
    confirmButtonText: "Yes, delete it!",
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/coupons/${id}`);
      fetchCoupons();
      Swal.fire("Deleted!", "Coupon has been deleted.", "success");
    } catch (error) {
      Swal.fire("Error", "Failed to delete coupon", "error");
    }
  }
};

// --- Helpers ---
const generateCode = () => {
  const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  let result = "";
  for (let i = 0; i < 8; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  form.value.code = result;
};

const copyToClipboard = (text) => {
  navigator.clipboard.writeText(text);
  Swal.fire({
    icon: "success",
    title: "Copied!",
    toast: true,
    position: "top-end",
    timer: 1000,
    showConfirmButton: false,
  });
};

const formatDate = (dateString) => {
  if (!dateString) return "No Expiry";
  return new Date(dateString).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const isExpired = (dateString) => {
  if (!dateString) return false;
  return new Date(dateString) < new Date();
};

const closeModal = () => {
  showModal.value = false;
  // Reset Form
  form.value = {
    code: "",
    type: "fixed",
    value: "",
    min_purchase: 0,
    expires_at: "",
    usage_limit: "",
    customer_id: null,
  };
  // Reset Custom State
  isGlobal.value = true;
  selectedCustomer.value = null;
  customerSearchQuery.value = "";
};

const filteredCoupons = computed(() => {
  if (!coupons.value || coupons.value.length === 0) return [];
  if (!searchQuery.value) return coupons.value;
  const query = searchQuery.value.toLowerCase();
  return coupons.value.filter((c) => {
    const codeMatch = c.code.toLowerCase().includes(query);
    const customerMatch =
      c.customer && c.customer.name.toLowerCase().includes(query);
    return codeMatch || customerMatch;
  });
});

onMounted(() => {
  fetchCoupons();
  fetchCustomers();
});
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6">
    <div
      class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"
    >
      <div>
        <h1
          class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2"
        >
          <TicketIcon class="w-8 h-8 text-indigo-600" />
          Coupons & Offers
        </h1>
        <p class="text-sm text-gray-500 mt-1">
          Manage discounts and promo codes for your customers.
        </p>
      </div>
      <button
        @click="showModal = true"
        class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg shadow-sm transition-all transform active:scale-95"
      >
        <PlusIcon class="w-5 h-5" />
        Create Coupon
      </button>
    </div>

    <div
      class="bg-white dark:bg-slate-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700"
    >
      <div class="relative max-w-md">
        <MagnifyingGlassIcon
          class="absolute left-3 top-2.5 h-5 w-5 text-gray-400"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by code or customer name..."
          class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-slate-700/50 border border-gray-200 dark:border-slate-600 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500 transition"
        />
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden"
    >
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead
            class="bg-gray-50 dark:bg-slate-700/50 text-gray-500 uppercase text-xs font-semibold"
          >
            <tr>
              <th class="px-6 py-4">Code</th>
              <th class="px-6 py-4">Discount</th>
              <th class="px-6 py-4">Scope</th>
              <th class="px-6 py-4">Usage</th>
              <th class="px-6 py-4">Validity</th>
              <th class="px-6 py-4 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
            <tr v-if="isLoading">
              <td colspan="6" class="px-6 py-10 text-center">
                <div
                  class="animate-spin inline-block w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full"
                ></div>
              </td>
            </tr>
            <tr v-else-if="filteredCoupons.length === 0">
              <td colspan="6" class="px-6 py-10 text-center text-gray-400">
                <TicketIcon class="w-12 h-12 mx-auto mb-2 opacity-20" />
                No coupons found.
              </td>
            </tr>
            <tr
              v-else
              v-for="coupon in filteredCoupons"
              :key="coupon.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition group"
            >
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <span
                    class="font-mono font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded border border-indigo-100 dark:bg-indigo-900/30 dark:border-indigo-700/50"
                  >
                    {{ coupon.code }}
                  </span>
                  <button
                    @click="copyToClipboard(coupon.code)"
                    class="text-gray-400 hover:text-gray-600 opacity-0 group-hover:opacity-100 transition"
                  >
                    <ClipboardDocumentIcon class="w-4 h-4" />
                  </button>
                </div>
                <p class="text-xs text-gray-400 mt-1">
                  Min: ৳{{ coupon.min_purchase }}
                </p>
              </td>
              <td class="px-6 py-4">
                <div class="font-bold text-gray-800 dark:text-white">
                  {{ coupon.type === "fixed" ? "৳" : "" }}{{ coupon.value
                  }}{{ coupon.type === "percent" ? "%" : "" }}
                </div>
                <span class="text-xs text-gray-500 capitalize"
                  >{{ coupon.type }} off</span
                >
              </td>
              <td class="px-6 py-4">
                <div
                  v-if="!coupon.customer_id"
                  class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold border border-green-200"
                >
                  <GlobeAltIcon class="w-3 h-3" /> Global
                </div>
                <div v-else class="flex items-center gap-2">
                  <span
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-bold border border-purple-200"
                  >
                    <UserIcon class="w-3 h-3" /> Private
                  </span>
                  <span
                    class="text-xs font-medium text-gray-600 dark:text-gray-300"
                  >
                    {{
                      coupon.customer ? coupon.customer.name : "Unknown User"
                    }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4">
                <span class="font-bold text-gray-700 dark:text-gray-200"
                  >{{ coupon.used_count }} /
                  {{ coupon.usage_limit || "∞" }}</span
                >
              </td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold"
                  :class="
                    isExpired(coupon.expires_at)
                      ? 'bg-red-100 text-red-700'
                      : 'bg-emerald-100 text-emerald-700'
                  "
                >
                  {{ isExpired(coupon.expires_at) ? "Expired" : "Active" }}
                </span>
                <p class="text-xs text-gray-500 mt-1">
                  {{ formatDate(coupon.expires_at) }}
                </p>
              </td>
              <td class="px-6 py-4 text-right">
                <button
                  @click="deleteCoupon(coupon.id)"
                  class="text-gray-400 hover:text-red-500 transition"
                >
                  <TrashIcon class="w-5 h-5" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm transition-opacity"
    >
      <div
        class="bg-white dark:bg-slate-900 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden transform transition-all"
      >
        <div
          class="px-6 py-4 border-b border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/50 flex justify-between items-center"
        >
          <h3
            class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"
          >
            <PlusIcon class="w-5 h-5 text-indigo-600" /> New Coupon
          </h3>
          <button
            @click="closeModal"
            class="text-gray-400 hover:text-gray-600 transition"
          >
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>

        <div class="p-6 space-y-5">
          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1"
              >Coupon Code</label
            >
            <div class="flex gap-2">
              <input
                v-model="form.code"
                type="text"
                placeholder="E.G. SUMMER2024"
                class="flex-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500 font-mono uppercase"
              />
              <button
                @click="generateCode"
                class="px-3 bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 text-xs font-bold rounded-lg border border-gray-200 dark:border-slate-600"
              >
                Auto
              </button>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label
                class="block text-xs font-bold text-gray-500 uppercase mb-1"
                >Type</label
              >
              <select
                v-model="form.type"
                class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="fixed">Fixed Amount (৳)</option>
                <option value="percent">Percentage (%)</option>
              </select>
            </div>
            <div>
              <label
                class="block text-xs font-bold text-gray-500 uppercase mb-1"
                >Value</label
              >
              <input
                v-model="form.value"
                type="number"
                placeholder="0.00"
                class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-2"
              >Target Audience</label
            >

            <div class="flex items-center gap-4 mb-3">
              <button
                @click="toggleGlobal"
                class="flex-1 py-2 px-4 rounded-lg border text-sm font-bold transition flex items-center justify-center gap-2"
                :class="
                  isGlobal
                    ? 'bg-green-50 border-green-200 text-green-700'
                    : 'bg-gray-50 border-gray-200 text-gray-400 hover:bg-gray-100'
                "
              >
                <GlobeAltIcon class="w-4 h-4" /> Global (All)
                <CheckCircleIcon v-if="isGlobal" class="w-4 h-4 ml-1" />
              </button>

              <button
                @click="toggleGlobal"
                class="flex-1 py-2 px-4 rounded-lg border text-sm font-bold transition flex items-center justify-center gap-2"
                :class="
                  !isGlobal
                    ? 'bg-purple-50 border-purple-200 text-purple-700'
                    : 'bg-gray-50 border-gray-200 text-gray-400 hover:bg-gray-100'
                "
              >
                <UserIcon class="w-4 h-4" /> Specific Customer
                <CheckCircleIcon v-if="!isGlobal" class="w-4 h-4 ml-1" />
              </button>
            </div>

            <div v-if="!isGlobal" class="relative">
              <div
                v-if="selectedCustomer"
                class="flex items-center justify-between p-3 bg-purple-50 border border-purple-200 rounded-lg"
              >
                <div>
                  <p class="text-sm font-bold text-purple-800">
                    {{ selectedCustomer.name }}
                  </p>
                  <p class="text-xs text-purple-600">
                    {{ selectedCustomer.phone }}
                  </p>
                </div>
                <button
                  @click="removeSelectedCustomer"
                  class="text-purple-400 hover:text-purple-700"
                >
                  <XMarkIcon class="w-5 h-5" />
                </button>
              </div>

              <div v-else>
                <div class="relative">
                  <MagnifyingGlassIcon
                    class="absolute left-3 top-2.5 w-5 h-5 text-gray-400"
                  />
                  <input
                    v-model="customerSearchQuery"
                    @focus="showCustomerResults = true"
                    type="text"
                    placeholder="Search Customer Name or Phone..."
                    class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg outline-none focus:ring-2 focus:ring-purple-500"
                  />
                </div>

                <div
                  v-if="
                    showCustomerResults && filteredCustomerSearch.length > 0
                  "
                  class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-xl max-h-48 overflow-y-auto"
                >
                  <ul>
                    <li
                      v-for="cus in filteredCustomerSearch"
                      :key="cus.id"
                      @click="selectCustomer(cus)"
                      class="px-4 py-2 hover:bg-purple-50 dark:hover:bg-slate-700 cursor-pointer text-sm border-b border-gray-100 last:border-0"
                    >
                      <p class="font-bold text-gray-800 dark:text-white">
                        {{ cus.name }}
                      </p>
                      <p class="text-xs text-gray-500">{{ cus.phone }}</p>
                    </li>
                  </ul>
                </div>
                <div
                  v-if="
                    showCustomerResults &&
                    filteredCustomerSearch.length === 0 &&
                    customerSearchQuery
                  "
                  class="absolute z-10 w-full mt-1 bg-white p-2 text-sm text-center text-gray-500 border rounded shadow"
                >
                  No customer found.
                </div>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label
                class="block text-xs font-bold text-gray-500 uppercase mb-1"
                >Min Purchase</label
              >
              <input
                v-model="form.min_purchase"
                type="number"
                class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label
                class="block text-xs font-bold text-gray-500 uppercase mb-1"
                >Usage Limit</label
              >
              <input
                v-model="form.usage_limit"
                type="number"
                placeholder="∞"
                class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1"
              >Expiry Date</label
            >
            <input
              v-model="form.expires_at"
              type="date"
              class="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>

        <div
          class="px-6 py-4 bg-gray-50 dark:bg-slate-800/50 flex justify-end gap-3"
        >
          <button
            @click="closeModal"
            class="px-4 py-2 text-gray-600 font-bold hover:bg-gray-100 rounded-lg transition"
          >
            Cancel
          </button>
          <button
            @click="createCoupon"
            :disabled="isSubmitting"
            class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg shadow-lg hover:bg-indigo-700 disabled:opacity-50 transition flex items-center gap-2"
          >
            <span
              v-if="isSubmitting"
              class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"
            ></span>
            {{ isSubmitting ? "Saving..." : "Create Coupon" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
