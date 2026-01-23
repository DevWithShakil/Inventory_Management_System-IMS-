<script setup>
import { ref, onMounted } from "vue";

const isDark = ref(false);

// পেজ লোড হলে চেক করবে আগে থেকে ডার্ক মোড সেভ করা ছিল কিনা
onMounted(() => {
  if (localStorage.getItem("theme") === "dark") {
    document.documentElement.classList.add("dark");
    isDark.value = true;
  }
});

// টগল ফাংশন
const toggleTheme = () => {
  isDark.value = !isDark.value;

  if (isDark.value) {
    document.documentElement.classList.add("dark");
    localStorage.setItem("theme", "dark"); // মেমোরিতে সেভ রাখা
  } else {
    document.documentElement.classList.remove("dark");
    localStorage.setItem("theme", "light");
  }
};
</script>

<template>
  <div class="flex flex-col items-center justify-center min-h-screen space-y-4">
    <h1 class="text-3xl font-bold text-blue-600 dark:text-blue-400">
      Inventory Management System
    </h1>

    <p class="text-gray-600 dark:text-gray-300">
      This is a demo of Dark Mode toggling.
    </p>

    <button
      @click="toggleTheme"
      class="px-4 py-2 font-bold text-white bg-indigo-600 rounded hover:bg-indigo-700 dark:bg-yellow-500 dark:hover:bg-yellow-600 dark:text-black transition"
    >
      {{ isDark ? "Switch to Light ☀️" : "Switch to Dark 🌙" }}
    </button>

    <div class="p-6 bg-white rounded shadow-lg dark:bg-gray-800 w-80">
      <h2 class="text-xl font-bold dark:text-white">Product Card</h2>
      <p class="mt-2 text-gray-500 dark:text-gray-400">
        In Dark mode, this card becomes dark gray.
      </p>
    </div>
  </div>
</template>
