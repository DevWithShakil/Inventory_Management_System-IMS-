<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import {
  Squares2X2Icon,
  ShoppingBagIcon,
  CubeIcon,
  ChartBarIcon,
  UsersIcon,
  Cog6ToothIcon,
  XMarkIcon,
  TagIcon, // Attributes এর জন্য
  TruckIcon, // Suppliers এর জন্য
  ClipboardDocumentCheckIcon, // Purchases এর জন্য
} from "@heroicons/vue/24/outline";

const route = useRoute();
const user = JSON.parse(localStorage.getItem("user") || "{}");
const role = user.role;

const menuItems = computed(() => {
  const items = [
    {
      name: "Dashboard",
      path: "/dashboard",
      icon: Squares2X2Icon,
      roles: ["admin", "staff"],
    },
    {
      name: "POS Console",
      path: "/pos",
      icon: ShoppingBagIcon,
      roles: ["staff", "admin"],
    },
    {
      name: "Sales Report",
      path: "/sales",
      icon: ChartBarIcon,
      roles: ["admin", "staff"],
    },
    // --- New Modules Start ---
    {
      name: "Purchases",
      path: "/purchases",
      icon: ClipboardDocumentCheckIcon,
      roles: ["admin"],
    },
    {
      name: "Inventory",
      path: "/inventory",
      icon: CubeIcon,
      roles: ["admin"],
    },
    {
      name: "Attributes", // Categories, Brands, Units
      path: "/attributes",
      icon: TagIcon,
      roles: ["admin"],
    },
    {
      name: "Suppliers",
      path: "/suppliers",
      icon: TruckIcon,
      roles: ["admin"],
    },
    // --- New Modules End ---
    {
      name: "Customers",
      path: "/customers",
      icon: UsersIcon,
      roles: ["staff", "admin"],
    },
    {
      name: "Settings",
      path: "/settings",
      icon: Cog6ToothIcon,
      roles: ["admin"],
    },
  ];
  return items.filter((item) => item.roles.includes(role));
});

defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(["close"]);
</script>

<template>
  <aside
    :class="[
      'fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static',
      isOpen ? 'translate-x-0' : '-translate-x-full',
    ]"
  >
    <div
      class="h-16 flex items-center justify-between px-6 border-b border-gray-100 dark:border-slate-800"
    >
      <h1
        class="text-xl font-bold text-gray-800 dark:text-white tracking-tight"
      >
        Smart<span class="text-indigo-600">IMS</span>
      </h1>
      <button
        @click="$emit('close')"
        class="lg:hidden text-gray-500 hover:text-red-500 transition"
      >
        <XMarkIcon class="w-6 h-6" />
      </button>
    </div>

    <nav class="p-4 space-y-1 overflow-y-auto h-[calc(100vh-8rem)]">
      <div v-for="section in ['Menu']" :key="section">
        <p
          class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 mt-2"
        >
          {{ section }}
        </p>
        <router-link
          v-for="item in menuItems"
          :key="item.path"
          :to="item.path"
          class="flex items-center px-3 py-2.5 rounded-lg transition-colors duration-200 group font-medium text-sm"
          :class="
            route.path === item.path
              ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400'
              : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
          "
        >
          <component
            :is="item.icon"
            class="w-5 h-5 mr-3 transition-colors"
            :class="
              route.path === item.path
                ? 'text-indigo-600 dark:text-indigo-400'
                : 'text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300'
            "
          />
          {{ item.name }}
        </router-link>
      </div>
    </nav>

    <div
      class="absolute bottom-0 w-full p-4 border-t border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-900/50"
    >
      <div class="flex items-center gap-3">
        <div
          class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-700 dark:text-indigo-300 font-bold text-xs"
        >
          {{ user.name?.charAt(0).toUpperCase() }}
        </div>
        <div class="overflow-hidden">
          <p
            class="text-sm font-semibold text-gray-700 dark:text-gray-200 truncate"
          >
            {{ user.name }}
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-500 capitalize">
            {{ user.role }}
          </p>
        </div>
      </div>
    </div>
  </aside>
</template>
