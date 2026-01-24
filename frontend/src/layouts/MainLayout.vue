<script setup>
import { ref, onMounted } from "vue";
import Sidebar from "../components/Sidebar.vue";
import {
  Bars3Icon,
  SunIcon,
  MoonIcon,
  ArrowRightStartOnRectangleIcon,
} from "@heroicons/vue/24/outline";
import { useRouter } from "vue-router";
import axios from "../axios";

const router = useRouter();
const isSidebarOpen = ref(false);
const isDark = ref(false);

// Dark Mode Logic
onMounted(() => {
  const savedTheme = localStorage.getItem("theme") || "light";
  isDark.value = savedTheme === "dark";
  document.documentElement.classList.toggle("dark", isDark.value);
});

const toggleTheme = () => {
  isDark.value = !isDark.value;
  document.documentElement.classList.toggle("dark", isDark.value);
  localStorage.setItem("theme", isDark.value ? "dark" : "light");
};

const handleLogout = async () => {
  try {
    await axios.post("/logout");
  } catch (e) {}
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  router.push("/");
};
</script>

<template>
  <div
    class="flex h-screen bg-gray-50 dark:bg-slate-950 font-sans text-gray-900 dark:text-gray-100 transition-colors duration-300"
  >
    <Sidebar :isOpen="isSidebarOpen" @close="isSidebarOpen = false" />

    <div
      v-if="isSidebarOpen"
      @click="isSidebarOpen = false"
      class="fixed inset-0 bg-gray-900/50 z-40 lg:hidden backdrop-blur-sm"
    ></div>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <header
        class="bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800 sticky top-0 z-30 h-16 flex items-center justify-between px-4 sm:px-6 lg:px-8 shadow-sm"
      >
        <div class="flex items-center gap-4">
          <button
            @click="isSidebarOpen = true"
            class="lg:hidden p-2 text-gray-500 hover:bg-gray-100 rounded-md"
          >
            <Bars3Icon class="w-6 h-6" />
          </button>
          <h2
            class="text-lg font-semibold text-gray-800 dark:text-white hidden sm:block"
          >
            Dashboard
          </h2>
        </div>

        <div class="flex items-center gap-3">
          <button
            @click="toggleTheme"
            class="p-2 rounded-full text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-800 transition"
          >
            <SunIcon v-if="!isDark" class="w-5 h-5" />
            <MoonIcon v-else class="w-5 h-5" />
          </button>

          <div class="h-6 w-px bg-gray-200 dark:bg-slate-700 mx-1"></div>

          <button
            @click="handleLogout"
            class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 rounded-md transition"
          >
            <ArrowRightStartOnRectangleIcon class="w-4 h-4" />
            <span class="hidden sm:inline">Logout</span>
          </button>
        </div>
      </header>

      <main class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>
  </div>
</template>

<style>
/* Subtle Fade Animation only */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
