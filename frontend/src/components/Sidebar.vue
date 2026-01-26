<script setup>
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";
import {
  Squares2X2Icon,
  ShoppingBagIcon,
  CubeIcon,
  UsersIcon,
  Cog6ToothIcon,
  XMarkIcon,
  TagIcon,
  TruckIcon,
  ClipboardDocumentCheckIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  ListBulletIcon,
  PlusCircleIcon,
  BanknotesIcon,
  SwatchIcon,
  ScaleIcon,
} from "@heroicons/vue/24/outline";

const route = useRoute();
const user = JSON.parse(localStorage.getItem("user") || "{}");
const role = user.role;

// Open Menu State
const openMenu = ref("");

// --- Menu Structure ---
const menuItems = computed(() => {
  const items = [
    {
      name: "Dashboard",
      path: "/dashboard",
      icon: Squares2X2Icon,
      roles: ["admin", "staff"],
    },
    // --- 1. Sales Dropdown ---
    {
      name: "Sales",
      icon: BanknotesIcon,
      roles: ["admin", "staff"],
      children: [
        { name: "POS Console", path: "/pos", icon: ShoppingBagIcon },
        { name: "Sales History", path: "/sales", icon: ListBulletIcon },
      ],
    },
    // --- 2. Purchase Dropdown ---
    {
      name: "Purchases",
      icon: ClipboardDocumentCheckIcon,
      roles: ["admin"],
      children: [
        { name: "All Purchases", path: "/purchases", icon: ListBulletIcon },
        {
          name: "New Purchase",
          path: "/purchases/create",
          icon: PlusCircleIcon,
        },
      ],
    },
    // --- 3. Inventory Dropdown ---
    {
      name: "Inventory",
      icon: CubeIcon,
      roles: ["admin"],
      children: [
        { name: "Product List", path: "/inventory", icon: ListBulletIcon },
        {
          name: "Add Product",
          path: "/inventory?action=add",
          icon: PlusCircleIcon,
        },
      ],
    },
    // --- 4. Attributes Dropdown ---
    {
      name: "Attributes",
      icon: TagIcon,
      roles: ["admin"],
      children: [
        {
          name: "Categories",
          path: "/attributes?tab=categories",
          icon: TagIcon,
        },
        { name: "Brands", path: "/attributes?tab=brands", icon: SwatchIcon },
        { name: "Units", path: "/attributes?tab=units", icon: ScaleIcon },
      ],
    },
    // --- 5. Suppliers Dropdown ---
    {
      name: "Suppliers",
      icon: TruckIcon,
      roles: ["admin"],
      children: [
        { name: "All Suppliers", path: "/suppliers", icon: ListBulletIcon },
        //  Link updated with query parameter
        {
          name: "Add Supplier",
          path: "/suppliers?action=add",
          icon: PlusCircleIcon,
        },
      ],
    },
    // --- 6. Customers Dropdown ---
    {
      name: "Customers",
      icon: UsersIcon,
      roles: ["staff", "admin"],
      children: [
        { name: "All Customers", path: "/customers", icon: ListBulletIcon },
        //  Link updated with query parameter
        {
          name: "Add Customer",
          path: "/customers?action=add",
          icon: PlusCircleIcon,
        },
      ],
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

// Toggle Logic
const toggleMenu = (menuName) => {
  openMenu.value = openMenu.value === menuName ? "" : menuName;
};

// Auto Open Menu based on Route
watch(
  () => route.path,
  (newPath) => {
    menuItems.value.forEach((item) => {
      if (item.children) {
        // Check matching path ignoring query params
        const hasChild = item.children.some(
          (child) => child.path.split("?")[0] === newPath,
        );
        if (hasChild) openMenu.value = item.name;
      }
    });
  },
  { immediate: true },
);

// Helper: Check active link handling query params
const isActive = (path) => {
  if (path.includes("?")) {
    return route.fullPath === path;
  }
  return route.path === path;
};
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

        <div v-for="item in menuItems" :key="item.name" class="mb-1">
          <div v-if="item.children">
            <button
              @click="toggleMenu(item.name)"
              class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group font-medium text-sm hover:bg-gray-50 dark:hover:bg-slate-800"
              :class="
                openMenu === item.name
                  ? 'text-indigo-600 dark:text-indigo-400 bg-gray-50 dark:bg-slate-800'
                  : 'text-gray-600 dark:text-gray-400'
              "
            >
              <div class="flex items-center">
                <component
                  :is="item.icon"
                  class="w-5 h-5 mr-3 transition-colors"
                  :class="
                    openMenu === item.name
                      ? 'text-indigo-600 dark:text-indigo-400'
                      : 'text-gray-400 group-hover:text-gray-600'
                  "
                />
                {{ item.name }}
              </div>
              <component
                :is="
                  openMenu === item.name ? ChevronDownIcon : ChevronRightIcon
                "
                class="w-4 h-4 text-gray-400 transition-transform"
              />
            </button>

            <div
              v-show="openMenu === item.name"
              class="mt-1 ml-4 border-l-2 border-gray-100 dark:border-slate-800 pl-2 space-y-1"
            >
              <router-link
                v-for="child in item.children"
                :key="child.name"
                :to="child.path"
                class="flex items-center px-3 py-2 rounded-lg text-sm transition-colors"
                :class="
                  isActive(child.path)
                    ? 'text-indigo-600 bg-indigo-50 dark:bg-indigo-900/20 font-bold'
                    : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50 dark:hover:bg-slate-800 dark:text-gray-400'
                "
              >
                <component :is="child.icon" class="w-4 h-4 mr-2 opacity-70" />
                {{ child.name }}
              </router-link>
            </div>
          </div>

          <router-link
            v-else
            :to="item.path"
            class="flex items-center px-3 py-2.5 rounded-lg transition-colors duration-200 group font-medium text-sm"
            :class="
              isActive(item.path)
                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400'
                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
            "
          >
            <component
              :is="item.icon"
              class="w-5 h-5 mr-3 transition-colors"
              :class="
                isActive(item.path)
                  ? 'text-indigo-600 dark:text-indigo-400'
                  : 'text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300'
              "
            />
            {{ item.name }}
          </router-link>
        </div>
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
          <p class="text-xs text-gray-500 capitalize">{{ user.role }}</p>
        </div>
      </div>
    </div>
  </aside>
</template>
