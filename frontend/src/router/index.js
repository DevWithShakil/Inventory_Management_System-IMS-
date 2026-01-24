import { createRouter, createWebHistory } from 'vue-router'
import Login from '../views/Login.vue'
import Dashboard from '../views/Dashboard.vue'
import MainLayout from '../layouts/MainLayout.vue';
import SalesList from '../views/SalesList.vue';
import PoseConsole from '../views/PoseConsole.vue';
import Inventory from '../views/Inventory.vue';

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