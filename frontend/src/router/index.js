import { createRouter, createWebHistory } from 'vue-router'
import Login from '../views/Login.vue'
import Dashboard from '../views/Dashboard.vue'
import MainLayout from '../layouts/MainLayout.vue';
import SalesList from '../views/SalesList.vue';
import PoseConsole from '../views/PoseConsole.vue';
import Inventory from '../views/Inventory.vue';
import Customers from '../views/Customers.vue';
import Settings from '../views/Settings.vue';
import Attributes from '../views/Attributes.vue';
import Suppliers from '../views/Suppliers.vue';
import Purchases from '../views/Purchases.vue';
import PurchaseCreate from '../views/PurchaseCreate.vue';
import Profile from '../views/Profile.vue';
import Users from '../views/Users.vue';
import Coupons from '../views/Coupons.vue';
import ExpenseList from '../views/ExpenseList.vue';
import ExpenseCategories from '../views/ExpenseCategories.vue';
import BarcodeGenerator from '../views/BarcodeGenerator.vue';

const routes = [
    {
        path: '/',
        name: 'Login',
        component: Login,
        meta: { guest: true, title: 'Login' }
    },
    {
        path: '/',
        component: MainLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: 'dashboard',
                name: 'Dashboard',
                component: Dashboard,
                meta: { title: 'Dashboard' }
            },
            {
                path: '/sales',
                name: 'SalesList',
                component: SalesList,
                meta: { title: 'Sales History' }
            },
            {
                path: '/pos',
                name: 'PosConsole',
                component: PoseConsole,
                meta: { title: 'POS Terminal' }
            },
            {
                path: '/inventory',
                name: 'Inventory',
                component: Inventory,
                meta: { title: 'Inventory Management' }
            },
            {
                path: '/customers',
                name: 'Customers',
                component: Customers,
                meta: { title: 'Customer List' }
            },
            {
                path: '/settings',
                name: 'Settings',
                component: Settings,
                meta: { title: 'System Settings' }
            },
            {
                path: '/attributes',
                name: 'attributes',
                component: Attributes,
                meta: { title: 'Product Attributes' }
            },
            {
                path: '/suppliers',
                name: 'suppliers',
                component: Suppliers,
                meta: { title: 'Supplier Management' }
            },
            {
                path: '/purchases',
                name: 'purchases',
                component: Purchases,
                meta: { title: 'Purchase History' }
            },
            {
                path: '/purchases/create',
                name: 'purchase-create',
                component: PurchaseCreate,
                meta: { title: 'New Purchase' }
            },
            {
                path: '/profile',
                name: 'user_profile',
                component: Profile,
                meta: { requiresAuth: true, title: 'My Profile' }
            },
            {
                path: '/users',
                name: 'users',
                component: Users,
                meta: { requiresAuth: true, role: 'admin', title: 'Staff Management' }
            },
            {
                path: '/coupons',
                name: 'coupons',
                component: Coupons,
                meta: { requiresAuth: true, role: 'admin', title: 'Coupons & Offers' }
            },
            {
                path: '/expenses',
                name: 'expenses',
                component: ExpenseList,
                meta: { title: 'Expense Manager' }
            },
            {
                path: '/expense-categories',
                name: 'expense-categories',
                component: ExpenseCategories,
                meta: { title: 'Expense Categories' }
            },
            {
                path: '/barcode',
                name: 'BarcodeGenerator',
                component: BarcodeGenerator,
                meta: { title: 'Barcode Generator' }
            }
        ]
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes
})

router.beforeEach((to, from, next) => {
    const appName = 'Smart IMS';
    document.title = to.meta.title ? `${to.meta.title} | ${appName}` : appName;
    const token = localStorage.getItem('token');
    if (to.meta.requiresAuth && !token) {
        next('/');
    } else if (to.meta.guest && token) {
        next('/dashboard');
    } else {
        next();
    }
});

export default router