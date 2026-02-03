<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import {
  UserGroupIcon,
  PlusIcon,
  MagnifyingGlassIcon,
  PencilSquareIcon,
  TrashIcon,
  PhoneIcon,
  MapPinIcon,
  CalendarDaysIcon,
  BanknotesIcon,
  ChartBarIcon,
  UserIcon,
  XMarkIcon,
  EnvelopeIcon,
  CameraIcon, // 🔥 আইকন ইমপোর্ট
} from "@heroicons/vue/24/outline";

// --- State ---
const users = ref([]);
const selectedUser = ref(null);
const performanceData = ref(null);
const loading = ref(false);
const loadingStats = ref(false);
const searchQuery = ref("");
let pollingInterval = null;

// Modal & Form State
const showModal = ref(false);
const isEditing = ref(false);
const fileInput = ref(null); // ফাইল ইনপুটের রেফারেন্স
const avatarPreview = ref(null); // প্রিভিউ করার জন্য
const avatarFile = ref(null); // আসল ফাইল স্টোর করার জন্য

const form = ref({
  id: null,
  name: "",
  email: "",
  password: "",
  role: "staff",
  phone: "",
  address: "",
  avatar: null,
});

// --- Helper: Image URL Generator ---
const getAvatarUrl = (path) => {
  if (!path) return null;
  // আপনার ব্যাকএন্ড স্টোরেজ পাথ অনুযায়ী এটি পরিবর্তন হতে পারে
  return `http://localhost:8000/storage/${path}`;
};

// --- API Actions ---
const fetchUsers = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/users");
    if (response.data.status) {
      users.value = response.data.data;
      if (!selectedUser.value && users.value.length > 0) {
        selectUser(users.value[0]);
      }
    }
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const fetchUserStats = async (userId) => {
  loadingStats.value = true;
  performanceData.value = null;
  try {
    const response = await axios.get(`/users/${userId}/performance`);
    if (response.data.status) {
      performanceData.value = response.data.data;
    }
  } catch (error) {
    console.error("Failed to fetch stats", error);
  } finally {
    loadingStats.value = false;
  }
};

const startPolling = () => {
  if (pollingInterval) clearInterval(pollingInterval);
  pollingInterval = setInterval(async () => {
    if (selectedUser.value) {
      try {
        const response = await axios.get(
          `/users/${selectedUser.value.id}/performance`,
        );
        if (response.data.status) {
          performanceData.value = response.data.data;
        }
      } catch (error) {
        console.error("Polling error", error);
      }
    }
  }, 30000);
};

// --- Logic ---
const filteredUsers = computed(() => {
  if (!searchQuery.value) return users.value;
  return users.value.filter(
    (u) =>
      u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      u.email.toLowerCase().includes(searchQuery.value.toLowerCase()),
  );
});

const selectUser = (user) => {
  selectedUser.value = user;
  fetchUserStats(user.id);
  startPolling();
};

// --- File Upload Handlers ---
const triggerFileInput = () => {
  fileInput.value.click();
};

const handleFileChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    if (file.size > 2 * 1024 * 1024) {
      // 2MB Limit
      Swal.fire("Error", "Image size must be less than 2MB", "error");
      return;
    }
    avatarFile.value = file;
    // প্রিভিউ তৈরি
    const reader = new FileReader();
    reader.onload = (e) => {
      avatarPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
};

// --- CRUD Actions ---
const openModal = (user = null) => {
  avatarFile.value = null; // রিসেট
  avatarPreview.value = null; // রিসেট

  if (user) {
    isEditing.value = true;
    form.value = { ...user, password: "" };
    // যদি আগে থেকে ছবি থাকে, প্রিভিউতে দেখাবো
    if (user.avatar) {
      avatarPreview.value = getAvatarUrl(user.avatar);
    }
  } else {
    isEditing.value = false;
    form.value = {
      id: null,
      name: "",
      email: "",
      password: "",
      role: "staff",
      phone: "",
      address: "",
      avatar: null,
    };
  }
  showModal.value = true;
};

