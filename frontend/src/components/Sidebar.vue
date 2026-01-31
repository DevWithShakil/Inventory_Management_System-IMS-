<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from "vue";
import { useRoute } from "vue-router";
import axios from "../axios";
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
  TicketIcon,
  CurrencyDollarIcon,
  QrCodeIcon,
} from "@heroicons/vue/24/outline";

const route = useRoute();

// --- State ---
const user = ref(JSON.parse(localStorage.getItem("user") || "{}"));
const role = user.value.role;
const openMenu = ref("");
const settings = ref(null);

// --- Reactivity for User Profile ---
const updateUserFromStorage = () => {
  user.value = JSON.parse(localStorage.getItem("user") || "{}");
};

const fetchSettings = async () => {
  try {
    const response = await axios.get("/settings");
    if (response.data.status) {
      settings.value = response.data.data;
    }
  } catch (error) {
    console.error("Failed to load settings", error);
  }
};

// Image Helper
const getImageUrl = (path) => {
  if (!path) return null;
  if (path.startsWith("http")) return path;
  return `http://localhost:8000/storage/${path}`;
};

onMounted(() => {
  fetchSettings();
  window.addEventListener("storage", updateUserFromStorage);
  window.addEventListener("user-profile-updated", updateUserFromStorage);
});

onUnmounted(() => {
  window.removeEventListener("storage", updateUserFromStorage);
  window.removeEventListener("user-profile-updated", updateUserFromStorage);
});

// --- Menu Structure ---
const menuItems = computed(() => {
  const items = [
    {
      name: "Dashboard",
      path: "/dashboard",
      icon: Squares2X2Icon,
      roles: ["admin", "staff"],
    },
    {
      name: "Sales",
      icon: BanknotesIcon,
      roles: ["admin", "staff"],
      children: [
        {
          name: "POS Console",
          path: "/pos",
          icon: ShoppingBagIcon,
          roles: ["admin", "staff"],
        },
        {
          name: "Sales History",
          path: "/sales",
          icon: ListBulletIcon,
          roles: ["admin", "staff"],
        },
      ],
    },
    {
      name: "Expenses",
      icon: CurrencyDollarIcon,
      roles: ["admin", "staff"],
      children: [
        {
          name: "All Expenses",
          path: "/expenses",
          icon: ListBulletIcon,
          roles: ["admin", "staff"],
        },
        {
          name: "Add Expense",
          path: "/expenses?action=add",
          icon: PlusCircleIcon,
          roles: ["admin", "staff"],
        },
      ],
    },

    {
      name: "Inventory",
      icon: CubeIcon,
      roles: ["admin", "staff"],
      children: [
        {
          name: "Product List",
          path: "/inventory",
          icon: ListBulletIcon,
          roles: ["admin"],
        },
        {
          name: "Add Product",
          path: "/inventory?action=add",
          icon: PlusCircleIcon,
          roles: ["admin"],
        },
        {
          name: "Print Labels",
          path: "/barcode",
          icon: QrCodeIcon,
          roles: ["admin", "staff"],
        },
      ],
    },

    {
      name: "Purchases",
      icon: ClipboardDocumentCheckIcon,
      roles: ["admin"],
      children: [
        {
          name: "All Purchases",
          path: "/purchases",
          icon: ListBulletIcon,
          roles: ["admin"],
        },
        {
          name: "New Purchase",
          path: "/purchases/create",
          icon: PlusCircleIcon,
          roles: ["admin"],
        },
      ],
    },
    {
      name: "Attributes",
      icon: TagIcon,
      roles: ["admin"],
      children: [
        {
          name: "Categories",
          path: "/attributes?tab=categories",
          icon: TagIcon,
          roles: ["admin"],
        },
        {
          name: "Brands",
          path: "/attributes?tab=brands",
          icon: SwatchIcon,
          roles: ["admin"],
        },
        {
          name: "Units",
          path: "/attributes?tab=units",
          icon: ScaleIcon,
          roles: ["admin"],
        },
      ],
    },
    {
      name: "Suppliers",
      icon: TruckIcon,
      roles: ["admin"],
      children: [
        {
          name: "All Suppliers",
          path: "/suppliers",
          icon: ListBulletIcon,
          roles: ["admin"],
        },
        {
          name: "Add Supplier",
          path: "/suppliers?action=add",
          icon: PlusCircleIcon,
          roles: ["admin"],
        },
      ],
    },
    {
      name: "Customers",
      icon: UsersIcon,
      roles: ["staff", "admin"],
      children: [
        {
          name: "All Customers",
          path: "/customers",
          icon: ListBulletIcon,
          roles: ["staff", "admin"],
        },
        {
          name: "Add Customer",
          path: "/customers?action=add",
          icon: PlusCircleIcon,
          roles: ["staff", "admin"],
        },
      ],
    },
    {
      name: "Coupons & Offers",
      icon: TicketIcon,
      roles: ["admin"],
      path: "/coupons",
    },
    {
      name: "User Management",
      path: "/users",
      icon: UsersIcon,
      roles: ["admin"],
    },
    {
      name: "Settings",
      path: "/settings",
      icon: Cog6ToothIcon,
      roles: ["admin"],
    },
  ];

  return items.reduce((acc, item) => {
    if (item.roles.includes(role)) {
      if (item.children) {
        const filteredChildren = item.children.filter(
          (child) => !child.roles || child.roles.includes(role),
        );
        if (filteredChildren.length > 0) {
          acc.push({ ...item, children: filteredChildren });
        }
      } else {
        acc.push(item);
      }
    }
    return acc;
  }, []);
});

defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(["close"]);

const toggleMenu = (menuName) => {
  openMenu.value = openMenu.value === menuName ? "" : menuName;
};

watch(
  () => route.path,
  (newPath) => {
    menuItems.value.forEach((item) => {
      if (item.children) {
        const hasChild = item.children.some(
          (child) => child.path.split("?")[0] === newPath,
        );
        if (hasChild) openMenu.value = item.name;
      }
    });
  },
  { immediate: true },
);

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
      'fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static flex flex-col',
      isOpen ? 'translate-x-0' : '-translate-x-full',
    ]"
  >
    <div
      class="h-20 flex items-center justify-between px-6 border-b border-gray-100 dark:border-slate-800 flex-shrink-0"
    >
      <router-link to="/dashboard" class="flex items-center gap-2">
        <img
          v-if="settings?.logo"
          :src="getImageUrl(settings.logo)"
          alt="Logo"
          class="h-12 w-auto object-contain transition-all hover:scale-105"
        />

        <h1
          v-else
          class="text-2xl font-bold text-gray-800 dark:text-white tracking-tight"
        >
          {{ settings?.company_name || "Smart" }}
          <span v-if="!settings?.company_name" class="text-indigo-600"
            >IMS</span
          >
        </h1>
      </router-link>

      <button
        @click="$emit('close')"
        class="lg:hidden text-gray-500 hover:text-red-500 transition"
      >
        <XMarkIcon class="w-6 h-6" />
      </button>
    </div>

    <nav class="p-4 space-y-1 overflow-y-auto flex-1 custom-scrollbar">
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
      class="p-4 border-t border-gray-100 dark:border-slate-800 bg-gray-50/50 dark:bg-slate-900/50 flex-shrink-0"
    >
      <div class="flex items-center gap-3">
        <div
          class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-sm overflow-hidden ring-2 ring-white dark:ring-slate-800 shadow-sm"
        >
          <img
            v-if="user.avatar"
            :src="getImageUrl(user.avatar)"
            class="w-full h-full object-cover"
          />
          <span v-else>{{ user.name?.charAt(0).toUpperCase() }}</span>
        </div>

        <div class="overflow-hidden">
          <p
            class="text-sm font-bold text-gray-700 dark:text-gray-200 truncate"
          >
            {{ user.name }}
          </p>
          <p class="text-xs text-gray-500 capitalize">
            {{ user.role }} Account
          </p>
        </div>
      </div>
    </div>
  </aside>
</template>

<style scoped>
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
