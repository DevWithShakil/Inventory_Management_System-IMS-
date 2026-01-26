<script setup>
import { ref, onMounted, reactive } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import {
  BuildingStorefrontIcon,
  ShieldCheckIcon,
  PhotoIcon,
} from "@heroicons/vue/24/outline";

// --- State ---
const activeTab = ref("general"); // 'general' or 'security'
const isLoading = ref(false);
const isSaving = ref(false);

// General Settings Form
const generalForm = ref({
  company_name: "",
  company_phone: "",
  company_email: "",
  company_address: "",
  currency_symbol: "৳",
  default_vat: 0,
  delivery_charge: 0,
  image: null, // File object
});
const logoPreview = ref(null);

// Password Form
const passwordForm = reactive({
  current_password: "",
  new_password: "",
  new_password_confirmation: "",
});

// --- Actions ---

// 1. Fetch Settings
const fetchSettings = async () => {
  isLoading.value = true;
  try {
    const response = await axios.get("/settings");
    if (response.data.status) {
      const data = response.data.data;
      // ডাটা পপুলেট করা
      generalForm.value = { ...data, image: null }; // ইমেজ ফিল্ড রিসেট

      // লোগো প্রিভিউ সেট করা
      if (data.company_logo) {
        logoPreview.value = data.company_logo.startsWith("http")
          ? data.company_logo
          : `http://localhost:8000/storage/${data.company_logo}`;
      }
    }
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

// 2. Handle Logo Upload
const handleFileChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    generalForm.value.image = file;
    logoPreview.value = URL.createObjectURL(file);
  }
};

// 3. Update General Settings
const updateGeneralSettings = async () => {
  isSaving.value = true;
  const formData = new FormData();

  Object.keys(generalForm.value).forEach((key) => {
    if (generalForm.value[key] !== null) {
      formData.append(key, generalForm.value[key]);
    }
  });

  try {
    await axios.post("/settings", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    Swal.fire("Success", "Settings updated successfully!", "success");
  } catch (error) {
    console.error(error);
    Swal.fire("Error", "Failed to update settings.", "error");
  } finally {
    isSaving.value = false;
  }
};

// 4. Change Password
const changePassword = async () => {
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    Swal.fire("Error", "Passwords do not match!", "error");
    return;
  }

  isSaving.value = true;
  try {
    await axios.post("/change-password", passwordForm);
    Swal.fire("Success", "Password changed successfully!", "success");
    // Form Reset
    passwordForm.current_password = "";
    passwordForm.new_password = "";
    passwordForm.new_password_confirmation = "";
  } catch (error) {
    let msg = "Failed to change password.";
    if (error.response && error.response.data.message)
      msg = error.response.data.message;
    Swal.fire("Error", msg, "error");
  } finally {
    isSaving.value = false;
  }
};

onMounted(() => fetchSettings());
</script>

<template>
  <div class="max-w-4xl mx-auto space-y-6 pb-10">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
          Settings
        </h2>
        <p class="text-sm text-gray-500">
          Configure your shop settings and profile.
        </p>
      </div>
    </div>

    <div class="flex space-x-4 border-b border-gray-200 dark:border-slate-700">
      <button
        @click="activeTab = 'general'"
        :class="[
          'pb-2 px-4 text-sm font-medium transition',
          activeTab === 'general'
            ? 'border-b-2 border-indigo-600 text-indigo-600'
            : 'text-gray-500 hover:text-gray-700',
        ]"
      >
        <div class="flex items-center gap-2">
          <BuildingStorefrontIcon class="w-5 h-5" /> General & Shop
        </div>
      </button>
      <button
        @click="activeTab = 'security'"
        :class="[
          'pb-2 px-4 text-sm font-medium transition',
          activeTab === 'security'
            ? 'border-b-2 border-indigo-600 text-indigo-600'
            : 'text-gray-500 hover:text-gray-700',
        ]"
      >
        <div class="flex items-center gap-2">
          <ShieldCheckIcon class="w-5 h-5" /> Security
        </div>
      </button>
    </div>

    <div
      class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-200 dark:border-slate-800 p-6"
    >
      <div v-if="activeTab === 'general'" class="space-y-6">
        <div
          class="flex flex-col items-center gap-4 py-4 border-b border-gray-100 dark:border-slate-800"
        >
          <div
            class="relative w-32 h-32 rounded-full overflow-hidden border-4 border-gray-100 dark:border-slate-700 bg-gray-50 flex items-center justify-center"
          >
            <img
              v-if="logoPreview"
              :src="logoPreview"
              class="w-full h-full object-cover"
            />
            <PhotoIcon v-else class="w-10 h-10 text-gray-300" />
          </div>
          <label
            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-bold rounded-lg cursor-pointer transition"
          >
            Change Logo
            <input
              type="file"
              class="hidden"
              accept="image/*"
              @change="handleFileChange"
            />
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Company Name</label
            >
            <input
              v-model="generalForm.company_name"
              type="text"
              class="w-full px-4 py-2 bg-gray-50 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            />
          </div>
          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Phone</label
            >
            <input
              v-model="generalForm.company_phone"
              type="text"
              class="w-full px-4 py-2 bg-gray-50 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            />
          </div>
          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Email</label
            >
            <input
              v-model="generalForm.company_email"
              type="email"
              class="w-full px-4 py-2 bg-gray-50 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            />
          </div>
          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Currency Symbol</label
            >
            <input
              v-model="generalForm.currency_symbol"
              type="text"
              class="w-full px-4 py-2 bg-gray-50 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
              placeholder="৳"
            />
          </div>
          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Default VAT (%)</label
            >
            <input
              v-model="generalForm.default_vat"
              type="number"
              class="w-full px-4 py-2 bg-gray-50 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            />
          </div>
          <div>
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Delivery Charge</label
            >
            <input
              v-model="generalForm.delivery_charge"
              type="number"
              class="w-full px-4 py-2 bg-gray-50 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            />
          </div>
          <div class="md:col-span-2">
            <label
              class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1"
              >Address</label
            >
            <textarea
              v-model="generalForm.company_address"
              rows="3"
              class="w-full px-4 py-2 bg-gray-50 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            ></textarea>
          </div>
        </div>

        <div class="flex justify-end pt-4">
          <button
            @click="updateGeneralSettings"
            :disabled="isSaving"
            class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-lg disabled:opacity-50"
          >
            {{ isSaving ? "Saving..." : "Save Settings" }}
          </button>
        </div>
      </div>

      <div
        v-if="activeTab === 'security'"
        class="space-y-6 max-w-md mx-auto py-10"
      >
        <h3 class="text-lg font-bold text-gray-800 text-center mb-6">
          Change Password
        </h3>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1"
              >Current Password</label
            >
            <input
              v-model="passwordForm.current_password"
              type="password"
              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            />
          </div>
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1"
              >New Password</label
            >
            <input
              v-model="passwordForm.new_password"
              type="password"
              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            />
          </div>
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1"
              >Confirm New Password</label
            >
            <input
              v-model="passwordForm.new_password_confirmation"
              type="password"
              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
            />
          </div>
        </div>

        <button
          @click="changePassword"
          :disabled="isSaving"
          class="w-full px-6 py-2.5 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 shadow-lg disabled:opacity-50 mt-6"
        >
          {{ isSaving ? "Updating..." : "Update Password" }}
        </button>
      </div>
    </div>
  </div>
</template>
