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
  HomeIcon,
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
const user = ref(null); // Initially null for skeleton triggering
const isLoading = ref(true); // Loading state

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
onMounted(async () => {
  const savedTheme = localStorage.getItem("theme") || "light";
  isDark.value = savedTheme === "dark";
  document.documentElement.classList.toggle("dark", isDark.value);

  document.addEventListener("click", closeDropdowns);

  // Load User & Notifications
  updateUserFromStorage();
  await fetchNotifications();

  // Fake delay to show skeleton (remove in production if fast)
  setTimeout(() => {
    isLoading.value = false;
  }, 800);

  window.addEventListener("storage", updateUserFromStorage);
  window.addEventListener("user-profile-updated", updateUserFromStorage);
});

onUnmounted(() => {
  document.removeEventListener("click", closeDropdowns);
  window.removeEventListener("storage", updateUserFromStorage);
  window.removeEventListener("user-profile-updated", updateUserFromStorage);
});

const updateUserFromStorage = () => {
  const storedUser = localStorage.getItem("user");
  if (storedUser) {
    user.value = JSON.parse(storedUser);
  }
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

const handleNotificationClick = (id) => {
  isNotificationOpen.value = false;
  goToProduct(id);
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
  if (!path) return null;
  if (path.startsWith("http")) return path;
  return `http://localhost:8000/storage/${path}`;
};
</script>

<template>
  <div
    class="flex h-screen bg-gray-50/30 dark:bg-[#0f172a] font-sans text-slate-600 dark:text-slate-300 transition-colors duration-500 overflow-hidden"
  >
    <Sidebar :isOpen="isSidebarOpen" @close="isSidebarOpen = false" />

    <div
      v-if="isSidebarOpen"
      @click="isSidebarOpen = false"
      class="fixed inset-0 bg-slate-900/50 z-40 lg:hidden backdrop-blur-sm transition-opacity duration-300"
    ></div>

    <div class="flex-1 flex flex-col min-w-0 relative">
      <header
        class="sticky top-0 z-30 h-[70px] px-4 sm:px-6 lg:px-8 flex items-center justify-between bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border-b border-gray-100 dark:border-slate-800 transition-all duration-300"
      >
        <div class="flex items-center gap-4">
          <button
            @click="isSidebarOpen = true"
            class="lg:hidden p-2 -ml-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors"
          >
            <Bars3Icon class="w-6 h-6" />
          </button>

          <div class="flex flex-col justify-center">
            <div
              class="flex items-center gap-2 text-xs font-medium text-slate-400 dark:text-slate-500 mb-0.5"
            >
              <HomeIcon class="w-3 h-3" />
              <span>/</span>
              <span class="capitalize">{{
                route.path.split("/")[1] || "Dashboard"
              }}</span>
            </div>
            <h2
              class="text-lg font-bold text-slate-800 dark:text-white capitalize tracking-tight leading-none"
            >
              {{ pageTitle }}
            </h2>
          </div>
        </div>

        <div class="flex items-center gap-3 sm:gap-5">
          <div
            v-if="isLoading"
            class="hidden md:block w-[240px] h-10 bg-gray-200 dark:bg-slate-800 rounded-full animate-pulse"
          ></div>

          <div v-else class="hidden md:block relative z-50 group">
            <div
              class="flex items-center bg-gray-50 dark:bg-slate-800/50 rounded-full px-4 py-2 border border-gray-200 dark:border-slate-700 focus-within:border-indigo-500/50 focus-within:ring-4 ring-indigo-500/10 focus-within:bg-white dark:focus-within:bg-slate-800 transition-all duration-300 w-[240px] focus-within:w-[320px]"
            >
              <MagnifyingGlassIcon
                class="w-4 h-4 text-slate-400 group-focus-within:text-indigo-500 transition-colors"
              />
              <input
                v-model="searchQuery"
                @input="handleSearchInput"
                type="text"
                placeholder="Search inventory..."
                class="bg-transparent border-none outline-none text-sm ml-2 w-full text-slate-600 dark:text-slate-200 placeholder-slate-400"
              />
              <button
                v-if="searchQuery"
                @click="
                  searchQuery = '';
                  searchResults = [];
                "
                class="text-slate-400 hover:text-red-500 transition-colors"
              >
                <XMarkIcon class="w-4 h-4" />
              </button>
            </div>

            <transition name="dropdown">
              <div
                v-if="searchQuery && (searchResults.length > 0 || isSearching)"
                class="absolute top-full left-0 mt-3 w-full bg-white dark:bg-slate-800 rounded-2xl shadow-xl shadow-slate-200/50 dark:shadow-black/50 border border-gray-100 dark:border-slate-700 overflow-hidden"
              >
                <div
                  v-if="isSearching"
                  class="p-4 text-center text-xs text-slate-500 animate-pulse"
                >
                  Searching...
                </div>
                <div
                  v-else-if="searchResults.length === 0"
                  class="p-4 text-center text-xs text-slate-500"
                >
                  No items found.
                </div>
                <ul v-else class="py-2">
                  <li v-for="product in searchResults" :key="product.id">
                    <button
                      @click="goToProduct(product.id)"
                      class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-indigo-50/50 dark:hover:bg-slate-700/50 transition-colors text-left group/item"
                    >
                      <img
                        :src="getImageUrl(product.image)"
                        class="w-9 h-9 rounded-lg object-cover border border-gray-100 dark:border-slate-600 shadow-sm"
                      />
                      <div class="flex-1 min-w-0">
                        <p
                          class="text-sm font-semibold text-slate-700 dark:text-slate-200 truncate group-hover/item:text-indigo-600 dark:group-hover/item:text-indigo-400 transition-colors"
                        >
                          {{ product.name }}
                        </p>
                        <p class="text-xs text-slate-500">
                          Stock:
                          <span
                            :class="
                              product.stock_quantity < 5
                                ? 'text-red-500 font-bold'
                                : ''
                            "
                            >{{ product.stock_quantity }}</span
                          >
                          | Price: {{ product.selling_price }}
                        </p>
                      </div>
                    </button>
                  </li>
                </ul>
              </div>
            </transition>
          </div>

          <button
            @click="toggleTheme"
            class="p-2.5 rounded-full text-slate-500 hover:text-amber-500 hover:bg-amber-50 dark:text-slate-400 dark:hover:text-amber-400 dark:hover:bg-slate-800 transition-all duration-300 active:scale-95"
          >
            <SunIcon v-if="!isDark" class="w-5 h-5" />
            <MoonIcon v-else class="w-5 h-5" />
          </button>

          <div class="relative">
            <button
              @click="toggleNotification"
              class="relative p-2.5 rounded-full text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:text-slate-400 dark:hover:text-indigo-400 dark:hover:bg-slate-800 transition-all duration-300 active:scale-95"
            >
              <BellIcon class="w-5 h-5" />
              <span
                v-if="!isLoading && unreadCount > 0"
                class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white dark:border-slate-900 animate-ping"
              ></span>
              <span
                v-if="!isLoading && unreadCount > 0"
                class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white dark:border-slate-900"
              ></span>
            </button>
            <transition name="dropdown">
              <div
                v-if="isNotificationOpen"
                class="absolute right-0 mt-4 w-80 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl shadow-slate-200/50 dark:shadow-black/50 border border-gray-100 dark:border-slate-700 overflow-hidden origin-top-right z-50"
              >
                <div
                  class="px-5 py-3.5 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center bg-gray-50/50 dark:bg-slate-800/50"
                >
                  <h3 class="font-bold text-sm text-slate-800 dark:text-white">
                    Notifications
                  </h3>
                  <span
                    class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider"
                    >{{ unreadCount }} New</span
                  >
                </div>
                <div class="max-h-[300px] overflow-y-auto custom-scrollbar">
                  <div
                    v-if="notifications.length === 0"
                    class="p-8 text-center flex flex-col items-center"
                  >
                    <BellIcon
                      class="w-10 h-10 text-slate-200 dark:text-slate-700 mb-2"
                    />
                    <p class="text-sm text-slate-500">No new notifications.</p>
                  </div>
                  <div
                    v-else
                    class="divide-y divide-gray-50 dark:divide-slate-700/50"
                  >
                    <button
                      v-for="item in notifications"
                      :key="item.id"
                      @click="handleNotificationClick(item.id)"
                      class="w-full text-left px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex gap-4 cursor-pointer group"
                    >
                      <div
                        class="mt-1 flex-shrink-0 w-8 h-8 rounded-full bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center text-amber-500 group-hover:bg-amber-100 dark:group-hover:bg-amber-900/30 transition-colors"
                      >
                        <ExclamationTriangleIcon class="w-4 h-4" />
                      </div>
                      <div>
                        <p
                          class="text-sm font-semibold text-slate-800 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors"
                        >
                          Low Stock Alert
                        </p>
                        <p class="text-xs text-slate-500 mt-0.5">
                          <span
                            class="font-medium text-slate-700 dark:text-slate-300"
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
            class="h-8 w-[1px] bg-gray-200 dark:bg-slate-700 hidden sm:block"
          ></div>

          <div v-if="isLoading" class="flex items-center gap-3">
            <div
              class="w-9 h-9 bg-gray-200 dark:bg-slate-800 rounded-full animate-pulse"
            ></div>
            <div class="hidden md:flex flex-col gap-1">
              <div
                class="h-3 w-20 bg-gray-200 dark:bg-slate-800 rounded animate-pulse"
              ></div>
              <div
                class="h-2 w-12 bg-gray-200 dark:bg-slate-800 rounded animate-pulse"
              ></div>
            </div>
          </div>

          <div v-else class="relative">
            <button
              @click="toggleProfile"
              class="flex items-center gap-3 p-1 pl-1 pr-3 rounded-full hover:bg-gray-100 dark:hover:bg-slate-800 border border-transparent hover:border-gray-200 dark:hover:border-slate-700 transition-all duration-300 group"
            >
              <div
                class="w-9 h-9 rounded-full overflow-hidden shadow-sm ring-2 ring-white dark:ring-slate-900 bg-indigo-50 dark:bg-slate-700 flex items-center justify-center text-indigo-600 dark:text-indigo-300 font-bold text-sm"
              >
                <img
                  v-if="user?.avatar"
                  :src="getImageUrl(user.avatar)"
                  class="w-full h-full object-cover"
                />
                <span v-else>{{ user?.name?.charAt(0).toUpperCase() }}</span>
              </div>

              <div class="hidden md:block text-left leading-tight">
                <p
                  class="text-xs font-bold text-slate-700 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors"
                >
                  {{ user?.name }}
                </p>
                <p class="text-[10px] text-slate-500 capitalize font-medium">
                  {{ user?.role }}
                </p>
              </div>
              <ChevronDownIcon
                class="w-3 h-3 text-slate-400 transition-transform duration-300"
                :class="isProfileOpen ? 'rotate-180 text-indigo-500' : ''"
              />
            </button>

            <transition name="dropdown">
              <div
                v-if="isProfileOpen"
                class="absolute right-0 mt-3 w-56 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl shadow-slate-200/50 dark:shadow-black/50 border border-gray-100 dark:border-slate-700 py-2 origin-top-right z-50"
              >
                <div
                  class="px-5 py-3 border-b border-gray-50 dark:border-slate-700/50 mb-1"
                >
                  <p
                    class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-0.5"
                  >
                    Signed in as
                  </p>
                  <p
                    class="text-sm font-bold text-slate-800 dark:text-white truncate"
                  >
                    {{ user?.email }}
                  </p>
                </div>

                <div class="px-2 space-y-0.5">
                  <router-link
                    to="/profile"
                    @click="isProfileOpen = false"
                    class="flex items-center px-3 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white hover:bg-indigo-50 dark:hover:bg-indigo-500/10 rounded-xl transition-all group"
                  >
                    <UserCircleIcon
                      class="w-4 h-4 mr-3 text-slate-400 group-hover:text-indigo-500"
                    />
                    Profile
                  </router-link>

                  <router-link
                    v-if="user?.role === 'admin'"
                    to="/settings"
                    @click="isProfileOpen = false"
                    class="flex items-center px-3 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white hover:bg-indigo-50 dark:hover:bg-indigo-500/10 rounded-xl transition-all group"
                  >
                    <Cog6ToothIcon
                      class="w-4 h-4 mr-3 text-slate-400 group-hover:text-indigo-500"
                    />
                    Settings
                  </router-link>
                </div>

                <div
                  class="mt-2 pt-2 border-t border-gray-50 dark:border-slate-700/50 px-2"
                >
                  <button
                    @click="handleLogout"
                    class="w-full text-left flex items-center px-3 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/10 rounded-xl transition-all group"
                  >
                    <ArrowRightStartOnRectangleIcon
                      class="w-4 h-4 mr-3 text-red-400 group-hover:text-red-500"
                    />
                    Sign out
                  </button>
                </div>
              </div>
            </transition>
          </div>
        </div>
      </header>

      <main
        class="flex-1 overflow-x-hidden overflow-y-auto p-4 sm:p-6 lg:p-8 relative scroll-smooth"
      >
        <div
          class="fixed top-20 left-0 w-full h-96 bg-gradient-to-b from-indigo-50/50 to-transparent dark:from-slate-900 dark:to-transparent pointer-events-none -z-10 opacity-60"
        ></div>

        <router-view v-slot="{ Component }">
          <transition name="page-fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>
  </div>
</template>

<style scoped>
/* Page Transitions */
.page-fade-enter-active,
.page-fade-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.page-fade-enter-from {
  opacity: 0;
  transform: translateY(10px);
}
.page-fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* Dropdown Animation */
.dropdown-enter-active {
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.dropdown-leave-active {
  transition: all 0.15s ease-in;
}
.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: scale(0.95) translateY(-8px);
}

/* Custom Scrollbar for Dropdowns */
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 20px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #334155;
}
</style>
