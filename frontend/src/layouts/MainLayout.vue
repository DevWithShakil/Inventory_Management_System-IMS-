<script setup>
import { ref, onMounted, computed, onUnmounted } from "vue";
import Sidebar from "../components/Sidebar.vue";
import {
  Bars3Icon,
  SunIcon,
  MoonIcon,
  ArrowRightStartOnRectangleIcon,
  BellIcon,
  MagnifyingGlassIcon,
  UserCircleIcon,
  ChevronDownIcon,
  Cog6ToothIcon,
  ExclamationTriangleIcon,
  XMarkIcon,
} from "@heroicons/vue/24/outline";
import { useRouter, useRoute } from "vue-router";
import axios from "../axios";

const router = useRouter();
const route = useRoute();

// State
const isSidebarOpen = ref(false);
const isDark = ref(false);
const isProfileOpen = ref(false);
const isNotificationOpen = ref(false);
const user = ref(JSON.parse(localStorage.getItem("user") || "{}"));

// Search & Notification State
const searchQuery = ref("");
const searchResults = ref([]);
const isSearching = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);

// --- Dynamic Page Title ---
const pageTitle = computed(() => {
  return route.name ? route.name.toString().replace(/_/g, " ") : "Dashboard";
});

// --- Theme & Event Logic ---
onMounted(() => {
  const savedTheme = localStorage.getItem("theme") || "light";
  isDark.value = savedTheme === "dark";
  document.documentElement.classList.toggle("dark", isDark.value);

  document.addEventListener("click", closeDropdowns);

  // Initial Fetch
  fetchNotifications();

  // Listen for storage changes
  window.addEventListener("storage", updateUserFromStorage);
  window.addEventListener("user-profile-updated", updateUserFromStorage);
});

onUnmounted(() => {
  document.removeEventListener("click", closeDropdowns);
  window.removeEventListener("storage", updateUserFromStorage);
  window.removeEventListener("user-profile-updated", updateUserFromStorage);
});

const updateUserFromStorage = () => {
  user.value = JSON.parse(localStorage.getItem("user") || "{}");
};

const toggleTheme = () => {
  isDark.value = !isDark.value;
  document.documentElement.classList.toggle("dark", isDark.value);
  localStorage.setItem("theme", isDark.value ? "dark" : "light");
};

// --- Dropdown Logic ---
const toggleProfile = (e) => {
  e.stopPropagation();
  isProfileOpen.value = !isProfileOpen.value;
  isNotificationOpen.value = false;
};

const toggleNotification = (e) => {
  e.stopPropagation();
  isNotificationOpen.value = !isNotificationOpen.value;
  isProfileOpen.value = false;
};

const closeDropdowns = () => {
  isProfileOpen.value = false;
  isNotificationOpen.value = false;
  if (!searchQuery.value) searchResults.value = [];
};

// --- Notifications ---
const fetchNotifications = async () => {
  try {
    const response = await axios.get("/reports/low-stock");
    if (response.data.status) {
      notifications.value = response.data.data;
      unreadCount.value = response.data.data.length;
    }
  } catch (error) {
    console.error("Notification fetch error", error);
  }
};

// --- Navigation Helpers ---
const goToProduct = (id) => {
  searchQuery.value = "";
  searchResults.value = [];
  router.push(`/inventory?highlight=${id}`);
};

// 🔥 New: Handle Notification Click
const handleNotificationClick = (id) => {
  isNotificationOpen.value = false; // Close dropdown
  goToProduct(id); // Redirect to product
};

// --- Search Logic ---
let debounceTimeout = null;
const handleSearchInput = () => {
  if (debounceTimeout) clearTimeout(debounceTimeout);

  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    return;
  }

  isSearching.value = true;
  debounceTimeout = setTimeout(async () => {
    try {
      const response = await axios.get(
        `/products/search?query=${searchQuery.value}`,
      );
      searchResults.value = response.data;
    } catch (error) {
      console.error(error);
    } finally {
      isSearching.value = false;
    }
  }, 300);
};

// --- Logout ---
const handleLogout = async () => {
  try {
    await axios.post("/logout");
  } catch (e) {}
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  router.push("/");
};

// Image Helper
const getImageUrl = (path) => {
  if (!path) return "https://placehold.co/40x40?text=IMG";
  if (path.startsWith("http")) return path;
  return `http://localhost:8000/storage/${path}`;
};
</script>

