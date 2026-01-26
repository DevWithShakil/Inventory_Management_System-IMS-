<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import PaymentModal from "../components/PaymentModal.vue";
import InvoiceModal from "../components/InvoiceModal.vue";
import { useRouter, useRoute } from "vue-router";

import {
  MagnifyingGlassIcon,
  ShoppingCartIcon,
  Squares2X2Icon,
  TagIcon,
  PlusIcon,
  MinusIcon,
  TrashIcon,
  UserIcon,
  GiftIcon,
  TicketIcon,
  UserPlusIcon,
  PauseIcon,
  ClockIcon,
  BoltIcon,
} from "@heroicons/vue/24/outline";

// --- State ---
const products = ref([]);
const categories = ref([]);
const customers = ref([]);
const cart = ref([]);
const heldOrders = ref([]);
const isLoading = ref(false);

// Refs for Focus Management
const searchInputRef = ref(null);

// Filters & Selections
const searchQuery = ref("");
const selectedCategory = ref("all");
const selectedCustomer = ref(null);

// Loyalty & Coupon
const couponCode = ref("");
const appliedCoupon = ref(null);
const redeemPoints = ref(false);

// Modal States
const showPaymentModal = ref(false);
const showInvoiceModal = ref(false);
const showHeldOrdersModal = ref(false);
const completedSaleData = ref(null);