const saveUser = async () => {
  try {
    // 🔥 FormData ব্যবহার করতে হবে ফাইল আপলোডের জন্য
    const formData = new FormData();
    formData.append("name", form.value.name);
    formData.append("email", form.value.email);
    formData.append("role", form.value.role);
    formData.append("phone", form.value.phone || "");
    formData.append("address", form.value.address || "");

    if (form.value.password) {
      formData.append("password", form.value.password);
    }

    // নতুন ছবি থাকলে অ্যাপেন্ড করো
    if (avatarFile.value) {
      formData.append("avatar", avatarFile.value);
    }

    let response;

    if (isEditing.value) {
      // Laravel এ PUT রিকোয়েস্টে ফাইল পাঠাতে হলে _method: PUT ব্যবহার করতে হয়
      formData.append("_method", "PUT");
      response = await axios.post(`/users/${form.value.id}`, formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    } else {
      response = await axios.post("/users", formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    }

    if (response.data.status) {
      Swal.fire("Success", response.data.message, "success");
      await fetchUsers();
      // আপডেট করার পর ভিউ রিফ্রেশ করা
      const updatedUser = users.value.find((u) => u.email === form.value.email);
      if (updatedUser) selectUser(updatedUser);
      showModal.value = false;
    }
  } catch (error) {
    console.error(error);
    Swal.fire(
      "Error",
      error.response?.data?.message || "Failed to save user.",
      "error",
    );
  }
};

const deleteUser = async (id) => {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "This user will be permanently deleted!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, delete!",
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/users/${id}`);
      Swal.fire("Deleted!", "User has been removed.", "success");
      selectedUser.value = null;
      fetchUsers();
    } catch (error) {
      Swal.fire("Error", "Failed to delete.", "error");
    }
  }
};

onMounted(() => {
  fetchUsers();
});

onUnmounted(() => {
  if (pollingInterval) clearInterval(pollingInterval);
});
</script>

<template>
  <div class="flex flex-col h-[calc(100vh-6rem)] gap-4">
    <div
      class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 px-1"
    >
      <div>
        <h1
          class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2"
        >
          Staff Management
        </h1>
        <p class="text-sm text-gray-500">
          Monitor performance, track activity, and manage access.
        </p>
      </div>
      <button
        @click="openModal()"
        class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-md shadow-indigo-200 dark:shadow-none flex items-center gap-2 font-semibold transition-all active:scale-95"
      >
        <PlusIcon class="w-5 h-5" /> Add New Staff
      </button>
    </div>

    <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-6 min-h-0">
      <div
        class="lg:col-span-3 bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-200 dark:border-slate-800 flex flex-col h-full overflow-hidden"
      >
        <div
          class="p-4 border-b border-gray-100 dark:border-slate-800 bg-gray-50/50 dark:bg-slate-800/50 shrink-0"
        >
          <div class="relative">
            <MagnifyingGlassIcon
              class="w-5 h-5 text-gray-400 absolute left-3 top-2.5"
            />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search by name..."
              class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
            />
          </div>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-1">
          <div
            v-for="user in filteredUsers"
            :key="user.id"
            @click="selectUser(user)"
            class="group relative p-3 rounded-lg cursor-pointer transition-all duration-200 flex items-center gap-3 border border-transparent"
            :class="
              selectedUser?.id === user.id
                ? 'bg-indigo-50 dark:bg-indigo-900/20'
                : 'hover:bg-gray-50 dark:hover:bg-slate-800'
            "
          >
            <div
              v-if="selectedUser?.id === user.id"
              class="absolute left-0 top-2 bottom-2 w-1 bg-indigo-500 rounded-r-full"
            ></div>

            <div class="relative">
              <img
                v-if="user.avatar"
                :src="getAvatarUrl(user.avatar)"
                class="w-10 h-10 rounded-full object-cover border border-gray-200"
              />
              <div
                v-else
                class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold shadow-sm"
                :class="
                  selectedUser?.id === user.id
                    ? 'bg-indigo-600 text-white'
                    : 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300'
                "
              >
                {{ user.name.charAt(0).toUpperCase() }}
              </div>

              <div
                v-if="
                  selectedUser?.id === user.id &&
                  performanceData?.profile?.is_online
                "
                class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 border-2 border-white dark:border-slate-900 rounded-full"
              ></div>
            </div>

            <div class="flex-1 min-w-0">
              <h4
                class="font-semibold text-sm truncate"
                :class="
                  selectedUser?.id === user.id
                    ? 'text-indigo-700 dark:text-indigo-400'
                    : 'text-gray-700 dark:text-gray-200'
                "
              >
                {{ user.name }}
              </h4>
              <p class="text-xs text-gray-400 truncate flex items-center gap-1">
                <BriefcaseIcon class="w-3 h-3" /> {{ user.role }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-9 flex flex-col h-full overflow-hidden">
        <div
          v-if="!selectedUser"
          class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-white dark:bg-slate-900 rounded-xl border border-gray-200 border-dashed"
        >
          <UserIcon class="w-16 h-16 opacity-20 mb-3" />
          <p class="text-sm font-medium">
            Select a staff member to view details
          </p>
        </div>

        <div
          v-else
          class="flex-1 overflow-y-auto custom-scrollbar pr-1 flex flex-col gap-6"
        >
          <div
            class="bg-white dark:bg-slate-900 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-slate-800 relative overflow-hidden shrink-0"
          >
            <div
              class="absolute top-0 right-0 w-64 h-64 bg-indigo-50 dark:bg-indigo-900/10 rounded-full -translate-y-1/2 translate-x-1/2 blur-3xl pointer-events-none"
            ></div>

            <div
              class="relative flex flex-col md:flex-row justify-between items-start md:items-center gap-6"
            >
              <div class="flex items-center gap-5">
                <div class="relative">
                  <img
                    v-if="selectedUser.avatar"
                    :src="getAvatarUrl(selectedUser.avatar)"
                    class="w-20 h-20 rounded-2xl object-cover shadow-lg border-2 border-white dark:border-slate-800"
                  />
                  <div
                    v-else
                    class="w-20 h-20 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white text-3xl font-bold shadow-lg shadow-indigo-200 dark:shadow-none"
                  >
                    {{ selectedUser.name.charAt(0).toUpperCase() }}
                  </div>

                  <div
                    class="absolute -bottom-2 -right-2 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-full shadow-sm border border-gray-100 dark:border-slate-700 flex items-center gap-1"
                  >
                    <span
                      v-if="performanceData?.profile?.is_online"
                      class="flex items-center gap-1 text-[10px] font-bold text-green-600 uppercase"
                    >
                      <span class="relative flex h-2 w-2">
                        <span
                          class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"
                        ></span>
                        <span
                          class="relative inline-flex rounded-full h-2 w-2 bg-green-500"
                        ></span>
                      </span>
                      Online
                    </span>
                    <span
                      v-else
                      class="flex items-center gap-1 text-[10px] font-bold text-gray-400 uppercase"
                    >
                      <span class="h-2 w-2 rounded-full bg-gray-300"></span>
                      Offline
                    </span>
                  </div>
                </div>

                <div>
                  <h2
                    class="text-2xl font-bold text-gray-800 dark:text-white mb-1"
                  >
                    {{ selectedUser.name }}
                  </h2>
                  <div
                    class="flex flex-wrap items-center gap-3 text-sm text-gray-500"
                  >
                    <span
                      class="flex items-center gap-1.5 bg-gray-100 dark:bg-slate-800 px-2.5 py-1 rounded-md text-xs font-medium uppercase tracking-wide"
                    >
                      {{ selectedUser.role }}
                    </span>
                    <span class="flex items-center gap-1">
                      <EnvelopeIcon class="w-4 h-4 text-gray-400" />
                      {{ selectedUser.email }}
                    </span>
                    <span
                      v-if="!performanceData?.profile?.is_online"
                      class="flex items-center gap-1 text-xs"
                    >
                      <ClockIcon class="w-4 h-4 text-gray-400" /> Seen:
                      {{ performanceData?.profile?.last_seen || "N/A" }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-2 self-end md:self-auto">
                <button
                  @click="openModal(selectedUser)"
                  class="px-4 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:border-indigo-300 text-gray-700 dark:text-gray-300 rounded-lg shadow-sm font-medium flex items-center gap-2 text-sm transition-colors"
                >
                  <PencilSquareIcon class="w-4 h-4" /> Edit
                </button>
                <button
                  @click="deleteUser(selectedUser.id)"
                  class="px-4 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:border-red-300 hover:text-red-600 text-gray-700 dark:text-gray-300 rounded-lg shadow-sm font-medium flex items-center gap-2 text-sm transition-colors"
                >
                  <TrashIcon class="w-4 h-4" /> Delete
                </button>
              </div>
            </div>
          </div>

          <div
            v-if="loadingStats"
            class="grid grid-cols-1 md:grid-cols-3 gap-4 shrink-0"
          >
            <div
              v-for="i in 3"
              :key="i"
              class="h-32 bg-gray-100 dark:bg-slate-800 rounded-xl animate-pulse"
            ></div>
          </div>

          <template v-else-if="performanceData">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 shrink-0">
              <div
                class="bg-indigo-600 text-white p-6 rounded-xl shadow-lg shadow-indigo-200 dark:shadow-none relative overflow-hidden group"
              >
                <div class="relative z-10">
                  <p
                    class="text-indigo-100 text-xs font-bold uppercase tracking-wider mb-2"
                  >
                    Today's Performance
                  </p>
                  <h3 class="text-3xl font-bold">
                    ৳
                    {{
                      Number(
                        performanceData.sales_performance.daily,
                      ).toLocaleString()
                    }}
                  </h3>
                  <p class="text-xs text-indigo-200 mt-2">Daily Revenue</p>
                </div>
                <ChartBarIcon
                  class="absolute -right-6 -bottom-6 w-32 h-32 text-white opacity-10 group-hover:opacity-20 transition-opacity"
                />
              </div>

              <div
                class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-slate-800 hover:border-indigo-100 transition-colors"
              >
                <div class="flex justify-between items-start mb-2">
                  <p
                    class="text-gray-500 text-xs font-bold uppercase tracking-wider"
                  >
                    Current Month
                  </p>
                  <span class="p-1.5 bg-green-50 text-green-600 rounded-md">
                    <ChartBarIcon class="w-4 h-4" />
                  </span>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white">
                  ৳
                  {{
                    Number(
                      performanceData.sales_performance.monthly,
                    ).toLocaleString()
                  }}
                </h3>
                <p
                  class="text-xs text-green-600 font-medium mt-2 flex items-center gap-1"
                >
                  <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                  Active Selling
                </p>
              </div>

              <div
                class="bg-white dark:bg-slate-900 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-slate-800 hover:border-indigo-100 transition-colors"
              >
                <div class="flex justify-between items-start mb-2">
                  <p
                    class="text-gray-500 text-xs font-bold uppercase tracking-wider"
                  >
                    Lifetime Sales
                  </p>
                  <span class="p-1.5 bg-gray-50 text-gray-600 rounded-md">
                    <BanknotesIcon class="w-4 h-4" />
                  </span>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white">
                  ৳
                  {{
                    Number(
                      performanceData.sales_performance.lifetime,
                    ).toLocaleString()
                  }}
                </h3>
                <p class="text-xs text-gray-400 mt-2">
                  Across
                  {{ performanceData.sales_performance.total_invoices }}
                  Invoices
                </p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 shrink-0">
              <div
                class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-200 dark:border-slate-800 overflow-hidden"
              >
                <div
                  class="px-6 py-4 border-b border-gray-100 dark:border-slate-800 bg-gray-50/50 dark:bg-slate-800/50"
                >
                  <h4
                    class="font-bold text-gray-800 dark:text-white flex items-center gap-2 text-sm"
                  >
                    <UserIcon class="w-4 h-4 text-indigo-500" /> Contact
                    Information
                  </h4>
                </div>
                <div class="p-6">
                  <ul class="space-y-5">
                    <li class="flex items-start gap-4">
                      <div
                        class="p-2 bg-indigo-50 dark:bg-slate-800 rounded-lg text-indigo-600"
                      >
                        <PhoneIcon class="w-5 h-5" />
                      </div>
                      <div>
                        <p
                          class="text-xs text-gray-500 uppercase font-bold mb-0.5"
                        >
                          Phone Number
                        </p>
                        <p
                          class="text-sm font-medium text-gray-800 dark:text-gray-200"
                        >
                          {{
                            performanceData.profile.phone || "No phone added"
                          }}
                        </p>
                      </div>
                    </li>
                    <li class="flex items-start gap-4">
                      <div
                        class="p-2 bg-indigo-50 dark:bg-slate-800 rounded-lg text-indigo-600"
                      >
                        <MapPinIcon class="w-5 h-5" />
                      </div>
                      <div>
                        <p
                          class="text-xs text-gray-500 uppercase font-bold mb-0.5"
                        >
                          Address
                        </p>
                        <p
                          class="text-sm font-medium text-gray-800 dark:text-gray-200"
                        >
                          {{
                            performanceData.profile.address ||
                            "No address added"
                          }}
                        </p>
                      </div>
                    </li>
                    <li class="flex items-start gap-4">
                      <div
                        class="p-2 bg-indigo-50 dark:bg-slate-800 rounded-lg text-indigo-600"
                      >
                        <CalendarDaysIcon class="w-5 h-5" />
                      </div>
                      <div>
                        <p
                          class="text-xs text-gray-500 uppercase font-bold mb-0.5"
                        >
                          Joined Date
                        </p>
                        <p
                          class="text-sm font-medium text-gray-800 dark:text-gray-200"
                        >
                          {{ performanceData.profile.joined_at }}
                        </p>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>

              <div
                class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-200 dark:border-slate-800 overflow-hidden"
              >
                <div
                  class="px-6 py-4 border-b border-gray-100 dark:border-slate-800 bg-gray-50/50 dark:bg-slate-800/50"
                >
                  <h4
                    class="font-bold text-gray-800 dark:text-white flex items-center gap-2 text-sm"
                  >
                    <BanknotesIcon class="w-4 h-4 text-rose-500" /> Expense
                    Overview
                  </h4>
                </div>
                <div class="p-6 flex flex-col gap-4">
                  <div
                    class="flex items-center justify-between p-4 bg-rose-50 dark:bg-rose-900/10 border border-rose-100 dark:border-rose-900/20 rounded-xl"
                  >
                    <div>
                      <p class="text-xs text-rose-600 font-bold uppercase mb-1">
                        Expense (This Month)
                      </p>
                      <h5 class="text-xl font-bold text-rose-700">
                        ৳
                        {{
                          Number(
                            performanceData.expense_report.monthly,
                          ).toLocaleString()
                        }}
                      </h5>
                    </div>
                    <div
                      class="p-2 bg-white dark:bg-rose-900/40 rounded-full text-rose-500"
                    >
                      <ChartBarIcon class="w-6 h-6" />
                    </div>
                  </div>

                  <div
                    class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-xl"
                  >
                    <div>
                      <p class="text-xs text-gray-500 font-bold uppercase mb-1">
                        Total Lifetime Expense
                      </p>
                      <h5
                        class="text-xl font-bold text-gray-800 dark:text-white"
                      >
                        ৳
                        {{
                          Number(
                            performanceData.expense_report.lifetime,
                          ).toLocaleString()
                        }}
                      </h5>
                    </div>
                    <div
                      class="p-2 bg-white dark:bg-slate-700 rounded-full text-gray-400"
                    >
                      <BanknotesIcon class="w-6 h-6" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
      <div
        class="bg-white dark:bg-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden animate-fade-in-up transform transition-all scale-100"
      >
        <div
          class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center bg-gray-50 dark:bg-slate-800/50"
        >
          <h3 class="font-bold text-lg text-gray-800 dark:text-white">
            {{ isEditing ? "Edit Staff Details" : "Add New Staff" }}
          </h3>
          <button
            @click="showModal = false"
            class="text-gray-400 hover:text-red-500 transition-colors"
          >
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>

        <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
          <div class="flex flex-col items-center mb-4">
            <div
              class="relative group cursor-pointer"
              @click="triggerFileInput"
            >
              <div
                class="w-24 h-24 rounded-full overflow-hidden border-4 border-gray-100 dark:border-slate-700 shadow-sm"
              >
                <img
                  v-if="avatarPreview"
                  :src="avatarPreview"
                  class="w-full h-full object-cover"
                />
                <div
                  v-else
                  class="w-full h-full bg-indigo-100 dark:bg-slate-700 flex items-center justify-center text-indigo-500 text-3xl font-bold"
                >
                  {{ form.name ? form.name.charAt(0).toUpperCase() : "?" }}
                </div>
              </div>
              <div
                class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
              >
                <CameraIcon class="w-8 h-8 text-white" />
              </div>
              <div
                class="absolute bottom-0 right-0 bg-white p-1.5 rounded-full shadow border border-gray-200"
              >
                <PencilSquareIcon class="w-4 h-4 text-gray-600" />
              </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
              Click to change profile picture
            </p>
            <input
              type="file"
              ref="fileInput"
              class="hidden"
              accept="image/*"
              @change="handleFileChange"
            />
          </div>

          <div>
            <label
              class="block text-xs font-bold text-gray-500 mb-1.5 uppercase"
              >Full Name</label
            >
            <input
              v-model="form.name"
              type="text"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              placeholder="e.g. John Doe"
            />
          </div>
          <div>
            <label
              class="block text-xs font-bold text-gray-500 mb-1.5 uppercase"
              >Email Address</label
            >
            <input
              v-model="form.email"
              type="email"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              placeholder="user@example.com"
            />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label
                class="block text-xs font-bold text-gray-500 mb-1.5 uppercase"
                >Phone</label
              >
              <input
                v-model="form.phone"
                type="text"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 outline-none focus:ring-2 focus:ring-indigo-500 transition-all"
                placeholder="017..."
              />
            </div>
            <div>
              <label
                class="block text-xs font-bold text-gray-500 mb-1.5 uppercase"
                >Role</label
              >
              <select
                v-model="form.role"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 outline-none focus:ring-2 focus:ring-indigo-500 transition-all"
              >
                <option value="staff">Staff</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>
          <div>
            <label
              class="block text-xs font-bold text-gray-500 mb-1.5 uppercase"
              >Address</label
            >
            <input
              v-model="form.address"
              type="text"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 outline-none focus:ring-2 focus:ring-indigo-500 transition-all"
              placeholder="Dhaka, Bangladesh"
            />
          </div>
          <div>
            <label
              class="block text-xs font-bold text-gray-500 mb-1.5 uppercase"
              >Password
              <span
                v-if="isEditing"
                class="text-gray-400 font-normal normal-case"
                >(Optional)</span
              ></label
            >
            <input
              v-model="form.password"
              type="password"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 outline-none focus:ring-2 focus:ring-indigo-500 transition-all"
              placeholder="******"
            />
          </div>
        </div>
        <div
          class="px-6 py-4 bg-gray-50 dark:bg-slate-900/50 flex justify-end gap-3 border-t border-gray-100 dark:border-slate-700"
        >
          <button
            @click="showModal = false"
            class="px-5 py-2 text-gray-600 font-bold hover:bg-gray-200 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            @click="saveUser"
            class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 shadow-md shadow-indigo-200 dark:shadow-none transition-all"
          >
            Save Changes
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 20px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: #94a3b8;
}
</style>
