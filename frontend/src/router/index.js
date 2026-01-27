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

const routes = [
    {
        path: '/',
        name: 'Login',
        component: Login,
        meta: { guest: true }
    },
    {
        path: '/',
        component: MainLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: 'dashboard',
                name: 'Dashboard',
                component: Dashboard
            },
            {
                path: '/sales',
                name: 'SalesList',
                component: SalesList
            },
            {
                path: '/pos',
                name: 'PosConsole',
                component: PoseConsole
            },
            {
                path: '/inventory',
                name: 'Inventory',
                component: Inventory
            },
            {
                path: '/customers',
                name: 'Customers',
                component: Customers
            },
            {
                path: '/settings',
                name: 'Settings',
                component: Settings
            },

            {
                path: '/attributes',
                name: 'attributes',
                component: Attributes
            },
            {
                path: '/suppliers',
                name: 'suppliers',
                component: Suppliers
            },
            {
                path: '/purchases',
                name: 'purchases',
                component: Purchases
            },

            {
                path: '/purchases/create',
                name: 'purchase-create',
                component: PurchaseCreate
            },

            {
                path: '/profile',
                name: 'user_profile',
                component: Profile,
                meta: { requiresAuth: true }
            },

            {
                path: '/users',
                name: 'users',
                component: Users,
                meta: { requiresAuth: true, role: 'admin' }
            }

        ]
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes
})

router.beforeEach((to, from, next) => {
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