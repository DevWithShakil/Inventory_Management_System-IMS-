<script setup>
import { ref, onMounted } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import { UserCircleIcon, KeyIcon, CameraIcon } from "@heroicons/vue/24/outline";

const user = ref({
  name: "",
  email: "",
  role: "",
  avatar: null, // For DB path
});

// For file upload
const avatarFile = ref(null);
const previewAvatar = ref(null);
const fileInput = ref(null);

const passwordForm = ref({
  current_password: "",
  new_password: "",
  new_password_confirmation: "",
});

const loading = ref(false);
const errors = ref({});

// Helper to get image URL
const getAvatarUrl = (path) => {
  if (!path) return null;
  if (path.startsWith("http")) return path;
  return `http://localhost:8000/storage/${path}`;
};

// Load User Data
onMounted(() => {
  const storedUser = JSON.parse(localStorage.getItem("user") || "{}");
  user.value = { ...storedUser };
  if (storedUser.avatar) {
    previewAvatar.value = getAvatarUrl(storedUser.avatar);
  }
});

// Handle File Select
const handleFileChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    avatarFile.value = file;
    previewAvatar.value = URL.createObjectURL(file);
  }
};

const updateProfile = async () => {
  loading.value = true;
  errors.value = {};

  try {
    // 🔥 Must use FormData for file upload
    const formData = new FormData();
    formData.append("name", user.value.name);
    formData.append("email", user.value.email);

    if (avatarFile.value) {
      formData.append("avatar", avatarFile.value);
    }

    if (passwordForm.value.current_password) {
      formData.append("current_password", passwordForm.value.current_password);
      formData.append("new_password", passwordForm.value.new_password);
      formData.append(
        "new_password_confirmation",
        passwordForm.value.new_password_confirmation,
      );
    }

    const response = await axios.post("/profile/update", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    if (response.data.status) {
      Swal.fire({
        icon: "success",
        title: "Success",
        text: "Profile updated successfully!",
        timer: 1500,
        showConfirmButton: false,
      });

      // Update LocalStorage & Trigger Event for Header Update
      const updatedUser = {
        ...JSON.parse(localStorage.getItem("user")),
        ...response.data.user,
      };
      localStorage.setItem("user", JSON.stringify(updatedUser));

      // Notify other components (MainLayout) that user data changed
      window.dispatchEvent(new Event("user-profile-updated"));

      // Clear password fields
      passwordForm.value = {
        current_password: "",
        new_password: "",
        new_password_confirmation: "",
      };
      avatarFile.value = null; // Reset file input
    }
  } catch (error) {
    if (error.response && error.response.status === 422) {
      errors.value = error.response.data.errors;
    } else {
      Swal.fire("Error", "Something went wrong.", "error");
    }
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="max-w-4xl mx-auto pb-10">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
        My Profile
      </h1>
      <p class="text-sm text-gray-500">
        Manage your account settings and password.
      </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-1">
        <div
          class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 text-center"
        >
          <div
            class="relative w-28 h-28 mx-auto mb-4 group cursor-pointer"
            @click="$refs.fileInput.click()"
          >
            <div
              class="w-full h-full rounded-full overflow-hidden border-4 border-white dark:border-slate-700 shadow-lg bg-indigo-50"
            >
              <img
                v-if="previewAvatar"
                :src="previewAvatar"
                class="w-full h-full object-cover"
              />
              <div
                v-else
                class="w-full h-full flex items-center justify-center bg-indigo-100 dark:bg-indigo-900 text-3xl font-bold text-indigo-600 dark:text-indigo-300"
              >
                {{ user.name?.charAt(0).toUpperCase() }}
              </div>
            </div>

            <div
              class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200"
            >
              <CameraIcon class="w-8 h-8 text-white" />
            </div>

            <input
              type="file"
              ref="fileInput"
              class="hidden"
              accept="image/*"
              @change="handleFileChange"
            />
          </div>

          <h2 class="text-xl font-bold text-gray-800 dark:text-white">
            {{ user.name }}
          </h2>
          <span
            class="inline-block mt-2 px-3 py-1 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 text-xs font-bold uppercase rounded-full"
          >
            {{ user.role }}
          </span>
          <p class="text-xs text-gray-400 mt-2">Click image to update</p>
        </div>
      </div>

      <div class="lg:col-span-2 space-y-6">
        <div
          class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700"
        >
          <h3
            class="font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2 border-b pb-4 dark:border-slate-700"
          >
            <UserCircleIcon class="w-5 h-5 text-indigo-500" />
            Basic Information
          </h3>

          <div class="grid grid-cols-1 gap-4">
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Full Name</label
              >
              <input
                v-model="user.name"
                type="text"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-transparent focus:ring-2 focus:ring-indigo-500 outline-none transition"
              />
            </div>

            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Email Address</label
              >
              <input
                v-model="user.email"
                type="email"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-transparent focus:ring-2 focus:ring-indigo-500 outline-none transition"
              />
              <p v-if="errors.email" class="text-red-500 text-xs mt-1">
                {{ errors.email[0] }}
              </p>
            </div>
          </div>
        </div>

        <div
          class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700"
        >
          <h3
            class="font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2 border-b pb-4 dark:border-slate-700"
          >
            <KeyIcon class="w-5 h-5 text-indigo-500" />
            Change Password
          </h3>

          <div class="space-y-4">
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Current Password</label
              >
              <input
                v-model="passwordForm.current_password"
                type="password"
                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-transparent focus:ring-2 focus:ring-indigo-500 outline-none transition"
              />
              <p
                v-if="errors.current_password"
                class="text-red-500 text-xs mt-1"
              >
                {{ errors.current_password[0] }}
              </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label
                  class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                  >New Password</label
                >
                <input
                  v-model="passwordForm.new_password"
                  type="password"
                  class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-transparent focus:ring-2 focus:ring-indigo-500 outline-none transition"
                />
              </div>
              <div>
                <label
                  class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                  >Confirm Password</label
                >
                <input
                  v-model="passwordForm.new_password_confirmation"
                  type="password"
                  class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-transparent focus:ring-2 focus:ring-indigo-500 outline-none transition"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end">
          <button
            @click="updateProfile"
            :disabled="loading"
            class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium flex items-center gap-2 disabled:opacity-70 shadow-lg shadow-indigo-200 dark:shadow-none"
          >
            <span
              v-if="loading"
              class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"
            ></span>
            {{ loading ? "Updating Profile..." : "Save Changes" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
