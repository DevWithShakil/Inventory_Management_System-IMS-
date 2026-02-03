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
  ListBulletIcon,
  PlusCircleIcon,
  BanknotesIcon,
  SwatchIcon,
  ScaleIcon,
  TicketIcon,
  CurrencyDollarIcon,
  QrCodeIcon,
  BriefcaseIcon,
} from "@heroicons/vue/24/outline";

const route = useRoute();

// --- State ---
const user = ref(JSON.parse(localStorage.getItem("user") || "{}"));
const role = user.value.role;
const openMenu = ref("");
const settings = ref(null);

// --- Helper Functions ---
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

// --- Menu Structure with COLOR THEMES ---
const menuItems = computed(() => {
  const items = [
    {
      header: "Overview",
      items: [
        {
          name: "Dashboard",
          path: "/dashboard",
          icon: Squares2X2Icon,
          roles: ["admin", "staff"],
          colorTheme: "indigo", // Default theme
        },
      ],
    },
    {
      header: "Business & POS",
      items: [
        {
          name: "Sales",
          icon: BanknotesIcon,
          roles: ["admin", "staff"],
          colorTheme: "emerald", // Green for money/sales
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
          colorTheme: "emerald",
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
      ],
    },
    {
      header: "Inventory Management",
      items: [
        {
          name: "Inventory",
          icon: CubeIcon,
          roles: ["admin", "staff"],
          colorTheme: "amber", // Orange/Amber for products
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
          colorTheme: "amber",
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
          colorTheme: "amber",
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
          colorTheme: "amber",
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
      ],
    },
    {
      header: "People & Promotions",
      items: [
        {
          name: "Customers",
          icon: UsersIcon,
          roles: ["staff", "admin"],
          colorTheme: "sky", // Blue for people
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
          name: "Coupons",
          icon: TicketIcon,
          roles: ["admin"],
          colorTheme: "rose", // Pink for promotions
          path: "/coupons",
        },
      ],
    },
    {
      header: "System",
      items: [
        {
          name: "Staff",
          path: "/users",
          icon: BriefcaseIcon,
          roles: ["admin"],
          colorTheme: "indigo",
        },
        {
          name: "Settings",
          path: "/settings",
          icon: Cog6ToothIcon,
          roles: ["admin"],
          colorTheme: "indigo",
        },
      ],
    },
  ];

  return items
    .map((section) => {
      const filteredItems = section.items.reduce((acc, item) => {
        if (item.roles && item.roles.includes(role)) {
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
      return { ...section, items: filteredItems };
    })
    .filter((section) => section.items.length > 0);
});

defineProps({
  isOpen: Boolean,
});

defineEmits(["close"]);

const toggleMenu = (menuName) => {
  openMenu.value = openMenu.value === menuName ? "" : menuName;
};

const isActive = (path) => {
  return route.fullPath === path || route.path === path.split("?")[0];
};

const isParentActive = (item) => {
  if (item.children) {
    return item.children.some((child) => isActive(child.path));
  }
  return isActive(item.path);
};

watch(
  () => route.path,
  (newPath) => {
    menuItems.value.forEach((section) => {
      section.items.forEach((item) => {
        if (item.children) {
          const hasChild = item.children.some(
            (child) => child.path.split("?")[0] === newPath,
          );
          if (hasChild) openMenu.value = item.name;
        }
      });
    });
  },
  { immediate: true },
);
</script>

<template>
  <aside
    :class="[
      'fixed inset-y-0 left-0 z-50 w-[280px] bg-white dark:bg-slate-900 border-r border-gray-100 dark:border-slate-800 transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static flex flex-col shadow-xl lg:shadow-none',
      isOpen ? 'translate-x-0' : '-translate-x-full',
    ]"
  >
    <div
      class="h-20 flex items-center justify-between px-6 shrink-0 relative overflow-hidden"
    >
      <div
        class="absolute inset-0 bg-gradient-to-br from-indigo-50/50 to-white dark:from-slate-800/50 dark:to-slate-900 opacity-50"
      ></div>

      <router-link
        to="/dashboard"
        class="flex items-center gap-3 group relative z-10"
      >
        <div
          class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center group-hover:scale-105 transition-transform duration-300 shadow-sm"
        >
          <img
            v-if="settings?.logo"
            :src="getImageUrl(settings.logo)"
            alt="Logo"
            class="h-8 w-auto object-contain brightness-0 invert"
          />
          <span v-else class="text-white font-black text-xl">S</span>
        </div>

        <div class="flex flex-col">
          <h1
            class="text-lg font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-gray-900 to-gray-700 dark:from-white dark:to-gray-300 tracking-tight leading-none"
          >
            {{ settings?.company_name || "Smart IMS" }}
          </h1>
          <span
            class="text-[10px] text-indigo-500/80 dark:text-indigo-400/80 font-bold tracking-wider uppercase mt-0.5"
            >Management Panel</span
          >
        </div>
      </router-link>

      <button
        @click="$emit('close')"
        class="lg:hidden p-1 rounded-md text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors relative z-10"
      >
        <XMarkIcon class="w-6 h-6" />
      </button>
    </div>

    <nav
      class="flex-1 overflow-y-auto custom-scrollbar px-4 py-4 space-y-6 relative z-10"
    >
      <div v-for="(section, idx) in menuItems" :key="idx">
        <p
          v-if="section.header"
          class="px-3 mb-3 text-[11px] font-bold uppercase tracking-widest opacity-70"
          :class="{
            'text-indigo-400': idx === 0 || idx === 4,
            'text-emerald-400': idx === 1,
            'text-amber-400': idx === 2,
            'text-sky-400': idx === 3,
          }"
        >
          {{ section.header }}
        </p>

        <ul class="space-y-1.5">
          <li v-for="item in section.items" :key="item.name">
            <div v-if="item.children">
              <button
                @click="toggleMenu(item.name)"
                class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl transition-all duration-200 group text-sm font-medium border border-transparent relative overflow-hidden"
                :class="[
                  openMenu === item.name || isParentActive(item)
                    ? {
                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400':
                          item.colorTheme === 'emerald',
                        'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400':
                          item.colorTheme === 'amber',
                        'bg-sky-50 text-sky-700 dark:bg-sky-900/20 dark:text-sky-400':
                          item.colorTheme === 'sky',
                        'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400':
                          item.colorTheme === 'indigo',
                      }
                    : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800/50',
                ]"
              >
                <div
                  v-if="openMenu === item.name || isParentActive(item)"
                  class="absolute left-0 top-0 bottom-0 w-1"
                  :class="{
                    'bg-emerald-500': item.colorTheme === 'emerald',
                    'bg-amber-500': item.colorTheme === 'amber',
                    'bg-sky-500': item.colorTheme === 'sky',
                    'bg-indigo-500': item.colorTheme === 'indigo',
                  }"
                ></div>

                <div class="flex items-center gap-3 z-10">
                  <component
                    :is="item.icon"
                    class="w-5 h-5 transition-colors duration-300"
                    :class="[
                      openMenu === item.name || isParentActive(item)
                        ? 'opacity-100'
                        : {
                            'text-emerald-500/70 group-hover:text-emerald-600 dark:text-emerald-400/70':
                              item.colorTheme === 'emerald',
                            'text-amber-500/70 group-hover:text-amber-600 dark:text-amber-400/70':
                              item.colorTheme === 'amber',
                            'text-sky-500/70 group-hover:text-sky-600 dark:text-sky-400/70':
                              item.colorTheme === 'sky',
                            'text-indigo-500/70 group-hover:text-indigo-600 dark:text-indigo-400/70':
                              item.colorTheme === 'indigo',
                          },
                    ]"
                  />
                  <span>{{ item.name }}</span>
                </div>
                <ChevronDownIcon
                  class="w-4 h-4 transition-transform duration-300 opacity-50 group-hover:opacity-100 z-10"
                  :class="{ 'rotate-180': openMenu === item.name }"
                />
              </button>

              <transition
                enter-active-class="transition-all duration-300 ease-in-out overflow-hidden"
                enter-from-class="max-h-0 opacity-0"
                enter-to-class="max-h-96 opacity-100"
                leave-active-class="transition-all duration-200 ease-in-out overflow-hidden"
                leave-from-class="max-h-96 opacity-100"
                leave-to-class="max-h-0 opacity-0"
              >
                <ul
                  v-show="openMenu === item.name"
                  class="mt-1 space-y-1 relative pl-2"
                >
                  <li v-for="child in item.children" :key="child.name">
                    <router-link
                      :to="child.path"
                      class="relative flex items-center pl-9 pr-3 py-2 rounded-lg text-[13px] font-medium transition-all duration-200"
                      :class="[
                        isActive(child.path)
                          ? {
                              'text-emerald-600 bg-emerald-50/50 dark:text-emerald-400 dark:bg-emerald-900/10':
                                item.colorTheme === 'emerald',
                              'text-amber-600 bg-amber-50/50 dark:text-amber-400 dark:bg-amber-900/10':
                                item.colorTheme === 'amber',
                              'text-sky-600 bg-sky-50/50 dark:text-sky-400 dark:bg-sky-900/10':
                                item.colorTheme === 'sky',
                              'text-indigo-600 bg-indigo-50/50 dark:text-indigo-400 dark:bg-indigo-900/10':
                                item.colorTheme === 'indigo',
                            }
                          : 'text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-slate-800/50',
                      ]"
                    >
                      <span
                        class="absolute left-[14px] top-1/2 -translate-y-1/2 w-2 h-2 rounded-full transition-all duration-300"
                        :class="[
                          isActive(child.path)
                            ? {
                                'bg-emerald-500 ring-4 ring-emerald-100 dark:ring-emerald-900/30':
                                  item.colorTheme === 'emerald',
                                'bg-amber-500 ring-4 ring-amber-100 dark:ring-amber-900/30':
                                  item.colorTheme === 'amber',
                                'bg-sky-500 ring-4 ring-sky-100 dark:ring-sky-900/30':
                                  item.colorTheme === 'sky',
                                'bg-indigo-500 ring-4 ring-indigo-100 dark:ring-indigo-900/30':
                                  item.colorTheme === 'indigo',
                              }
                            : 'bg-gray-200 dark:bg-slate-700',
                        ]"
                      ></span>

                      {{ child.name }}
                    </router-link>
                  </li>
                </ul>
              </transition>
            </div>

            <router-link
              v-else
              :to="item.path"
              class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group text-sm font-medium border border-transparent relative overflow-hidden"
              :class="[
                isActive(item.path)
                  ? {
                      'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400':
                        item.colorTheme === 'indigo',
                      'bg-rose-50 text-rose-700 dark:bg-rose-900/20 dark:text-rose-400':
                        item.colorTheme === 'rose',
                    }
                  : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800/50 hover:text-gray-900 dark:hover:text-white',
              ]"
            >
              <div
                v-if="isActive(item.path)"
                class="absolute left-0 top-0 bottom-0 w-1"
                :class="{
                  'bg-indigo-500': item.colorTheme === 'indigo',
                  'bg-rose-500': item.colorTheme === 'rose',
                }"
              ></div>

              <component
                :is="item.icon"
                class="w-5 h-5 transition-colors duration-300 z-10"
                :class="[
                  isActive(item.path)
                    ? 'opacity-100'
                    : {
                        'text-indigo-500/70 group-hover:text-indigo-600 dark:text-indigo-400/70':
                          item.colorTheme === 'indigo',
                        'text-rose-500/70 group-hover:text-rose-600 dark:text-rose-400/70':
                          item.colorTheme === 'rose',
                      },
                ]"
              />
              <span class="z-10">{{ item.name }}</span>
            </router-link>
          </li>
        </ul>
      </div>
    </nav>

    <div
      class="p-4 border-t border-gray-100 dark:border-slate-800 relative z-10"
    >
      <div
        class="absolute inset-0 bg-gradient-to-t from-indigo-50/50 to-white dark:from-slate-900 dark:to-slate-900/50 opacity-50 -z-10"
      ></div>

      <router-link
        to="/settings"
        class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/60 dark:hover:bg-slate-800/60 transition-all group cursor-pointer border border-transparent hover:border-gray-200/50 dark:hover:border-slate-700/50 hover:shadow-sm"
      >
        <div class="relative">
          <div
            class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-500 to-violet-500 flex items-center justify-center text-white font-bold text-sm border-2 border-white dark:border-slate-800 shadow-sm group-hover:scale-105 transition-transform"
          >
            <img
              v-if="user.avatar"
              :src="getImageUrl(user.avatar)"
              class="w-full h-full object-cover rounded-full"
            />
            <span v-else>{{ user.name?.charAt(0).toUpperCase() }}</span>
          </div>
          <span
            class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-white dark:border-slate-900 rounded-full"
          ></span>
        </div>

        <div class="flex-1 min-w-0">
          <p
            class="text-sm font-bold text-gray-800 dark:text-white truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors"
          >
            {{ user.name }}
          </p>
          <p
            class="text-xs text-gray-500 dark:text-slate-400 capitalize truncate flex items-center gap-1"
          >
            <BriefcaseIcon class="w-3 h-3 inline" /> {{ user.role }}
          </p>
        </div>

        <Cog6ToothIcon
          class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 group-hover:rotate-90 transition-all duration-500"
        />
      </router-link>
    </div>
  </aside>

  <div
    v-if="isOpen"
    @click="$emit('close')"
    class="fixed inset-0 bg-gray-900/30 backdrop-blur-[2px] z-40 lg:hidden transition-opacity"
  ></div>
</template>

<style scoped>
/* Slim & Modern Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #e2e8f0;
  border-radius: 20px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: #cbd5e1;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #334155;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: #475569;
}
</style>
