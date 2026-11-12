<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";

import {
    Users,
    Store,
    ShoppingCart,
    Ticket,
    RotateCcw,
    Link2,
    LoaderCircle,
    LayoutDashboard,
    ShieldCheck,
    RefreshCw,
} from "lucide-vue-next";

import { useTheme } from "../composables/useTheme";

const router = useRouter();
const { isDark } = useTheme();

// ==========================================
// STATE
// ==========================================

const stats = ref(null);
const user = ref(null);
const loading = ref(true);
const error = ref("");

// ==========================================
// AUTHENTICATION
// ==========================================

const getStoredUser = () => {
    try {
        const stored = localStorage.getItem("user");

        if (!stored) return null;

        return JSON.parse(stored);
    } catch {
        return null;
    }
};

// ==========================================
// FETCH DASHBOARD
// ==========================================

const fetchDashboard = async () => {
    loading.value = true;
    error.value = "";

    try {
        const token = localStorage.getItem("accessToken");

        if (!token) {
            router.replace("/login");
            return;
        }

        user.value = getStoredUser();

        const response = await fetch("/api/super-admin/dashboard", {
            method: "GET",
            headers: {
                Accept: "application/json",
                Authorization: `Bearer ${token}`,
            },
        });

        if (response.status === 401) {
            localStorage.removeItem("accessToken");
            localStorage.removeItem("user");

            router.replace("/login");
            return;
        }

        if (response.status === 403) {
            router.replace("/dashboard");
            return;
        }

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || "Failed to load dashboard.");
        }

        // Laravel API returns "overview"
        const overview = result.overview;

        if (!overview || typeof overview !== "object") {
            throw new Error("Dashboard statistics missing from API response.");
        }

        stats.value = overview;
    } catch (err) {
        console.error("Super Admin Dashboard Error:", err);

        error.value = err.message || "Unable to fetch dashboard statistics.";
    } finally {
        loading.value = false;
    }
};

// ==========================================
// STATISTICS CARDS
// ==========================================

const cards = computed(() => [
    {
        title: "Total Merchants",
        value: stats.value?.total_merchants,
        icon: Users,
    },
    {
        title: "Total Stores",
        value: stats.value?.total_stores,
        icon: Store,
    },
    {
        title: "Connected Stores",
        value: stats.value?.connected_stores,
        icon: Link2,
    },
    {
        title: "Total Orders",
        value: stats.value?.total_orders,
        icon: ShoppingCart,
    },
    {
        title: "Total Tickets",
        value: stats.value?.total_tickets,
        icon: Ticket,
    },
    {
        title: "Open Tickets",
        value: stats.value?.open_tickets,
        icon: Ticket,
    },
    {
        title: "Total Returns",
        value: stats.value?.total_returns,
        icon: RotateCcw,
    },
    {
        title: "Pending Returns",
        value: stats.value?.pending_returns,
        icon: RotateCcw,
    },
]);

// ==========================================
// FORMAT NUMBER
// ==========================================

const formatValue = (value) => {
    if (value === null || value === undefined) {
        return "—";
    }

    return Number(value).toLocaleString("en-US");
};

// ==========================================
// LIFECYCLE
// ==========================================

onMounted(fetchDashboard);
</script>

<template>
    <div class="w-full transition-colors duration-300">
        <!-- ======================================
         DASHBOARD HEADING
    ======================================= -->

        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="mb-3 flex items-center gap-2 text-cyan-500">
                    <LayoutDashboard :size="20" />

                    <span class="text-sm font-medium">
                        Platform Administration
                    </span>
                </div>

                <h2
                    class="text-2xl md:text-3xl font-bold"
                    :class="isDark ? 'text-white' : 'text-slate-900'"
                >
                    Platform Overview
                </h2>

                <p
                    class="mt-2 text-sm"
                    :class="isDark ? 'text-slate-400' : 'text-slate-500'"
                >
                    Monitor merchants, stores, orders, tickets and returns.
                </p>
            </div>

            <!-- ADMIN INFO + REFRESH -->
            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    :disabled="loading"
                    @click="fetchDashboard"
                    class="flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium transition-all disabled:opacity-50"
                    :class="
                        isDark
                            ? 'border-white/10 bg-[#172235] text-slate-300 hover:border-cyan-500/40'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-cyan-500/40'
                    "
                >
                    <RefreshCw
                        :size="17"
                        :class="loading ? 'animate-spin' : ''"
                    />

                    Refresh
                </button>

                <div
                    class="flex items-center gap-3 rounded-xl border px-4 py-3 shadow-sm"
                    :class="
                        isDark
                            ? 'border-white/10 bg-[#172235] text-white'
                            : 'border-slate-200 bg-white text-slate-900'
                    "
                >
                    <div
                        class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-500 flex items-center justify-center"
                    >
                        <ShieldCheck :size="20" />
                    </div>

                    <div>
                        <p class="text-sm font-semibold">
                            {{ user?.name || "Super Admin" }}
                        </p>

                        <p
                            class="text-xs"
                            :class="
                                isDark ? 'text-slate-400' : 'text-slate-500'
                            "
                        >
                            Super Administrator
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================
         LOADING STATE
    ======================================= -->

        <div
            v-if="loading"
            class="flex items-center justify-center gap-3 py-20"
            :class="isDark ? 'text-slate-400' : 'text-slate-500'"
        >
            <LoaderCircle :size="24" class="animate-spin text-cyan-500" />

            <span>Loading dashboard...</span>
        </div>

        <!-- ======================================
         ERROR STATE
    ======================================= -->

        <div
            v-else-if="error"
            role="alert"
            class="rounded-xl border border-red-500/30 bg-red-500/10 p-5 text-red-500"
        >
            <p class="font-medium">Failed to load dashboard</p>

            <p class="mt-1 text-sm">{{ error }}</p>

            <button
                type="button"
                @click="fetchDashboard"
                class="mt-4 rounded-lg bg-red-500/10 px-4 py-2 text-sm font-medium hover:bg-red-500/20"
            >
                Try Again
            </button>
        </div>

        <!-- ======================================
         STATISTICS GRID
    ======================================= -->

        <div
            v-else
            class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4"
        >
            <div
                v-for="card in cards"
                :key="card.title"
                class="group rounded-2xl border p-6 shadow-sm transition-all duration-300 hover:border-cyan-500/40 hover:shadow-lg hover:shadow-cyan-500/5"
                :class="
                    isDark
                        ? 'border-white/10 bg-[#172235] text-white'
                        : 'border-slate-200 bg-white text-slate-900'
                "
            >
                <div class="flex items-center justify-between gap-3">
                    <p
                        class="text-sm font-medium"
                        :class="isDark ? 'text-slate-400' : 'text-slate-500'"
                    >
                        {{ card.title }}
                    </p>

                    <div
                        class="rounded-xl bg-cyan-500/10 p-3 transition-colors group-hover:bg-cyan-500/20"
                    >
                        <component
                            :is="card.icon"
                            :size="22"
                            class="text-cyan-500"
                        />
                    </div>
                </div>

                <p class="mt-5 text-3xl font-bold">
                    {{ formatValue(card.value) }}
                </p>
            </div>
        </div>
    </div>
</template>