// --- API Actions ---
const fetchProducts = async () => {
  isLoading.value = true;
  try {
    const response = await axios.get("/products");
    if (response.data.status) products.value = response.data.data;
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const fetchCategories = async () => {
  try {
    const response = await axios.get("/categories");
    if (response.data.status) categories.value = response.data.data;
  } catch (error) {
    console.error(error);
  }
};

const fetchCustomers = async () => {
  try {
    const response = await axios.get("/customers");
    if (response.data.status) customers.value = response.data.data;
  } catch (error) {
    console.error(error);
  }
};

// --- Helper: Load Held Orders from LocalStorage ---
const loadHeldOrders = () => {
  const stored = localStorage.getItem("pos_held_orders");
  if (stored) heldOrders.value = JSON.parse(stored);
};

// --- Feature 1: Barcode Scanner / Auto Add ---
const handleSearchEnter = () => {
  if (!searchQuery.value) return;

  const query = searchQuery.value.toLowerCase();
  let product = products.value.find(
    (p) => p.sku && p.sku.toLowerCase() === query,
  );

  if (!product) {
    product = products.value.find((p) => p.name.toLowerCase() === query);
  }

  if (product) {
    addToCart(product);
    searchQuery.value = "";

    // Sound Effect
    const audio = new Audio(
      "https://codeskulptor-demos.commondatastorage.googleapis.com/pang/pop.mp3",
    );
    audio.volume = 0.5;
    audio.play().catch(() => {});
  } else {
    Swal.fire({
      icon: "error",
      title: "Not Found",
      text: "Product not found with this code",
      toast: true,
      position: "top-end",
      timer: 1500,
      showConfirmButton: false,
    });
  }
};

// --- Feature 2: Hold & Recall Order ---
const holdOrder = () => {
  if (cart.value.length === 0) {
    Swal.fire({ icon: "warning", text: "Cart is empty!" });
    return;
  }

  const orderData = {
    id: Date.now(),
    time: new Date().toLocaleTimeString(),
    items: [...cart.value],
    customer: selectedCustomer.value,
    total: grandTotal.value,
  };

  heldOrders.value.push(orderData);
  localStorage.setItem("pos_held_orders", JSON.stringify(heldOrders.value));
  resetCartState();

  Swal.fire({
    icon: "success",
    title: "Order Held",
    text: "Order saved to recall list (F8 to view)",
    toast: true,
    position: "top-end",
    timer: 1500,
    showConfirmButton: false,
  });
};

const recallOrder = (index) => {
  if (cart.value.length > 0) {
    if (!confirm("Current cart will be replaced. Continue?")) return;
  }

  const order = heldOrders.value[index];

  cart.value = order.items;
  selectedCustomer.value = order.customer;
  heldOrders.value.splice(index, 1);
  localStorage.setItem("pos_held_orders", JSON.stringify(heldOrders.value));

  showHeldOrdersModal.value = false;

  Swal.fire({
    icon: "success",
    title: "Order Restored",
    toast: true,
    position: "top-end",
    timer: 1500,
    showConfirmButton: false,
  });
};

const removeHeldOrder = (index) => {
  heldOrders.value.splice(index, 1);
  localStorage.setItem("pos_held_orders", JSON.stringify(heldOrders.value));
};

// --- Feature 3: Keyboard Shortcuts ---
const handleKeydown = (e) => {
  if (e.key === "F4") {
    e.preventDefault();
    searchInputRef.value?.focus();
  }
  if (e.key === "F8") {
    e.preventDefault();
    if (cart.value.length > 0) holdOrder();
    else if (heldOrders.value.length > 0) showHeldOrdersModal.value = true;
  }
  if (e.key === "F9") {
    e.preventDefault();
    handlePaymentTrigger();
  }
  if (e.key === "Escape") {
    if (showPaymentModal.value) showPaymentModal.value = false;
    else if (showHeldOrdersModal.value) showHeldOrdersModal.value = false;
    else if (cart.value.length > 0) clearCart();
  }
};

// --- Customer Logic ---
const addNewCustomer = async () => {
  const { value: formValues } = await Swal.fire({
    title: "Add New Customer",
    html:
      '<input id="swal-input1" class="swal2-input" placeholder="Name">' +
      '<input id="swal-input2" class="swal2-input" placeholder="Phone Number">',
    focusConfirm: false,
    showCancelButton: true,
    preConfirm: () => [
      document.getElementById("swal-input1").value,
      document.getElementById("swal-input2").value,
    ],
  });
  if (formValues) {
    const [name, phone] = formValues;
    if (!name || !phone) return;

    // In real app, call API to save customer here
    // For now, push to local list
    const newCus = { id: Date.now(), name, phone, points: 0 };
    customers.value.push(newCus);
    selectedCustomer.value = newCus.id;

    Swal.fire({
      icon: "success",
      title: "Customer Added",
      toast: true,
      position: "top-end",
      timer: 2000,
      showConfirmButton: false,
    });
  }
};

// --- Cart Logic ---
const addToCart = (product) => {
  const currentStock = product.stock_quantity || 0;

  if (currentStock <= 0) {
    Swal.fire({
      icon: "warning",
      title: "Out of Stock",
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 1500,
    });
    return;
  }

  const existingItem = cart.value.find((item) => item.id === product.id);
  if (existingItem) {
    if (existingItem.qty < currentStock) {
      existingItem.qty++;
    } else {
      Swal.fire({
        icon: "warning",
        title: "Stock Limit Reached",
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 1500,
      });
    }
  } else {
    cart.value.push({
      ...product,
      qty: 1,
      price: Number(product.selling_price || 0), // Ensure numeric price
    });
  }
};

const removeFromCart = (index) => cart.value.splice(index, 1);

const updateQty = (index, change) => {
  const item = cart.value[index];
  const currentStock = item.stock_quantity || 0;
  const newQty = item.qty + change;
  if (newQty > 0 && newQty <= currentStock) item.qty = newQty;
};

const clearCart = (silent = false) => {
  if (silent) {
    resetCartState();
    return;
  }
  Swal.fire({
    title: "Clear Cart?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    confirmButtonText: "Yes, clear (Esc)",
  }).then((result) => {
    if (result.isConfirmed) resetCartState();
  });
};

const resetCartState = () => {
  cart.value = [];
  couponCode.value = "";
  appliedCoupon.value = null;
  redeemPoints.value = false;
};

// --- Coupon Logic ---
const applyCoupon = () => {
  if (!couponCode.value) return;
  // Demo Coupon Logic - Replace with API call
  if (couponCode.value === "DISCOUNT100") {
    appliedCoupon.value = { code: "DISCOUNT100", amount: 100, type: "fixed" };
    Swal.fire({
      icon: "success",
      title: "Coupon Applied!",
      toast: true,
      position: "top-end",
      timer: 2000,
      showConfirmButton: false,
    });
  } else {
    Swal.fire({
      icon: "error",
      title: "Invalid Coupon",
      toast: true,
      position: "top-end",
      timer: 2000,
      showConfirmButton: false,
    });
    appliedCoupon.value = null;
  }
};

// --- Calculations ---
const currentCustomerData = computed(
  () => customers.value.find((c) => c.id === selectedCustomer.value) || null,
);

const subTotal = computed(() =>
  cart.value.reduce((total, item) => total + item.price * item.qty, 0),
);

const pointsDiscount = computed(() =>
  redeemPoints.value &&
  currentCustomerData.value &&
  currentCustomerData.value.points >= 100
    ? Math.min(currentCustomerData.value.points, subTotal.value)
    : 0,
);

const couponDiscountAmount = computed(() =>
  appliedCoupon.value
    ? appliedCoupon.value.type === "fixed"
      ? appliedCoupon.value.amount
      : (subTotal.value * appliedCoupon.value.amount) / 100
    : 0,
);

const grandTotal = computed(() =>
  Math.max(
    subTotal.value - pointsDiscount.value - couponDiscountAmount.value,
    0,
  ),
);

const potentialPoints = computed(() => Math.floor(grandTotal.value / 100));

const filteredProducts = computed(() => {
  return products.value.filter((product) => {
    const matchesSearch =
      product.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (product.sku &&
        product.sku.toLowerCase().includes(searchQuery.value.toLowerCase()));
    const matchesCategory =
      selectedCategory.value === "all"
        ? true
        : product.category_id === selectedCategory.value;
    return matchesSearch && matchesCategory;
  });
});

// --- Payment & Processing Logic (UPDATED) ---
const handlePaymentTrigger = () => {
  if (cart.value.length === 0) {
    Swal.fire({
      icon: "warning",
      title: "Cart Empty",
      text: "Please add items.",
    });
    return;
  }

  if (!selectedCustomer.value && selectedCustomer.value !== "walk-in") {
    // If you want to force selection. If walk-in is allowed null, remove this check or adjust logic.
    // For this code, we allow null if user didn't pick anything, handled in processSale
  }

  showPaymentModal.value = true;
};

// CORE FUNCTION: Process Sale with Backend
const processSale = async (paymentDetails) => {
  if (cart.value.length === 0) return;

  try {
    // 1. Common Data Payload
    const salePayload = {
      customer_id:
        selectedCustomer.value === "walk-in" ? null : selectedCustomer.value,
      items: cart.value.map((item) => ({
        product_id: item.id,
        quantity: item.qty,
        unit_price: item.price,
        subtotal: item.price * item.qty,
      })),
      sub_total: subTotal.value,
      discount: (pointsDiscount.value || 0) + (couponDiscountAmount.value || 0),
      grand_total: grandTotal.value,
      payment_method: paymentDetails.payment_method,
      note: paymentDetails.note,
    };

    // 2. Logic Split based on Method
    if (paymentDetails.payment_method === "cash") {
      // --- CASH PAYMENT ---
      salePayload.paid_amount = paymentDetails.received_amount;
      salePayload.due_amount = paymentDetails.due_amount;

      const response = await axios.post("/sales", salePayload);

      if (response.data.status) {
        handleSuccess(response.data.data);
      }
    } else {
      Swal.fire({
        title: "Processing...",
        text: "Redirecting to Payment Gateway",
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      const response = await axios.post("/pay-via-ssl", salePayload);
      if (response.data.status === true && response.data.url) {
        window.location.href = response.data.url;
      } else {
        Swal.fire(
          "Error",
          response.data.message || "Failed to initiate payment",
          "error",
        );
      }
    }
  } catch (error) {
    console.error("Sale Error:", error);
    console.log(error.response?.data);

    let msg = "Failed to process sale.";
    if (error.response && error.response.data.message) {
      msg = error.response.data.message;
    }
    Swal.fire({
      icon: "error",
      title: "Error",
      text: msg,
    });
  }
};

// Helper for Cash Success
const handleSuccess = (saleData) => {
  showPaymentModal.value = false;
  resetCartState();
  completedSaleData.value = saleData;
  showInvoiceModal.value = true;
  fetchProducts();
  Swal.fire({
    icon: "success",
    title: "Sale Completed!",
    toast: true,
    position: "top-end",
    timer: 2000,
    showConfirmButton: false,
  });
};

// --- Lifecycle ---
onMounted(async () => {
  fetchProducts();
  fetchCategories();
  fetchCustomers();
  loadHeldOrders();
  window.addEventListener("keydown", handleKeydown);

  const urlParams = new URLSearchParams(window.location.search);
  const isSuccess = urlParams.get("payment_success");
  const saleId = urlParams.get("sale_id");

  if (isSuccess === "true" && saleId) {
    try {
      const response = await axios.get(`/sales/${saleId}`);
      if (response.data.status) {
        resetCartState();
        completedSaleData.value = response.data.data;
        showInvoiceModal.value = true;
        window.history.replaceState(
          {},
          document.title,
          window.location.pathname,
        );
        Swal.fire({
          icon: "success",
          title: "Payment Successful!",
          text: "Thank you for your purchase.",
          timer: 3000,
          showConfirmButton: false,
        });
      }
    } catch (error) {
      console.error("Failed to load invoice details", error);
    }
  } else if (urlParams.get("payment_failed")) {
    Swal.fire({
      icon: "error",
      title: "Payment Failed",
      text: "Transaction was unsuccessful.",
    });
    window.history.replaceState({}, document.title, window.location.pathname);
  }
});

onUnmounted(() => {
  window.removeEventListener("keydown", handleKeydown);
});
</script>

<template>
  <div class="flex h-[calc(100vh-80px)] gap-4 pb-4">
    <div class="w-full lg:w-2/3 flex flex-col gap-4">
      <div
        class="flex flex-col gap-4 bg-white dark:bg-slate-900 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-slate-800"
      >
        <div class="relative w-full">
          <MagnifyingGlassIcon
            class="absolute left-3 top-3 h-5 w-5 text-gray-400"
          />
          <input
            ref="searchInputRef"
            v-model="searchQuery"
            @keyup.enter="handleSearchEnter"
            type="text"
            placeholder="Scan barcode (Enter) or search... (F4)"
            class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500 transition"
          />
        </div>

        <div
          class="flex gap-2 overflow-x-auto pb-1 custom-scrollbar select-none"
        >
          <button
            @click="selectedCategory = 'all'"
            :class="`whitespace-nowrap px-4 py-2 rounded-lg text-sm font-bold transition flex items-center gap-2 border ${selectedCategory === 'all' ? 'bg-indigo-600 text-white border-indigo-600 shadow-md' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'}`"
          >
            <Squares2X2Icon class="w-4 h-4" /> All Items
          </button>
          <button
            v-for="cat in categories"
            :key="cat.id"
            @click="selectedCategory = cat.id"
            :class="`whitespace-nowrap px-4 py-2 rounded-lg text-sm font-bold transition flex items-center gap-2 border ${selectedCategory === cat.id ? 'bg-indigo-600 text-white border-indigo-600 shadow-md' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'}`"
          >
            <TagIcon class="w-4 h-4" /> {{ cat.name }}
          </button>
        </div>
      </div>

      <div
        class="flex-1 bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-200 dark:border-slate-800 p-4 overflow-y-auto custom-scrollbar"
      >
        <div v-if="isLoading" class="flex justify-center items-center h-full">
          <div
            class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600"
          ></div>
        </div>
        <div
          v-else
          class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4"
        >
          <div
            v-for="product in filteredProducts"
            :key="product.id"
            @click="addToCart(product)"
            class="group relative bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-xl overflow-hidden hover:shadow-md cursor-pointer transition-all duration-200 hover:border-indigo-500 active:scale-95"
          >
            <div
              class="h-32 bg-gray-100 dark:bg-slate-700 flex items-center justify-center relative"
            >
              <img
                v-if="product.image"
                :src="
                  product.image.startsWith('http')
                    ? product.image
                    : `http://localhost:8000/storage/${product.image}`
                "
                class="h-full w-full object-cover"
                @error="
                  $event.target.src = 'https://placehold.co/400?text=No+Image'
                "
              />
              <div v-else class="text-gray-400">
                <Squares2X2Icon class="w-10 h-10 opacity-20" />
              </div>
              <span
                class="absolute top-2 right-2 px-2 py-0.5 text-[10px] font-bold rounded-full shadow-sm backdrop-blur-sm"
                :class="
                  (product.stock_quantity || 0) <= (product.alert_quantity || 5)
                    ? 'bg-red-500 text-white'
                    : 'bg-green-500 text-white'
                "
              >
                Qty: {{ product.stock_quantity || 0 }}
              </span>
            </div>
            <div class="p-3">
              <h3
                class="text-sm font-bold text-gray-800 dark:text-white truncate"
              >
                {{ product.name }}
              </h3>
              <p class="text-xs text-gray-500 mb-2 truncate">
                {{ product.sku || "No SKU" }}
              </p>
              <div class="flex justify-between items-center">
                <span class="text-indigo-600 dark:text-indigo-400 font-bold">
                  ৳ {{ Number(product.selling_price || 0).toLocaleString() }}
                </span>
                <button
                  class="p-1.5 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-600 hover:text-white transition"
                >
                  <ShoppingCartIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      class="w-full lg:w-1/3 flex flex-col bg-white dark:bg-slate-900 rounded-xl shadow-lg border border-gray-200 dark:border-slate-800 h-full"
    >
      <div
        class="p-4 border-b border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/50 rounded-t-xl flex justify-between items-center"
      >
        <h2
          class="font-bold text-gray-800 dark:text-white flex items-center gap-2"
        >
          <ShoppingCartIcon class="w-5 h-5 text-indigo-600" /> Current Order
        </h2>
        <div class="flex gap-2">
          <button
            v-if="heldOrders.length > 0"
            @click="showHeldOrdersModal = true"
            class="flex items-center gap-1 text-xs text-amber-600 bg-amber-50 hover:bg-amber-100 font-bold px-2 py-1.5 rounded transition"
            title="Recall Held Orders (F8)"
          >
            <ClockIcon class="w-4 h-4" /> Recall ({{ heldOrders.length }})
          </button>
          <button
            v-if="cart.length > 0"
            @click="clearCart(false)"
            class="text-xs text-red-500 hover:text-red-700 font-medium px-2 py-1 rounded hover:bg-red-50"
          >
            Clear All (Esc)
          </button>
        </div>
      </div>

      <div class="p-3 border-b border-gray-100 dark:border-slate-800">
        <div class="flex gap-2 mb-2">
          <div class="relative flex-1">
            <UserIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
            <select
              v-model="selectedCustomer"
              class="w-full pl-9 pr-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none focus:ring-2 focus:ring-indigo-500 appearance-none cursor-pointer"
            >
              <option :value="null" disabled>Select Customer</option>
              <option value="walk-in">Walk-in Customer</option>
              <option
                v-for="customer in customers"
                :key="customer.id"
                :value="customer.id"
              >
                {{ customer.name }} ({{ customer.phone }})
              </option>
            </select>
          </div>
          <button
            @click="addNewCustomer"
            class="p-2 bg-indigo-50 text-indigo-600 rounded-lg border border-indigo-100"
          >
            <UserPlusIcon class="w-5 h-5" />
          </button>
          <button
            @click="holdOrder"
            class="p-2 bg-amber-50 text-amber-600 rounded-lg border border-amber-100 hover:bg-amber-100 transition"
            title="Hold Order (F8)"
          >
            <PauseIcon class="w-5 h-5" />
          </button>
        </div>

        <div
          v-if="currentCustomerData"
          class="flex items-center justify-between bg-yellow-50 dark:bg-yellow-900/20 p-2 rounded-lg border border-yellow-200 dark:border-yellow-700/50"
        >
          <div
            class="flex items-center gap-2 text-yellow-700 dark:text-yellow-400"
          >
            <GiftIcon class="w-4 h-4" />
            <span class="text-xs font-bold"
              >Points: {{ currentCustomerData.points }}</span
            >
          </div>
          <div
            v-if="currentCustomerData.points >= 100"
            class="flex items-center gap-2"
          >
            <label class="text-xs font-medium cursor-pointer" for="redeem"
              >Redeem</label
            >
            <input
              type="checkbox"
              id="redeem"
              v-model="redeemPoints"
              class="rounded text-indigo-600 focus:ring-indigo-500"
            />
          </div>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto custom-scrollbar p-2">
        <div
          v-if="cart.length === 0"
          class="flex flex-col justify-center items-center text-gray-400 h-full"
        >
          <ShoppingCartIcon class="w-16 h-16 mb-4 opacity-20" />
          <p>Cart is empty</p>
        </div>
        <div v-else class="space-y-2">
          <div
            v-for="(item, index) in cart"
            :key="index"
            class="flex items-center justify-between p-3 bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-lg shadow-sm"
          >
            <div class="flex-1 min-w-0 pr-2">
              <h4
                class="text-sm font-bold text-gray-800 dark:text-white truncate"
              >
                {{ item.name }}
              </h4>
              <p class="text-xs text-gray-500">
                ৳ {{ item.price }} x {{ item.qty }}
              </p>
            </div>
            <div
              class="flex items-center gap-2 bg-gray-100 dark:bg-slate-700 rounded-lg p-1 mr-3"
            >
              <button
                @click="updateQty(index, -1)"
                class="p-1 hover:bg-white rounded-md"
              >
                <MinusIcon class="w-3 h-3" />
              </button>
              <span class="text-xs font-bold w-4 text-center">{{
                item.qty
              }}</span>
              <button
                @click="updateQty(index, 1)"
                class="p-1 hover:bg-white rounded-md"
              >
                <PlusIcon class="w-3 h-3" />
              </button>
            </div>
            <button
              @click="removeFromCart(index)"
              class="text-gray-400 hover:text-red-500"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <div class="bg-gray-50 dark:bg-slate-800/50 border-t border-gray-100">
        <div class="p-3 border-b border-gray-200 dark:border-slate-700">
          <div class="relative flex gap-2">
            <TicketIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
            <input
              v-model="couponCode"
              type="text"
              placeholder="Coupon Code"
              class="w-full pl-9 pr-2 py-2 bg-white dark:bg-slate-900 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-indigo-500"
              :disabled="appliedCoupon"
            />
            <button
              v-if="!appliedCoupon"
              @click="applyCoupon"
              class="px-3 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700"
            >
              Apply
            </button>
            <button
              v-else
              @click="
                () => {
                  appliedCoupon = null;
                  couponCode = '';
                }
              "
              class="px-3 bg-red-500 text-white text-xs font-bold rounded-lg hover:bg-red-600"
            >
              Remove
            </button>
          </div>
          <div
            v-if="appliedCoupon"
            class="mt-1 text-xs text-green-600 font-bold ml-1"
          >
            Coupon Applied! - ৳ {{ couponDiscountAmount }}
          </div>
        </div>

        <div class="p-4 pt-2">
          <div class="space-y-2 mb-4">
            <div
              class="flex justify-between text-sm text-gray-600 dark:text-gray-400"
            >
              <span>Subtotal</span
              ><span>৳ {{ subTotal.toLocaleString() }}</span>
            </div>
            <div
              v-if="pointsDiscount > 0"
              class="flex justify-between text-sm text-yellow-600 font-medium"
            >
              <span>Points Redeemed</span><span>- ৳ {{ pointsDiscount }}</span>
            </div>
            <div
              v-if="couponDiscountAmount > 0"
              class="flex justify-between text-sm text-green-600 font-medium"
            >
              <span>Coupon Discount</span
              ><span>- ৳ {{ couponDiscountAmount }}</span>
            </div>
            <div
              class="flex justify-between text-lg font-bold text-gray-900 dark:text-white border-t border-gray-200 pt-2"
            >
              <span>Total Payable</span
              ><span>৳ {{ grandTotal.toLocaleString() }}</span>
            </div>
            <div class="flex justify-between text-xs text-indigo-500">
              <span>Points to Earn:</span
              ><span>+ {{ potentialPoints }} pts</span>
            </div>
          </div>
          <button
            @click="handlePaymentTrigger"
            :disabled="cart.length === 0"
            class="w-full py-3 bg-indigo-600 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition transform active:scale-95 flex items-center justify-center gap-2"
          >
            <BoltIcon class="w-4 h-4" /> Pay Now (F9)
          </button>
        </div>
      </div>
    </div>

    <PaymentModal
      :isOpen="showPaymentModal"
      :totalAmount="grandTotal"
      :customer="currentCustomerData"
      @close="showPaymentModal = false"
      @submit-payment="processSale"
    />

    <InvoiceModal
      :isOpen="showInvoiceModal"
      :sale="completedSaleData"
      @close="showInvoiceModal = false"
    />

    <div
      v-if="showHeldOrdersModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    >
      <div
        class="bg-white dark:bg-slate-900 w-full max-w-md rounded-xl shadow-2xl overflow-hidden"
      >
        <div
          class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50"
        >
          <h3 class="font-bold text-lg">Held Orders</h3>
          <button
            @click="showHeldOrdersModal = false"
            class="text-gray-400 hover:text-gray-600"
          >
            Close
          </button>
        </div>
        <div class="p-4 max-h-[60vh] overflow-y-auto">
          <div
            v-if="heldOrders.length === 0"
            class="text-center text-gray-400 py-4"
          >
            No held orders found.
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="(order, index) in heldOrders"
              :key="order.id"
              class="border p-3 rounded-lg flex justify-between items-center hover:bg-gray-50"
            >
              <div>
                <p class="font-bold text-sm">
                  Order #{{ index + 1 }} - {{ order.time }}
                </p>
                <p class="text-xs text-gray-500">
                  {{ order.items.length }} Items | Total: ৳{{ order.total }}
                </p>
              </div>
              <div class="flex gap-2">
                <button
                  @click="recallOrder(index)"
                  class="p-1.5 bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100"
                  title="Restore"
                >
                  <ClockIcon class="w-4 h-4" />
                </button>
                <button
                  @click="removeHeldOrder(index)"
                  class="p-1.5 bg-red-50 text-red-600 rounded hover:bg-red-100"
                  title="Delete"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 20px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: #94a3b8;
}
</style>
