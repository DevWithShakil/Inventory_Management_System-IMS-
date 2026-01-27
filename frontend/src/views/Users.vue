<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import {
  UserGroupIcon,
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  MagnifyingGlassIcon,
  ShieldCheckIcon,
  UserIcon,
  XMarkIcon,
} from "@heroicons/vue/24/outline";

const users = ref([]);
const loading = ref(false);
const search = ref("");
const showModal = ref(false);
const isEditing = ref(false);

const form = ref({
  id: null,
  name: "",
  email: "",
  password: "",
  role: "staff", // default role
});

// Fetch Users
const fetchUsers = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/users");
    if (response.data.status) {
      users.value = response.data.data;
    }
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};

onMounted(() => fetchUsers());

// Filter Users
const filteredUsers = computed(() => {
  if (!search.value) return users.value;
  return users.value.filter(
    (u) =>
      u.name.toLowerCase().includes(search.value.toLowerCase()) ||
      u.email.toLowerCase().includes(search.value.toLowerCase()),
  );
});

// Modal Logic
const openModal = (user = null) => {
  if (user) {
    isEditing.value = true;
    // Password field blank rakhbo edit er somoy
    form.value = { ...user, password: "" };
  } else {
    isEditing.value = false;
    form.value = { id: null, name: "", email: "", password: "", role: "staff" };
  }
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

// CRUD
const saveUser = async () => {
  try {
    let response;
    if (isEditing.value) {
      response = await axios.put(`/users/${form.value.id}`, form.value);
    } else {
      response = await axios.post("/users", form.value);
    }

    if (response.data.status) {
      Swal.fire("Success", response.data.message, "success");
      fetchUsers();
      closeModal();
    }
  } catch (error) {
    Swal.fire(
      "Error",
      "Failed to save user. Email might be duplicate.",
      "error",
    );
  }
};

const deleteUser = async (id) => {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "This action cannot be undone!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, delete!",
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/users/${id}`);
      if (response.data.status) {
        Swal.fire("Deleted!", "User has been deleted.", "success");
        fetchUsers();
      } else {
        Swal.fire("Error", response.data.message, "error");
      }
    } catch (error) {
      Swal.fire("Error", "Failed to delete.", "error");
    }
  }
};
</script>

<template>
  <div class="space-y-6">
    <div
      class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"
    >
      <div>
        <h1
          class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2"
        >
          <UserGroupIcon class="w-8 h-8 text-indigo-600" />
          User Management
        </h1>
        <p class="text-sm text-gray-500">Manage admin and staff accounts.</p>
      </div>
      <button
        @click="openModal()"
        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg flex items-center gap-2 transition shadow-lg shadow-indigo-200 dark:shadow-none"
      >
        <PlusIcon class="w-5 h-5" /> Add New User
      </button>
    </div>

    <div
      class="bg-white dark:bg-slate-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700"
    >
      <div class="relative max-w-md">
        <MagnifyingGlassIcon
          class="w-5 h-5 text-gray-400 absolute left-3 top-2.5"
        />
        <input
          v-model="search"
          type="text"
          placeholder="Search users by name or email..."
          class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-transparent focus:ring-2 focus:ring-indigo-500 outline-none"
        />
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden"
    >
      <table class="w-full text-left border-collapse">
        <thead
          class="bg-gray-50 dark:bg-slate-900/50 text-gray-500 dark:text-gray-400 text-xs uppercase font-semibold"
        >
          <tr>
            <th class="px-6 py-4">User Info</th>
            <th class="px-6 py-4">Role</th>
            <th class="px-6 py-4">Created At</th>
            <th class="px-6 py-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody
          class="divide-y divide-gray-100 dark:divide-slate-700 text-gray-700 dark:text-gray-300"
        >
          <tr
            v-for="user in filteredUsers"
            :key="user.id"
            class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition"
          >
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold"
                >
                  {{ user.name.charAt(0).toUpperCase() }}
                </div>
                <div>
                  <p class="font-bold text-gray-800 dark:text-white">
                    {{ user.name }}
                  </p>
                  <p class="text-xs text-gray-500">{{ user.email }}</p>
                </div>
              </div>
            </td>
            <td class="px-6 py-4">
              <span
                class="px-3 py-1 rounded-full text-xs font-bold uppercase inline-flex items-center gap-1"
                :class="
                  user.role === 'admin'
                    ? 'bg-purple-100 text-purple-700'
                    : 'bg-green-100 text-green-700'
                "
              >
                <ShieldCheckIcon v-if="user.role === 'admin'" class="w-3 h-3" />
                <UserIcon v-else class="w-3 h-3" />
                {{ user.role }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm">
              {{ new Date(user.created_at).toLocaleDateString() }}
            </td>
            <td class="px-6 py-4 text-right flex justify-end gap-2">
              <button
                @click="openModal(user)"
                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition"
              >
                <PencilSquareIcon class="w-5 h-5" />
              </button>
              <button
                @click="deleteUser(user.id)"
                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition"
              >
                <TrashIcon class="w-5 h-5" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div
        v-if="filteredUsers.length === 0"
        class="text-center py-10 text-gray-500"
      >
        No users found.
      </div>
    </div>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
      <div
        class="bg-white dark:bg-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden animate-fade-in-up"
      >
        <div
          class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center"
        >
          <h3 class="font-bold text-lg text-gray-800 dark:text-white">
            {{ isEditing ? "Edit User" : "Add New User" }}
          </h3>
          <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300"
              >Full Name</label
            >
            <input
              v-model="form.name"
              type="text"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 outline-none focus:border-indigo-500"
              placeholder="e.g. John Doe"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300"
              >Email Address</label
            >
            <input
              v-model="form.email"
              type="email"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 outline-none focus:border-indigo-500"
              placeholder="user@example.com"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300">
              Password
              <span v-if="isEditing" class="text-xs text-gray-400 font-normal"
                >(Leave blank to keep current)</span
              >
            </label>
            <input
              v-model="form.password"
              type="password"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 outline-none focus:border-indigo-500"
              placeholder="******"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300"
              >Role</label
            >
            <div class="flex gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="form.role"
                  type="radio"
                  value="staff"
                  class="text-indigo-600 focus:ring-indigo-500"
                />
                <span class="text-sm dark:text-gray-300">Staff (POS Only)</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="form.role"
                  type="radio"
                  value="admin"
                  class="text-indigo-600 focus:ring-indigo-500"
                />
                <span class="text-sm dark:text-gray-300"
                  >Admin (Full Access)</span
                >
              </label>
            </div>
          </div>
        </div>

        <div
          class="px-6 py-4 bg-gray-50 dark:bg-slate-900/50 flex justify-end gap-3"
        >
          <button
            @click="closeModal"
            class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition"
          >
            Cancel
          </button>
          <button
            @click="saveUser"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition"
          >
            Save User
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
