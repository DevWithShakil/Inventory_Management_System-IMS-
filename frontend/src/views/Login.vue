<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import axios from "../axios";
// Professional Icons import
import {
  EyeIcon,
  EyeSlashIcon,
  EnvelopeIcon,
  LockClosedIcon,
  ArrowRightIcon,
  FingerPrintIcon,
} from "@heroicons/vue/24/outline";

const router = useRouter();

// State
const email = ref("");
const password = ref("");
const showPassword = ref(false);
const isLoading = ref(false);
const errorMessage = ref("");

// Login Logic
const handleLogin = async () => {
  errorMessage.value = "";
  isLoading.value = true;

  try {
    const response = await axios.post("/login", {
      email: email.value,
      password: password.value,
    });

    if (response.data.status) {
      localStorage.setItem("token", response.data.token);
      localStorage.setItem("user", JSON.stringify(response.data.user));
      router.push("/dashboard");
    }
  } catch (error) {
    if (error.response && error.response.status === 401) {
      errorMessage.value =
        "Invalid credentials. Please check your email or password.";
    } else {
      errorMessage.value = "Connection error. Please try again later.";
    }
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <div
    class="min-h-screen w-full flex overflow-hidden bg-gray-50 dark:bg-gray-900 transition-colors duration-500"
  >
    <div
      class="hidden lg:flex w-7/12 relative bg-gray-900 items-center justify-center overflow-hidden"
    >
      <div class="absolute inset-0 w-full h-full">
        <div
          class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-purple-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 animate-blob"
        ></div>
        <div
          class="absolute top-[-10%] right-[-10%] w-[500px] h-[500px] bg-indigo-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 animate-blob animation-delay-2000"
        ></div>
        <div
          class="absolute bottom-[-20%] left-[20%] w-[500px] h-[500px] bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 animate-blob animation-delay-4000"
        ></div>
      </div>

      <div
        class="relative z-10 bg-white/10 backdrop-blur-lg border border-white/20 p-12 rounded-2xl max-w-lg text-center shadow-2xl transform hover:scale-105 transition duration-500"
      >
        <div class="mb-6 flex justify-center">
          <div
            class="h-16 w-16 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-md"
          >
            <FingerPrintIcon class="h-10 w-10 text-white" />
          </div>
        </div>
        <h2 class="text-4xl font-extrabold text-white mb-4 tracking-tight">
          Smart Inventory
        </h2>
        <p class="text-gray-200 text-lg leading-relaxed">
          Experience the next generation of business management. Real-time
          analytics, seamless POS, and intelligent reporting.
        </p>

        <div class="mt-8 flex justify-center gap-4">
          <div
            class="px-4 py-2 bg-green-500/20 border border-green-500/30 rounded-full text-green-300 text-xs font-semibold"
          >
            ▲ 24% Growth
          </div>
          <div
            class="px-4 py-2 bg-blue-500/20 border border-blue-500/30 rounded-full text-blue-300 text-xs font-semibold"
          >
            ✔ 99.9% Uptime
          </div>
        </div>
      </div>

      <div
        class="absolute inset-0 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-20"
      ></div>
    </div>

    <div
      class="w-full lg:w-5/12 flex flex-col justify-center px-8 sm:px-12 lg:px-24 bg-white dark:bg-gray-900 z-20 shadow-[-10px_0_60px_rgba(0,0,0,0.1)]"
    >
      <div class="w-full max-w-md mx-auto space-y-8">
        <div class="text-center lg:text-left">
          <h1
            class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white"
          >
            Welcome Back
          </h1>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Please enter your details to access your dashboard.
          </p>
        </div>

        <div
          v-if="errorMessage"
          class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-300 px-4 py-3 rounded-lg text-sm flex items-center animate-pulse"
        >
          <svg
            class="w-5 h-5 mr-2"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          {{ errorMessage }}
        </div>

        <form @submit.prevent="handleLogin" class="space-y-6">
          <div class="group">
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
              >Email Address</label
            >
            <div class="relative">
              <div
                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
              >
                <EnvelopeIcon
                  class="h-5 w-5 text-gray-400 group-focus-within:text-violet-600 transition-colors"
                />
              </div>
              <input
                v-model="email"
                type="email"
                required
                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-700 rounded-xl leading-5 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 sm:text-sm transition duration-200 shadow-sm"
                placeholder="name@company.com"
              />
            </div>
          </div>

          <div class="group">
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
              >Password</label
            >
            <div class="relative">
              <div
                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
              >
                <LockClosedIcon
                  class="h-5 w-5 text-gray-400 group-focus-within:text-violet-600 transition-colors"
                />
              </div>
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                class="block w-full pl-10 pr-10 py-3 border border-gray-300 dark:border-gray-700 rounded-xl leading-5 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 sm:text-sm transition duration-200 shadow-sm"
                placeholder="••••••••"
              />
              <div
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer"
              >
                <EyeIcon
                  v-if="!showPassword"
                  class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                />
                <EyeSlashIcon
                  v-else
                  class="h-5 w-5 text-violet-600 hover:text-violet-800 transition"
                />
              </div>
            </div>
            <div class="flex items-center justify-end mt-2">
              <a
                href="#"
                class="text-sm font-medium text-violet-600 hover:text-violet-500 dark:text-violet-400"
              >
                Forgot password?
              </a>
            </div>
          </div>

          <button
            type="submit"
            :disabled="isLoading"
            class="w-full flex items-center justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-xl disabled:opacity-70 disabled:cursor-not-allowed"
          >
            <svg
              v-if="isLoading"
              class="animate-spin -ml-1 mr-2 h-5 w-5 text-white"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              ></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              ></path>
            </svg>

            <span v-else class="flex items-center">
              Sign In to Dashboard <ArrowRightIcon class="ml-2 h-5 w-5" />
            </span>
          </button>
        </form>

        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
          Don't have an account?
          <a
            href="#"
            class="font-bold text-violet-600 hover:text-violet-500 dark:text-violet-400 transition"
          >
            Contact Admin
          </a>
        </p>
      </div>

      <div class="mt-10 lg:hidden text-center text-xs text-gray-400">
        &copy; 2026 Smart Inventory. Version 1.0
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Custom Blob Animation */
@keyframes blob {
  0% {
    transform: translate(0px, 0px) scale(1);
  }
  33% {
    transform: translate(30px, -50px) scale(1.1);
  }
  66% {
    transform: translate(-20px, 20px) scale(0.9);
  }
  100% {
    transform: translate(0px, 0px) scale(1);
  }
}
.animate-blob {
  animation: blob 7s infinite;
}
.animation-delay-2000 {
  animation-delay: 2s;
}
.animation-delay-4000 {
  animation-delay: 4s;
}
</style>
