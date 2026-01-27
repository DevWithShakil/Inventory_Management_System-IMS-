<script setup>
import { ref, onMounted } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import {
  BuildingOfficeIcon,
  PhotoIcon,
  CurrencyBangladeshiIcon,
  DocumentTextIcon,
} from "@heroicons/vue/24/outline";

// State
const loading = ref(false);
const form = ref({
  company_name: "",
  company_email: "",
  company_phone: "",
  company_address: "",
  currency_symbol: "৳",
  invoice_footer_text: "",
  logo: null,
});

const previewLogo = ref(null); // For showing image preview
const logoInput = ref(null); // File input reference

// Fetch Settings
const fetchSettings = async () => {
  try {
    const response = await axios.get("/settings");
    if (response.data.status) {
      const data = response.data.data;
      form.value = { ...data, logo: null }; // Keep existing logo path separate

      // Set preview if logo exists
      if (data.logo) {
        previewLogo.value = `http://localhost:8000/storage/${data.logo}`;
      }
    }
  } catch (error) {
    console.error("Failed to load settings", error);
  }
};

// Handle File Change
const handleFileChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    form.value.logo = file;
    // Create preview URL
    previewLogo.value = URL.createObjectURL(file);
  }
};

// Update Settings
const updateSettings = async () => {
  loading.value = true;
  try {
    const formData = new FormData();
    formData.append("company_name", form.value.company_name);
    formData.append("company_email", form.value.company_email || "");
    formData.append("company_phone", form.value.company_phone || "");
    formData.append("company_address", form.value.company_address || "");
    formData.append("currency_symbol", form.value.currency_symbol);
    formData.append(
      "invoice_footer_text",
      form.value.invoice_footer_text || "",
    );

    // Only append logo if a new file is selected
    if (form.value.logo instanceof File) {
      formData.append("logo", form.value.logo);
    }

    // Axios POST request (Not PUT because of FormData/File upload)
    const response = await axios.post("/settings", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    if (response.data.status) {
      Swal.fire({
        icon: "success",
        title: "Updated!",
        text: "System settings have been updated.",
        timer: 2000,
        showConfirmButton: false,
      });
      // Refresh user context or logo in header if needed
    }
  } catch (error) {
    console.error("Update failed", error);
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "Failed to update settings.",
    });
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchSettings();
});
</script>

<template>
  <div class="max-w-4xl mx-auto pb-10">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
        System Settings
      </h1>
      <p class="text-sm text-gray-500">
        Manage your store details and branding.
      </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-1 space-y-6">
        <div
          class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700"
        >
          <h3
            class="font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2"
          >
            <PhotoIcon class="w-5 h-5 text-indigo-500" />
            Company Logo
          </h3>

          <div class="flex flex-col items-center">
            <div
              class="w-40 h-40 border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-xl flex items-center justify-center overflow-hidden mb-4 relative group bg-gray-50 dark:bg-slate-700"
            >
              <img
                v-if="previewLogo"
                :src="previewLogo"
                class="w-full h-full object-contain p-2"
              />
              <div v-else class="text-center p-4">
                <PhotoIcon class="w-10 h-10 text-gray-400 mx-auto mb-2" />
                <span class="text-xs text-gray-400">No Logo</span>
              </div>
            </div>

            <input
              type="file"
              ref="logoInput"
              @change="handleFileChange"
              class="hidden"
              accept="image/*"
            />

            <button
              @click="$refs.logoInput.click()"
              class="px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-200 transition w-full"
            >
              Change Logo
            </button>
            <p class="text-[10px] text-gray-400 mt-2 text-center">
              Recommended size: 300x100px. Max: 2MB.
            </p>
          </div>
        </div>
      </div>

      <div class="lg:col-span-2 space-y-6">
        <div
          class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700"
        >
          <h3
            class="font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2 border-b pb-4 dark:border-slate-700"
          >
            <BuildingOfficeIcon class="w-5 h-5 text-indigo-500" />
            General Information
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="col-span-2 md:col-span-1">
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Company Name</label
              >
              <input
                v-model="form.company_name"
                type="text"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-transparent focus:ring-2 focus:ring-indigo-500 outline-none transition"
                placeholder="e.g. Smart Shop"
              />
            </div>

            <div class="col-span-2 md:col-span-1">
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Phone Number</label
              >
              <input
                v-model="form.company_phone"
                type="text"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-transparent focus:ring-2 focus:ring-indigo-500 outline-none transition"
                placeholder="+880 1700..."
              />
            </div>

            <div class="col-span-2">
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Email Address</label
              >
              <input
                v-model="form.company_email"
                type="email"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-transparent focus:ring-2 focus:ring-indigo-500 outline-none transition"
                placeholder="info@company.com"
              />
            </div>

            <div class="col-span-2">
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Address</label
              >
              <textarea
                v-model="form.company_address"
                rows="3"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-transparent focus:ring-2 focus:ring-indigo-500 outline-none transition"
                placeholder="Shop address for invoice..."
              ></textarea>
            </div>
          </div>

          <h3
            class="font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2 border-b pb-4 dark:border-slate-700 pt-4"
          >
            <DocumentTextIcon class="w-5 h-5 text-indigo-500" />
            Invoice Configuration
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Currency Symbol</label
              >
              <div class="relative">
                <input
                  v-model="form.currency_symbol"
                  type="text"
                  class="w-full pl-10 px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-transparent focus:ring-2 focus:ring-indigo-500 outline-none transition"
                />
                <CurrencyBangladeshiIcon
                  class="w-5 h-5 text-gray-400 absolute left-3 top-2.5"
                />
              </div>
            </div>

            <div class="col-span-2">
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Invoice Footer Text</label
              >
              <input
                v-model="form.invoice_footer_text"
                type="text"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-transparent focus:ring-2 focus:ring-indigo-500 outline-none transition"
                placeholder="e.g. Thank you! No return without receipt."
              />
              <p class="text-xs text-gray-400 mt-1">
                This text will appear at the bottom of every invoice.
              </p>
            </div>
          </div>

          <div class="mt-8 flex justify-end">
            <button
              @click="updateSettings"
              :disabled="loading"
              class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium flex items-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed"
            >
              <span
                v-if="loading"
                class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"
              ></span>
              {{ loading ? "Saving..." : "Save Changes" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
