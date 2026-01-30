<script setup>
import { ref, onMounted, computed, reactive } from "vue";
import axios from "../axios";
import Swal from "sweetalert2";
import { useRouter } from "vue-router";
import ProductFormModal from "../components/ProductFormModal.vue";
import {
  PlusIcon,
  TrashIcon,
  ArrowLeftIcon,
  ShoppingCartIcon,
  BanknotesIcon,
} from "@heroicons/vue/24/outline";

const router = useRouter();

// --- State ---
const suppliers = ref([]);
const products = ref([]);
const isSubmitting = ref(false);
const showProductModal = ref(false);

const getLocalDate = () => {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, "0");
  const day = String(today.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

const form = reactive({
  supplier_id: "",
  date: getLocalDate(),
  reference_no: "",
  tax: 0,
  discount: 0,
  paid_amount: 0,
  note: "",
});

const cart = ref([]);
const tempItem = reactive({
  product_id: "",
  quantity: 1,
  unit_cost: 0,
  stock: 0,
});

// --- API Loaders ---
const loadData = async () => {
  try {
    const [supRes, prodRes] = await Promise.all([
      axios.get("/suppliers"),
      axios.get("/products"),
    ]);
    if (supRes.data.status) suppliers.value = supRes.data.data;
    if (prodRes.data.status) products.value = prodRes.data.data;
  } catch (error) {
    console.error("Error loading data", error);
  }
};

// --- Modal Close & Auto Select ---
const handleModalClose = async (shouldRefresh) => {
  showProductModal.value = false;
  if (shouldRefresh) {
    try {
      const response = await axios.get("/products");
      if (response.data.status) {
        products.value = response.data.data;
        if (products.value.length > 0) {
          const newProduct = products.value.reduce((prev, current) =>
            prev.id > current.id ? prev : current,
          );
          if (newProduct) {
            tempItem.product_id = newProduct.id;
            handleProductSelect();
            Swal.fire({
              icon: "success",
              title: "Added & Selected!",
              text: `${newProduct.name}`,
              toast: true,
              position: "top-end",
              showConfirmButton: false,
              timer: 2000,
            });
          }
        }
      }
    } catch (error) {
      console.error(error);
    }
  }
};

// --- Cart Logic ---
const handleProductSelect = () => {
  const product = products.value.find((p) => p.id == tempItem.product_id);
  if (product) {
    tempItem.unit_cost = product.cost_price || 0;
    tempItem.stock = product.stock_quantity || 0;
  } else {
    tempItem.unit_cost = 0;
    tempItem.stock = 0;
  }
};

const addToCart = () => {
  if (!tempItem.product_id || tempItem.quantity <= 0) {
    Swal.fire("Warning", "Select product & valid qty", "warning");
    return;
  }
  const product = products.value.find((p) => p.id == tempItem.product_id);

  const existingIndex = cart.value.findIndex(
    (item) => item.product_id == tempItem.product_id,
  );

  if (existingIndex !== -1) {
    cart.value[existingIndex].quantity += tempItem.quantity;
    cart.value[existingIndex].unit_cost = tempItem.unit_cost;
    cart.value[existingIndex].subtotal =
      cart.value[existingIndex].quantity * tempItem.unit_cost;
  } else {
    cart.value.push({
      product_id: tempItem.product_id,
      name: product.name,
      quantity: tempItem.quantity,
      unit_cost: tempItem.unit_cost,
      subtotal: tempItem.quantity * tempItem.unit_cost,
    });
  }
  tempItem.product_id = "";
  tempItem.quantity = 1;
  tempItem.unit_cost = 0;
  tempItem.stock = 0;
};

const removeFromCart = (index) => {
  cart.value.splice(index, 1);
};

// --- Calculations ---
const subTotal = computed(() =>
  cart.value.reduce((sum, item) => sum + item.subtotal, 0),
);

const grandTotal = computed(() => {
  const tax = parseFloat(form.tax) || 0;
  const discount = parseFloat(form.discount) || 0;
  return subTotal.value + tax - discount;
});

// Due Calculation
const dueAmount = computed(() => {
  const paid = parseFloat(form.paid_amount) || 0;
  return grandTotal.value - paid;
});

// --- Submit Logic ---
const submitPurchase = async () => {
  if (!form.supplier_id)
    return Swal.fire("Error", "Select a Supplier", "error");
  if (cart.value.length === 0)
    return Swal.fire("Error", "Cart is empty", "error");

  // Optional: Prevent paying more than total
  if (form.paid_amount > grandTotal.value) {
    return Swal.fire(
      "Warning",
      "Paid amount cannot exceed Grand Total",
      "warning",
    );
  }

  isSubmitting.value = true;
  try {
    const response = await axios.post("/purchases", {
      ...form,
      items: cart.value,
      due_amount: dueAmount.value, // Sending computed due explicitly
    });
    if (response.data.status) {
      Swal.fire("Success!", "Purchase created successfully.", "success");
      router.push("/purchases");
    }
  } catch (error) {
    Swal.fire("Error", error.response?.data?.message || "Failed.", "error");
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(() => loadData());
</script>

<template>
  <div class="max-w-7xl mx-auto pb-20 space-y-6">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button
          @click="router.back()"
          class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-800 transition"
        >
          <ArrowLeftIcon class="w-6 h-6 text-gray-600 dark:text-gray-300" />
        </button>
        <div>
          <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
            New Purchase
          </h2>
          <p class="text-sm text-gray-500">Create invoice & update stock</p>
        </div>
      </div>
      <div
        class="bg-indigo-600 text-white px-6 py-3 rounded-xl shadow-lg text-right"
      >
        <p class="text-xs opacity-80 uppercase tracking-wider">Grand Total</p>
        <h3 class="text-2xl font-bold">৳ {{ grandTotal.toLocaleString() }}</h3>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-1 space-y-6">
        <div
          class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm space-y-4"
        >
          <h4 class="font-bold text-gray-700 dark:text-gray-200 border-b pb-2">
            Invoice Details
          </h4>
          <div>
            <label class="label">Supplier *</label>
            <select v-model="form.supplier_id" class="input-field">
              <option value="">Select Supplier</option>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">
                {{ s.name }} ({{ s.company_name }})
              </option>
            </select>
          </div>
          <div>
            <label class="label">Date *</label
            ><input v-model="form.date" type="date" class="input-field" />
          </div>

          <div>
            <label class="label">Reference No</label>
            <input
              v-model="form.reference_no"
              type="text"
              class="input-field"
              placeholder="e.g. INV-2026-001"
            />
          </div>
        </div>

        <div
          class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm space-y-4"
        >
          <h4
            class="font-bold text-gray-700 dark:text-gray-200 border-b pb-2 flex items-center gap-2"
          >
            <ShoppingCartIcon class="w-5 h-5 text-indigo-600" /> Add Items
          </h4>

          <div>
            <label class="label">Product</label>
            <div class="flex items-center gap-2">
              <select
                v-model="tempItem.product_id"
                @change="handleProductSelect"
                class="input-field flex-1 min-w-0 truncate"
              >
                <option value="">Select Product</option>
                <option v-for="p in products" :key="p.id" :value="p.id">
                  {{
                    p.name.length > 40
                      ? p.name.substring(0, 40) + "..."
                      : p.name
                  }}
                  (Stock: {{ p.stock_quantity }})
                </option>
              </select>

              <button
                @click="showProductModal = true"
                class="bg-green-600 hover:bg-green-700 text-white px-3 py-2.5 rounded-lg shadow-sm transition shrink-0"
                title="Add New Product"
              >
                <PlusIcon class="w-5 h-5" />
              </button>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">Unit Cost</label
              ><input
                v-model.number="tempItem.unit_cost"
                type="number"
                class="input-field"
                min="0"
              />
            </div>
            <div>
              <label class="label">Quantity</label
              ><input
                v-model.number="tempItem.quantity"
                type="number"
                class="input-field"
                min="1"
              />
            </div>
          </div>

          <button
            @click="addToCart"
            class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg mt-2 flex justify-center items-center gap-2 shadow-md"
          >
            <PlusIcon class="w-5 h-5" /> Add to List
          </button>
        </div>
      </div>

      <div class="lg:col-span-2 flex flex-col h-full space-y-6">
        <div
          class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm overflow-hidden flex-1"
        >
          <table class="w-full text-left">
            <thead
              class="bg-gray-50 dark:bg-slate-800 text-xs font-bold text-gray-500 uppercase"
            >
              <tr>
                <th class="px-6 py-4">Product</th>
                <th class="px-6 py-4 text-center">Cost</th>
                <th class="px-6 py-4 text-center">Qty</th>
                <th class="px-6 py-4 text-right">Total</th>
                <th class="px-6 py-4 text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="cart.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                  No items added yet.
                </td>
              </tr>
              <tr
                v-for="(item, index) in cart"
                :key="index"
                class="border-t border-gray-50 dark:border-slate-800"
              >
                <td class="px-6 py-3 font-medium text-sm">
                  <div class="truncate max-w-[200px]" :title="item.name">
                    {{ item.name }}
                  </div>
                </td>
                <td class="px-6 py-3 text-center">৳ {{ item.unit_cost }}</td>
                <td class="px-6 py-3 text-center">{{ item.quantity }}</td>
                <td class="px-6 py-3 text-right font-bold">
                  ৳ {{ item.subtotal.toLocaleString() }}
                </td>
                <td class="px-6 py-3 text-center">
                  <button
                    @click="removeFromCart(index)"
                    class="text-red-500 hover:text-red-700"
                  >
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm"
        >
          <div class="flex flex-col md:flex-row gap-8">
            <div class="flex-1 space-y-4">
              <div>
                <label class="label">Order Tax</label>
                <input
                  v-model.number="form.tax"
                  type="number"
                  class="input-field"
                  placeholder="0.00"
                />
              </div>
              <div>
                <label class="label">Discount</label>
                <input
                  v-model.number="form.discount"
                  type="number"
                  class="input-field"
                  placeholder="0.00"
                />
              </div>
              <div>
                <label class="label text-emerald-600">Paid Amount</label>
                <div class="relative">
                  <input
                    v-model.number="form.paid_amount"
                    type="number"
                    class="input-field pl-4 border-emerald-200 focus:border-emerald-500 focus:ring-emerald-500"
                    placeholder="Enter amount paid"
                  />
                </div>
              </div>

              <div>
                <label class="label">Note</label>
                <textarea
                  v-model="form.note"
                  class="input-field"
                  rows="2"
                  placeholder="Internal notes..."
                ></textarea>
              </div>
            </div>

            <div class="flex-1 space-y-3 text-right">
              <div class="flex justify-between text-gray-500">
                <span>Subtotal</span>
                <span class="font-bold text-gray-800 dark:text-white"
                  >৳ {{ subTotal.toLocaleString() }}</span
                >
              </div>

              <div class="flex justify-between text-gray-500">
                <span>Tax (+)</span>
                <span class="font-bold text-gray-800 dark:text-white"
                  >৳ {{ form.tax || 0 }}</span
                >
              </div>

              <div class="flex justify-between text-gray-500">
                <span>Discount (-)</span>
                <span class="font-bold text-red-600"
                  >৳ {{ form.discount || 0 }}</span
                >
              </div>

              <div
                class="flex justify-between text-xl font-bold text-indigo-600 border-t pt-3 mt-2"
              >
                <span>Grand Total</span>
                <span>৳ {{ grandTotal.toLocaleString() }}</span>
              </div>

              <div class="flex justify-between text-lg font-bold pt-2">
                <span class="text-gray-600">Paid</span>
                <span class="text-emerald-600"
                  >৳ {{ (form.paid_amount || 0).toLocaleString() }}</span
                >
              </div>

              <div
                class="flex justify-between text-lg font-bold border-t border-dashed pt-2"
              >
                <span :class="dueAmount > 0 ? 'text-rose-600' : 'text-gray-500'"
                  >Due Amount</span
                >
                <span
                  :class="dueAmount > 0 ? 'text-rose-600' : 'text-gray-500'"
                >
                  ৳ {{ dueAmount.toLocaleString() }}
                </span>
              </div>

              <button
                @click="submitPurchase"
                :disabled="isSubmitting"
                class="w-full mt-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg disabled:opacity-70"
              >
                {{ isSubmitting ? "Processing..." : "Submit Purchase" }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ProductFormModal
      :isOpen="showProductModal"
      :product="null"
      @close="handleModalClose"
    />
  </div>
</template>

<style scoped>
.label {
  @apply block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1.5;
}
.input-field {
  @apply w-full px-4 py-2.5 border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition text-sm;
}
</style>
