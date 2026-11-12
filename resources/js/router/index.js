import { createRouter, createWebHistory } from "vue-router";

// ==========================================
// PUBLIC PAGES
// ==========================================

import Home from "../pages/Home.vue";
import Login from "../pages/Login.vue";
import ForgotPassword from "../pages/ForgotPassword.vue";
import ResetPassword from "../pages/ResetPassword.vue";
import Register from "../pages/Register.vue";

// ==========================================
// OWNER DASHBOARD LAYOUT
// ==========================================

import DashboardLayout from "../layouts/DashboardLayout.vue";

// ==========================================
// OWNER DASHBOARD PAGES
// ==========================================

import Dashboard from "../pages/Dashboard.vue";
import Stores from "../pages/Stores.vue";
import Orders from "../pages/Orders.vue";
import Returns from "../pages/Returns.vue";
import Tickets from "../pages/Tickets.vue";
import TicketDetails from "../pages/TicketDetails.vue";
import KnowledgeBase from "../pages/KnowledgeBase.vue";
import AiSettings from "../pages/AiSettings.vue";
import Analytics from "../pages/Analytics.vue";
import Settings from "../pages/Settings.vue";
import Billing from "../pages/Billing.vue";
import SuperAdminSettings from "../pages/SuperAdminSettings.vue";
// ==========================================
// SUPER ADMIN LAYOUT & PAGES
// ==========================================

import SuperAdminLayout from "../layouts/SuperAdminLayout.vue";
import SuperAdminDashboard from "../pages/SuperAdminDashboard.vue";
import SuperAdminMerchants from "../pages/SuperAdminMerchants.vue";
import SuperAdminUsers from "../pages/SuperAdminUsers.vue";
import SuperAdminPlans from "../pages/Plans.vue";
import SuperAdminSubscriptions from "../pages/SuperAdminSubscriptions.vue";

// ==========================================
// ROUTES
// ==========================================

const routes = [
    // ========================================
    // PUBLIC PAGES
    // ========================================

    {
        path: "/",
        name: "home",
        component: Home,
        meta: {
            guestOnly: true,
        },
    },

    {
        path: "/forgot-password",
        name: "forgot-password",
        component: ForgotPassword,
        meta: { guestOnly: true },
    },

    {
        path: "/reset-password",
        name: "reset-password",
        component: ResetPassword,
        meta: { guestOnly: true },
    },
    {
        path: "/register",
        name: "register",
        component: Register,
        meta: { guestOnly: true },
    },

    {
        path: "/login",
        name: "login",
        component: Login,
        meta: {
            guestOnly: true,
        },
    },

    // ========================================
    // OWNER DASHBOARD
    // ========================================

    {
        path: "/dashboard",
        component: DashboardLayout,
        meta: {
            requiresAuth: true,
            role: "owner",
        },
        children: [
            {
                path: "",
                name: "dashboard",
                component: Dashboard,
            },
            {
                path: "stores",
                name: "dashboard-stores",
                component: Stores,
            },
            {
                path: "orders",
                name: "dashboard-orders",
                component: Orders,
            },
            {
                path: "returns",
                name: "returns",
                component: Returns,
            },
            {
                path: "tickets",
                name: "Tickets",
                component: Tickets,
            },
            {
                path: "tickets/:id",
                name: "TicketDetails",
                component: TicketDetails,
            },
            {
                path: "knowledge-base",
                name: "KnowledgeBase",
                component: KnowledgeBase,
            },
            {
                path: "ai-settings",
                name: "AISettings",
                component: AiSettings,
            },
            {
                path: "analytics",
                name: "Analytics",
                component: Analytics,
            },
            {
                path: "settings",
                name: "Settings",
                component: Settings,
            },
            {
                path: "billing",
                name: "Billing",
                component: Billing,
            },
        ],
    },

    // ========================================
    // SUPER ADMIN DASHBOARD
    // ========================================

    {
        path: "/super-admin",
        component: SuperAdminLayout,
        meta: {
            requiresAuth: true,
            role: "super_admin",
        },
        children: [
            {
                path: "",
                name: "super-admin-dashboard",
                component: SuperAdminDashboard,
            },
            {
                path: "merchants",
                name: "super-admin-merchants",
                component: SuperAdminMerchants,
            },
            {
                path: "users",
                name: "super-admin-users",
                component: SuperAdminUsers,
            },
            // ==================================
            // NEW: PLANS MANAGEMENT
            // ==================================
            {
                path: "plans",
                name: "super-admin-plans",
                component: SuperAdminPlans,
            },
            {
                path: "subscriptions",
                name: "super-admin-subscriptions",
                component: SuperAdminSubscriptions,
            },
            {
                path: "settings",
                name: "super-admin-settings",
                component: SuperAdminSettings,
            },
        ],
    },

    // ========================================
    // UNKNOWN ROUTES
    // ========================================

    {
        path: "/:pathMatch(.*)*",
        redirect: "/",
    },
];

// ==========================================
// CREATE ROUTER
// ==========================================

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// ==========================================
// AUTH HELPERS
// ==========================================

const getUserRole = (user) => {
    return String(user?.role?.name || user?.role || "")
        .toLowerCase()
        .replace(/[\s-]/g, "_");
};

const getDashboardPath = (role) => {
    if (role === "super_admin") {
        return "/super-admin";
    }

    if (role === "owner") {
        return "/dashboard";
    }

    return null;
};

const clearAuth = () => {
    localStorage.removeItem("accessToken");
    localStorage.removeItem("user");
};

// ==========================================
// AUTHENTICATION GUARD
// ==========================================

router.beforeEach(async (to) => {
    const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);

    const guestOnly = to.matched.some((record) => record.meta.guestOnly);

    // Public routes without restrictions
    if (!requiresAuth && !guestOnly) {
        return true;
    }

    const token = localStorage.getItem("accessToken");

    // No token: allow guest pages
    if (!token) {
        if (requiresAuth) {
            return {
                path: "/login",
                query: {
                    redirect: to.fullPath,
                },
            };
        }

        return true;
    }

    // ========================================
    // VERIFY LOGIN WITH LARAVEL
    // ========================================

    try {
        const response = await fetch("/api/auth/me", {
            method: "GET",
            headers: {
                Accept: "application/json",
                Authorization: `Bearer ${token}`,
            },
        });

        // Invalid or expired token
        if (response.status === 401) {
            clearAuth();

            if (requiresAuth) {
                return {
                    path: "/login",
                    query: {
                        redirect: to.fullPath,
                    },
                };
            }

            return true;
        }

        if (!response.ok) {
            // Do not log out on temporary server errors.
            return false;
        }

        const result = await response.json();

        if (!result.success || !result.user) {
            return false;
        }

        const user = result.user;
        const role = getUserRole(user);
        const dashboardPath = getDashboardPath(role);

        localStorage.setItem("user", JSON.stringify(user));

        // ========================================
        // ALREADY LOGGED IN: BLOCK PUBLIC PAGES
        // ========================================

        if (guestOnly) {
            if (dashboardPath) {
                return {
                    path: dashboardPath,
                    replace: true,
                };
            }

            // Authenticated user with unsupported role
            return false;
        }

        // ========================================
        // ROLE-BASED DASHBOARD PROTECTION
        // ========================================

        const requiredRole = to.meta.role;

        if (requiredRole && role !== requiredRole) {
            if (dashboardPath) {
                return {
                    path: dashboardPath,
                    replace: true,
                };
            }

            return false;
        }

        return true;
    } catch (error) {
        console.error("Router Authentication Error:", error);

        // Prevent unauthorized navigation if API is unavailable.
        return false;
    }
});

export default router;