<template>
  <div
    class="flex h-screen bg-gray-50/50 dark:bg-slate-950 font-sans text-gray-900 dark:text-gray-100 transition-colors duration-300"
  >
    <Sidebar :isOpen="isSidebarOpen" @close="isSidebarOpen = false" />

    <div
      v-if="isSidebarOpen"
      @click="isSidebarOpen = false"
      class="fixed inset-0 bg-gray-900/60 z-40 lg:hidden backdrop-blur-sm transition-opacity"
    ></div>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
      <header
        class="sticky top-0 z-30 h-16 px-4 sm:px-6 lg:px-8 flex items-center justify-between bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-gray-200 dark:border-slate-800 shadow-sm transition-all"
      >
        <div class="flex items-center gap-4">
          <button
            @click="isSidebarOpen = true"
            class="lg:hidden p-2 -ml-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition"
          >
            <Bars3Icon class="w-6 h-6" />
          </button>

          <div class="flex flex-col">
            <h2
              class="text-lg font-bold text-gray-800 dark:text-white capitalize tracking-tight leading-tight"
            >
              {{ pageTitle }}
            </h2>
            <p class="text-[10px] text-gray-400 font-medium hidden sm:block">
              {{
                user.role === "admin"
                  ? "Administrator Panel"
                  : "Sales Associate Portal"
              }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-4">
          <div class="hidden md:block relative z-50">
            <div
              class="flex items-center bg-gray-100 dark:bg-slate-800 rounded-full px-3 py-1.5 border border-transparent focus-within:border-indigo-500 focus-within:ring-2 ring-indigo-500/20 transition-all"
            >
              <MagnifyingGlassIcon class="w-4 h-4 text-gray-400" />
              <input
                v-model="searchQuery"
                @input="handleSearchInput"
                type="text"
                placeholder="Search products..."
                class="bg-transparent border-none outline-none text-sm ml-2 w-32 focus:w-64 transition-all text-gray-600 dark:text-gray-300 placeholder-gray-400"
              />
              <button
                v-if="searchQuery"
                @click="
                  searchQuery = '';
                  searchResults = [];
                "
                class="text-gray-400 hover:text-red-500"
              >
                <XMarkIcon class="w-4 h-4" />
              </button>
            </div>

            <div
              v-if="searchQuery && (searchResults.length > 0 || isSearching)"
              class="absolute top-full left-0 mt-2 w-full min-w-[300px] bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-100 dark:border-slate-700 overflow-hidden"
            >
              <div
                v-if="isSearching"
                class="p-4 text-center text-xs text-gray-500"
              >
                Searching...
              </div>
              <div
                v-else-if="searchResults.length === 0"
                class="p-4 text-center text-xs text-gray-500"
              >
                No products found.
              </div>
              <ul v-else class="py-1">
                <li v-for="product in searchResults" :key="product.id">
                  <button
                    @click="goToProduct(product.id)"
                    class="w-full flex items-center gap-3 px-4 py-2 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition text-left"
                  >
                    <img
                      :src="getImageUrl(product.image)"
                      class="w-8 h-8 rounded object-cover border border-gray-200 dark:border-slate-600"
                    />
                    <div class="flex-1 min-w-0">
                      <p
                        class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate"
                      >
                        {{ product.name }}
                      </p>
                      <p class="text-xs text-gray-500">
                        Stock: {{ product.stock_quantity }} | Price:
                        {{ product.selling_price }}
                      </p>
                    </div>
                  </button>
                </li>
              </ul>
            </div>
          </div>

          <button
            @click="toggleTheme"
            class="p-2 rounded-full text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-800 transition"
          >
            <SunIcon v-if="!isDark" class="w-5 h-5" />
            <MoonIcon v-else class="w-5 h-5" />
          </button>

          <div class="relative">
            <button
              @click="toggleNotification"
              class="relative p-2 rounded-full text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-800 transition"
            >
              <BellIcon class="w-5 h-5" />
              <span
                v-if="unreadCount > 0"
                class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-slate-900 animate-pulse"
              ></span>
            </button>

            <transition name="dropdown">
              <div
                v-if="isNotificationOpen"
                class="absolute right-0 mt-2 w-80 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-100 dark:border-slate-700 overflow-hidden origin-top-right z-50"
              >
                <div
                  class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center"
                >
                  <h3 class="font-bold text-sm text-gray-800 dark:text-white">
                    Notifications
                  </h3>
                  <span
                    class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold"
                    >{{ unreadCount }} New</span
                  >
                </div>
                <div class="max-h-64 overflow-y-auto">
                  <div
                    v-if="notifications.length === 0"
                    class="p-4 text-center text-sm text-gray-500"
                  >
                    No new notifications.
                  </div>
                  <div
                    v-else
                    class="divide-y divide-gray-100 dark:divide-slate-700"
                  >
                    <button
                      v-for="item in notifications"
                      :key="item.id"
                      @click="handleNotificationClick(item.id)"
                      class="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition flex gap-3 cursor-pointer"
                    >
                      <div class="mt-1 flex-shrink-0">
                        <ExclamationTriangleIcon
                          class="w-5 h-5 text-amber-500"
                        />
                      </div>
                      <div>
                        <p
                          class="text-sm font-semibold text-gray-800 dark:text-gray-200"
                        >
                          Low Stock Alert
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                          <span
                            class="font-bold text-gray-700 dark:text-gray-300"
                            >{{ item.name }}</span
                          >
                          is running low. Only
                          <span class="text-red-500 font-bold">{{
                            item.stock_quantity
                          }}</span>
                          left.
                        </p>
                      </div>
                    </button>
                  </div>
                </div>
              </div>
            </transition>
          </div>

          <div
            class="h-6 w-px bg-gray-200 dark:bg-slate-700 hidden sm:block"
          ></div>

          <div class="relative">
            <button
              @click="toggleProfile"
              class="flex items-center gap-2 p-1 pl-2 pr-3 rounded-full hover:bg-gray-50 dark:hover:bg-slate-800 border border-transparent hover:border-gray-200 dark:hover:border-slate-700 transition"
            >
              <div
                class="w-8 h-8 rounded-full overflow-hidden shadow-md ring-2 ring-white dark:ring-slate-900 bg-indigo-600 flex items-center justify-center text-white font-bold text-sm"
              >
                <img
                  v-if="user.avatar"
                  :src="getImageUrl(user.avatar)"
                  class="w-full h-full object-cover"
                />
                <span v-else>{{ user.name?.charAt(0).toUpperCase() }}</span>
              </div>

              <div class="hidden md:block text-left leading-tight">
                <p class="text-xs font-bold text-gray-700 dark:text-gray-200">
                  {{ user.name }}
                </p>
                <p class="text-[10px] text-gray-500 capitalize">
                  {{ user.role }}
                </p>
              </div>
              <ChevronDownIcon
                class="w-4 h-4 text-gray-400 transition-transform duration-200"
                :class="isProfileOpen ? 'rotate-180' : ''"
              />
            </button>

            <transition name="dropdown">
              <div
                v-if="isProfileOpen"
                class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-gray-100 dark:border-slate-700 py-1 origin-top-right z-50"
              >
                <div
                  class="px-4 py-3 border-b border-gray-100 dark:border-slate-700"
                >
                  <p class="text-xs text-gray-500">Signed in as</p>
                  <p
                    class="text-sm font-bold text-gray-900 dark:text-white truncate"
                  >
                    {{ user.email }}
                  </p>
                </div>

                <div class="py-1">
                  <router-link
                    to="/profile"
                    @click="isProfileOpen = false"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700/50"
                  >
                    <UserCircleIcon class="w-4 h-4 mr-2" /> Profile
                  </router-link>

                  <router-link
                    v-if="user.role === 'admin'"
                    to="/settings"
                    @click="isProfileOpen = false"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700/50"
                  >
                    <Cog6ToothIcon class="w-4 h-4 mr-2" /> Settings
                  </router-link>
                </div>

                <div
                  class="py-1 border-t border-gray-100 dark:border-slate-700"
                >
                  <button
                    @click="handleLogout"
                    class="w-full text-left flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/10 transition"
                  >
                    <ArrowRightStartOnRectangleIcon class="w-4 h-4 mr-2" />
                    Sign out
                  </button>
                </div>
              </div>
            </transition>
          </div>
        </div>
      </header>

      <main
        class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50/50 dark:bg-slate-950 p-4 sm:p-6 lg:p-8 relative"
      >
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}
.fade-enter-from {
  opacity: 0;
  transform: translateY(10px);
}
.fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: scale(0.95) translateY(-10px);
}
</style>
