<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { RouterLink, useRouter } from "vue-router";
import { useTheme } from "../composables/useTheme";

import {
    ArrowLeft,
    BarChart3,
    ShoppingCart,
    Package,
    Ticket,
    Bot,
    Sparkles,
    Zap,
    ShieldCheck,
    MailCheck,
    RefreshCw,
    AlertCircle,
    Store,
    CalendarDays,
    TrendingUp,
    Clock3,
    MessageSquare,
    CheckCircle2,
    XCircle,
} from "lucide-vue-next";

const router = useRouter();
const { isDark } = useTheme();

const stores = ref([]);
const selectedStoreId = ref("");
const period = ref("30d");
const includeDemo = ref(true);

const analytics = ref(null);
const loading = ref(false);
const storesLoading = ref(false);
const error = ref("");

let requestId = 0;
let pollingTimer = null;
let backgroundRefreshBusy = false;
let pageActive = false;
const POLLING_INTERVAL = 30000;

// Existing theme styles
const cardClass = computed(() =>
    isDark.value
        ? "bg-[#172235] border-white/10"
        : "bg-white border-slate-200 shadow-sm",
);

const titleClass = computed(() =>
    isDark.value ? "text-white" : "text-slate-900",
);

const mutedClass = computed(() =>
    isDark.value ? "text-slate-400" : "text-slate-600",
);

const inputClass = computed(() =>
    isDark.value
        ? "bg-[#0f1726] border-white/10 text-white"
        : "bg-slate-50 border-slate-200 text-slate-900",
);

const secondaryClass = computed(() =>
    isDark.value
        ? "border-white/10 text-slate-300 hover:bg-white/5"
        : "border-slate-200 text-slate-700 hover:bg-slate-50",
);

// Same Laravel API pattern as My Stores
async function apiRequest(path) {
    const token = localStorage.getItem("accessToken");

    if (!token) {
        localStorage.removeItem("user");
        router.replace("/login");
        throw new Error("Please log in again.");
    }

    const response = await fetch(`/api${path}`, {
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${token}`,
        },
    });

    if (response.status === 401) {
        localStorage.removeItem("accessToken");
        localStorage.removeItem("user");
        router.replace("/login");
        throw new Error("Session expired. Please log in again.");
    }

    const contentType = response.headers.get("content-type") || "";

    if (!contentType.includes("application/json")) {
        throw new Error(`Unexpected API response (HTTP ${response.status}).`);
    }

    const data = await response.json();

    if (!response.ok || data?.success === false) {
        throw new Error(
            data?.message || `Request failed (HTTP ${response.status})`,
        );
    }

    return data;
}

function extractStores(data) {
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.stores)) return data.stores;
    if (Array.isArray(data?.data)) return data.data;
    if (Array.isArray(data?.data?.stores)) return data.data.stores;
    if (Array.isArray(data?.result?.stores)) return data.result.stores;

    throw new Error("Unexpected stores API response format");
}

async function getStores() {
    try {
        storesLoading.value = true;
        error.value = "";

        const data = await apiRequest("/stores");
        stores.value = extractStores(data);

        if (stores.value.length > 0) {
            selectedStoreId.value = String(stores.value[0].id);
        } else {
            analytics.value = null;
        }
    } catch (err) {
        error.value = err.message || "Failed to load stores.";
    } finally {
        storesLoading.value = false;
    }
}

async function getAnalytics({ silent = false } = {}) {
    const currentId = ++requestId;

    if (!selectedStoreId.value) {
        analytics.value = null;
        loading.value = false;
        return;
    }

    try {
        if (!silent) loading.value = true;
        error.value = "";

        const params = new URLSearchParams({
            store_id: selectedStoreId.value,
            period: period.value,
            include_demo: includeDemo.value ? "1" : "0",
        });

        const data = await apiRequest(
            `/analytics/overview?${params.toString()}`,
        );

        if (currentId !== requestId || !pageActive) return;

        analytics.value = data;
    } catch (err) {
        if (currentId !== requestId || !pageActive) return;

        // Keep the last good dashboard visible if a background refresh fails.
        if (!silent) analytics.value = null;
        error.value = err.message || "Failed to load analytics.";
    } finally {
        if (currentId === requestId && !silent) {
            loading.value = false;
        }
    }
}

async function refreshAnalytics() {
    if (loading.value || backgroundRefreshBusy) return;
    await getAnalytics();
}

async function pollAnalytics() {
    if (
        !pageActive ||
        document.hidden ||
        !selectedStoreId.value ||
        loading.value ||
        storesLoading.value ||
        backgroundRefreshBusy
    )
        return;

    backgroundRefreshBusy = true;
    try {
        await getAnalytics({ silent: true });
    } finally {
        backgroundRefreshBusy = false;
    }
}

watch([selectedStoreId, period, includeDemo], () => getAnalytics());

// Dashboard statistics
const stats = computed(() => {
    const overview = analytics.value?.overview || {};

    return [
        {
            title: "Total Orders",
            value: overview.total_orders ?? 0,
            icon: ShoppingCart,
            color: isDark.value ? "text-cyan-400" : "text-cyan-700",
            bg: isDark.value ? "bg-cyan-500/10" : "bg-cyan-50",
        },
        {
            title: "Returns & Exchanges",
            value: overview.total_returns ?? 0,
            icon: Package,
            color: isDark.value ? "text-amber-400" : "text-amber-700",
            bg: isDark.value ? "bg-amber-500/10" : "bg-amber-50",
        },
        {
            title: "Support Tickets",
            value: overview.total_tickets ?? 0,
            icon: Ticket,
            color: isDark.value ? "text-violet-400" : "text-violet-700",
            bg: isDark.value ? "bg-violet-500/10" : "bg-violet-50",
        },
        {
            title: "AI-Assisted Messages",
            value: overview.ai_assisted_messages ?? 0,
            icon: Bot,
            color: isDark.value ? "text-emerald-400" : "text-emerald-700",
            bg: isDark.value ? "bg-emerald-500/10" : "bg-emerald-50",
        },
        {
            title: "Emails Sent",
            value: overview.emails_sent ?? 0,
            icon: MailCheck,
            color: isDark.value ? "text-sky-400" : "text-sky-700",
            bg: isDark.value ? "bg-sky-500/10" : "bg-sky-50",
        },
    ];
});

const orderRows = computed(() => {
    const data = analytics.value?.orders || {};

    return [
        { label: "Total Orders", value: data.total ?? 0 },
        { label: "Pending Orders", value: data.pending ?? 0 },
        { label: "Completed Orders", value: data.completed ?? 0 },
    ];
});

const returnRows = computed(() => {
    const data = analytics.value?.returns || {};

    return [
        { label: "Total Requests", value: data.total ?? 0 },
        { label: "Returns", value: data.returns ?? 0 },
        { label: "Exchanges", value: data.exchanges ?? 0 },
        { label: "Refund Requests", value: data.refund_requests ?? 0 },
        { label: "Pending", value: data.pending ?? 0 },
        { label: "Approved", value: data.approved ?? 0 },
        { label: "Completed", value: data.completed ?? 0 },
        { label: "Rejected", value: data.rejected ?? 0 },
    ];
});

const ticketRows = computed(() => {
    const data = analytics.value?.tickets || {};

    return [
        { label: "Total Tickets", value: data.total ?? 0 },
        { label: "Open", value: data.open ?? 0 },
        { label: "In Progress", value: data.in_progress ?? 0 },
        { label: "Waiting Customer", value: data.waiting_customer ?? 0 },
        { label: "Resolved", value: data.resolved ?? 0 },
        { label: "Closed", value: data.closed ?? 0 },
        { label: "High Priority", value: data.high_priority ?? 0 },
        { label: "Urgent", value: data.urgent ?? 0 },
    ];
});

const messageRows = computed(() => {
    const data = analytics.value?.messages || {};

    return [
        { label: "Total Messages", value: data.total ?? 0 },
        { label: "Customer Messages", value: data.customer ?? 0 },
        { label: "Owner Replies", value: data.owner ?? 0 },
        { label: "AI Messages", value: data.ai ?? 0 },
        { label: "AI-Assisted", value: data.ai_assisted ?? 0 },
        { label: "Emails Sent", value: data.emails_sent ?? 0 },
        { label: "Emails Failed", value: data.emails_failed ?? 0 },
    ];
});

// Monthly AI quota is owner-wide, independent of store and date filters.
const aiUsage = computed(() => analytics.value?.ai_monthly_usage ?? null);
const aiPlanName = computed(() =>
    readableLabel(aiUsage.value?.plan || "No active plan"),
);
const aiUnlimited = computed(() => aiUsage.value?.unlimited === true);
const aiUsed = computed(() => Number(aiUsage.value?.used ?? 0));
const aiLimit = computed(() => Number(aiUsage.value?.limit ?? 0));
const aiRemaining = computed(() => Number(aiUsage.value?.remaining ?? 0));
const aiPercent = computed(() => {
    if (aiUnlimited.value || aiLimit.value <= 0) return 0;
    return Math.min(100, Math.max(0, (aiUsed.value / aiLimit.value) * 100));
});
const aiFeatureRows = computed(() => [
    {
        label: "AI Reply Generation",
        value: aiUsage.value?.features?.ai_reply ?? 0,
    },
    {
        label: "Knowledge Base Reply",
        value: aiUsage.value?.features?.kb_reply ?? 0,
    },
    {
        label: "Ticket Analysis",
        value: aiUsage.value?.features?.ticket_analysis ?? 0,
    },
]);

const trends = computed(() => analytics.value?.trends || []);

const maxTrendValue = computed(() => {
    const values = trends.value.flatMap((item) => [
        Number(item.orders || 0),
        Number(item.returns || 0),
        Number(item.tickets || 0),
    ]);

    return Math.max(1, ...values);
});

function trendWidth(value) {
    return `${(Number(value || 0) / maxTrendValue.value) * 100}%`;
}

const categories = computed(() => analytics.value?.tickets?.categories || []);

const maxCategoryValue = computed(() =>
    Math.max(1, ...categories.value.map((item) => Number(item.total || 0))),
);

function categoryWidth(value) {
    return `${(Number(value || 0) / maxCategoryValue.value) * 100}%`;
}

const averageResolution = computed(() => {
    const minutes = analytics.value?.tickets?.average_resolution_minutes;

    if (minutes == null) return "N/A";

    if (minutes < 60) return `${Math.round(minutes)} minutes`;

    return `${(minutes / 60).toFixed(1)} hours`;
});

function formatDate(date) {
    if (!date) return "";

    const [year, month, day] = date.split("-").map(Number);

    return new Date(year, month - 1, day).toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
    });
}

function formatCurrency(amount, currency) {
    try {
        return new Intl.NumberFormat("en-US", {
            style: "currency",
            currency: currency || "USD",
        }).format(Number(amount || 0));
    } catch {
        return `${currency || ""} ${Number(amount || 0).toFixed(2)}`;
    }
}

function readableLabel(value) {
    return String(value || "")
        .replace(/_/g, " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function refreshWhenVisible() {
    if (document.hidden || !pageActive) return;
    void pollAnalytics();
}

onMounted(() => {
    pageActive = true;
    void getStores();
    pollingTimer = window.setInterval(pollAnalytics, POLLING_INTERVAL);
    document.addEventListener("visibilitychange", refreshWhenVisible);
    window.addEventListener("focus", refreshWhenVisible);
});

onUnmounted(() => {
    pageActive = false;
    if (pollingTimer !== null) window.clearInterval(pollingTimer);
    pollingTimer = null;
    document.removeEventListener("visibilitychange", refreshWhenVisible);
    window.removeEventListener("focus", refreshWhenVisible);
    requestId++;
});
</script>

<template>
    <div class="mx-auto max-w-7xl space-y-7 pb-6">
        <!-- Back -->
        <RouterLink
            to="/dashboard"
            class="inline-flex items-center gap-2 text-sm"
            :class="mutedClass"
        >
            <ArrowLeft :size="18" />
            Back to dashboard
        </RouterLink>

        <!-- Header -->
        <div
            class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center"
        >
            <div>
                <p
                    class="mb-2 text-xs font-semibold tracking-widest"
                    :class="isDark ? 'text-cyan-400' : 'text-cyan-700'"
                >
                    BUSINESS INSIGHTS
                </p>

                <h1 class="text-3xl font-bold" :class="titleClass">
                    Analytics
                </h1>

                <p class="mt-2 text-sm" :class="mutedClass">
                    Track orders, returns, tickets, AI activity and emails.
                </p>
            </div>

            <button
                type="button"
                :disabled="loading || !selectedStoreId"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-500 px-5 py-3 font-semibold text-slate-950 transition hover:bg-cyan-400 disabled:opacity-50"
                @click="refreshAnalytics"
            >
                <RefreshCw :size="18" :class="{ 'animate-spin': loading }" />
                Refresh Analytics
            </button>
        </div>

        <!-- Error -->
        <div
            v-if="error"
            role="alert"
            class="flex items-start gap-3 rounded-xl border p-4"
            :class="
                isDark
                    ? 'border-red-500/20 bg-red-500/10 text-red-300'
                    : 'border-red-200 bg-red-50 text-red-700'
            "
        >
            <AlertCircle :size="19" class="shrink-0" />
            <span class="text-sm">{{ error }}</span>
        </div>

        <!-- Filters -->
        <div class="rounded-2xl border p-5" :class="cardClass">
            <div class="grid gap-5 md:grid-cols-3">
                <!-- Store -->
                <div>
                    <label
                        class="mb-2 flex items-center gap-2 text-sm font-medium"
                        :class="titleClass"
                    >
                        <Store :size="16" class="text-cyan-500" />
                        Select Store
                    </label>

                    <select
                        v-model="selectedStoreId"
                        :disabled="storesLoading"
                        class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500 disabled:opacity-50"
                        :class="inputClass"
                    >
                        <option value="">Select Store</option>

                        <option
                            v-for="store in stores"
                            :key="store.id"
                            :value="String(store.id)"
                        >
                            {{ store.store_name }}
                        </option>
                    </select>
                </div>

                <!-- Period -->
                <div>
                    <label
                        class="mb-2 flex items-center gap-2 text-sm font-medium"
                        :class="titleClass"
                    >
                        <CalendarDays :size="16" class="text-cyan-500" />
                        Date Range
                    </label>

                    <select
                        v-model="period"
                        class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                        :class="inputClass"
                    >
                        <option value="7d">Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                        <option value="90d">Last 90 Days</option>
                        <option value="12m">Last 12 Months</option>
                    </select>
                </div>

                <!-- Demo Toggle -->
                <div class="flex items-end">
                    <label
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl border px-4 py-3"
                        :class="inputClass"
                    >
                        <span class="text-sm font-medium">
                            Include Demo Data
                        </span>

                        <input
                            v-model="includeDemo"
                            type="checkbox"
                            class="h-4 w-4 accent-cyan-500"
                        />
                    </label>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div
            v-if="loading || storesLoading"
            class="rounded-2xl border p-12 text-center"
            :class="cardClass"
        >
            <RefreshCw
                :size="28"
                class="mx-auto mb-4 animate-spin text-cyan-500"
            />
            <p :class="mutedClass">Loading analytics...</p>
        </div>

        <template v-else-if="analytics">
            <!-- Statistics -->
            <div
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
            >
                <div
                    v-for="stat in stats"
                    :key="stat.title"
                    class="rounded-2xl border p-5"
                    :class="cardClass"
                >
                    <div class="flex items-center gap-4">
                        <div class="rounded-xl p-3" :class="stat.bg">
                            <component
                                :is="stat.icon"
                                :size="22"
                                :class="stat.color"
                            />
                        </div>

                        <div class="min-w-0">
                            <p class="text-xs" :class="mutedClass">
                                {{ stat.title }}
                            </p>

                            <h3
                                class="mt-1 text-2xl font-bold"
                                :class="titleClass"
                            >
                                {{ stat.value }}
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly AI Usage: owner-wide calendar-month subscription quota -->
            <div
                v-if="aiUsage"
                class="rounded-2xl border p-6"
                :class="cardClass"
            >
                <div
                    class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"
                >
                    <div>
                        <h2
                            class="flex items-center gap-2 text-lg font-semibold"
                            :class="titleClass"
                        >
                            <Sparkles :size="21" class="text-cyan-500" />
                            Monthly AI Usage
                        </h2>
                        <p class="mt-1 text-sm" :class="mutedClass">
                            All stores combined · {{ aiUsage.period_start }} to
                            {{ aiUsage.period_end }}
                        </p>
                    </div>
                    <span
                        class="self-start rounded-full px-3 py-1 text-xs font-semibold"
                        :class="
                            isDark
                                ? 'bg-cyan-500/10 text-cyan-300'
                                : 'bg-cyan-50 text-cyan-700'
                        "
                    >
                        {{ aiPlanName }} Plan
                    </span>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div
                        class="rounded-xl p-4"
                        :class="isDark ? 'bg-white/5' : 'bg-slate-50'"
                    >
                        <p class="text-xs" :class="mutedClass">Requests Used</p>
                        <p class="mt-1 text-2xl font-bold" :class="titleClass">
                            {{ aiUsed }}
                        </p>
                        <p class="mt-1 text-xs" :class="mutedClass">
                            Completed + pending
                        </p>
                    </div>
                    <div
                        class="rounded-xl p-4"
                        :class="isDark ? 'bg-white/5' : 'bg-slate-50'"
                    >
                        <p class="text-xs" :class="mutedClass">
                            Remaining Requests
                        </p>
                        <p class="mt-1 text-2xl font-bold" :class="titleClass">
                            {{ aiUnlimited ? "Unlimited" : aiRemaining }}
                        </p>
                        <p class="mt-1 text-xs" :class="mutedClass">
                            Available this month
                        </p>
                    </div>
                    <div
                        class="rounded-xl p-4"
                        :class="isDark ? 'bg-white/5' : 'bg-slate-50'"
                    >
                        <p class="text-xs" :class="mutedClass">Monthly Limit</p>
                        <p class="mt-1 text-2xl font-bold" :class="titleClass">
                            {{ aiUnlimited ? "Unlimited" : aiLimit }}
                        </p>
                        <p class="mt-1 text-xs" :class="mutedClass">
                            {{ aiPlanName }} subscription
                        </p>
                    </div>
                </div>

                <div v-if="!aiUnlimited && aiLimit > 0" class="mt-6">
                    <div class="mb-2 flex justify-between gap-3 text-sm">
                        <span :class="mutedClass">Monthly quota used</span>
                        <strong :class="titleClass"
                            >{{ aiPercent.toFixed(1) }}%</strong
                        >
                    </div>
                    <div
                        class="h-3 overflow-hidden rounded-full"
                        :class="isDark ? 'bg-white/10' : 'bg-slate-100'"
                    >
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="
                                aiPercent >= 100
                                    ? 'bg-red-500'
                                    : aiPercent >= 80
                                      ? 'bg-amber-500'
                                      : 'bg-cyan-500'
                            "
                            :style="{ width: aiPercent + '%' }"
                        ></div>
                    </div>
                </div>
                <div
                    v-else-if="aiUnlimited"
                    class="mt-5 flex items-center gap-2 text-sm text-emerald-500"
                >
                    <ShieldCheck :size="18" /> Unlimited AI requests enabled
                </div>

                <div
                    v-if="!aiUsage.allowed"
                    class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-600 dark:text-amber-300"
                >
                    {{ aiUsage.message }}
                </div>

                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    <div>
                        <h3
                            class="mb-3 flex items-center gap-2 text-sm font-semibold"
                            :class="titleClass"
                        >
                            <Zap :size="17" class="text-cyan-500" /> Feature
                            Breakdown
                        </h3>
                        <div
                            v-for="row in aiFeatureRows"
                            :key="row.label"
                            class="flex justify-between border-b py-2.5 text-sm"
                            :class="
                                isDark ? 'border-white/10' : 'border-slate-100'
                            "
                        >
                            <span :class="mutedClass">{{ row.label }}</span>
                            <strong :class="titleClass">{{ row.value }}</strong>
                        </div>
                    </div>
                    <div>
                        <h3
                            class="mb-3 text-sm font-semibold"
                            :class="titleClass"
                        >
                            Request Status
                        </h3>
                        <div
                            v-for="row in [
                                {
                                    label: 'Completed',
                                    value: aiUsage.completed ?? 0,
                                },
                                {
                                    label: 'Pending',
                                    value: aiUsage.reserved ?? 0,
                                },
                                {
                                    label: 'Failed (not charged)',
                                    value: aiUsage.failed ?? 0,
                                },
                            ]"
                            :key="row.label"
                            class="flex justify-between border-b py-2.5 text-sm"
                            :class="
                                isDark ? 'border-white/10' : 'border-slate-100'
                            "
                        >
                            <span :class="mutedClass">{{ row.label }}</span>
                            <strong :class="titleClass">{{ row.value }}</strong>
                        </div>
                    </div>
                </div>
                <p class="mt-5 text-xs" :class="mutedClass">
                    AI drafts and analysis count toward this quota; AI-assisted
                    messages below track saved ticket messages separately.
                </p>
            </div>

            <!-- Trends -->
            <div class="rounded-2xl border p-6" :class="cardClass">
                <div class="mb-6 flex items-center justify-between gap-4">
                    <div>
                        <h2
                            class="flex items-center gap-2 text-lg font-semibold"
                            :class="titleClass"
                        >
                            <TrendingUp :size="20" class="text-cyan-500" />
                            Activity Trends
                        </h2>

                        <p class="mt-1 text-sm" :class="mutedClass">
                            Orders, returns and support tickets over time.
                        </p>
                    </div>

                    <BarChart3 :size="24" class="text-cyan-500" />
                </div>

                <div v-if="trends.length" class="space-y-5">
                    <div
                        v-for="item in trends"
                        :key="item.date"
                        class="flex items-center gap-4"
                    >
                        <span class="w-16 shrink-0 text-xs" :class="mutedClass">
                            {{ formatDate(item.date) }}
                        </span>

                        <div class="flex-1 space-y-2">
                            <div
                                class="h-2 overflow-hidden rounded-full"
                                :class="isDark ? 'bg-white/10' : 'bg-slate-100'"
                            >
                                <div
                                    class="h-full rounded-full bg-cyan-500"
                                    :style="{ width: trendWidth(item.orders) }"
                                ></div>
                            </div>

                            <div
                                class="h-2 overflow-hidden rounded-full"
                                :class="isDark ? 'bg-white/10' : 'bg-slate-100'"
                            >
                                <div
                                    class="h-full rounded-full bg-amber-500"
                                    :style="{ width: trendWidth(item.returns) }"
                                ></div>
                            </div>

                            <div
                                class="h-2 overflow-hidden rounded-full"
                                :class="isDark ? 'bg-white/10' : 'bg-slate-100'"
                            >
                                <div
                                    class="h-full rounded-full bg-violet-500"
                                    :style="{ width: trendWidth(item.tickets) }"
                                ></div>
                            </div>
                        </div>

                        <div
                            class="flex w-8 shrink-0 flex-col gap-0.5 text-right text-xs"
                            :class="mutedClass"
                        >
                            <span>{{ item.orders }}</span>
                            <span>{{ item.returns }}</span>
                            <span>{{ item.tickets }}</span>
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap justify-center gap-5 border-t pt-5 text-xs"
                        :class="
                            isDark
                                ? 'border-white/10 text-slate-400'
                                : 'border-slate-200 text-slate-600'
                        "
                    >
                        <span class="flex items-center gap-2">
                            <span
                                class="h-2.5 w-2.5 rounded-full bg-cyan-500"
                            ></span>
                            Orders
                        </span>

                        <span class="flex items-center gap-2">
                            <span
                                class="h-2.5 w-2.5 rounded-full bg-amber-500"
                            ></span>
                            Returns
                        </span>

                        <span class="flex items-center gap-2">
                            <span
                                class="h-2.5 w-2.5 rounded-full bg-violet-500"
                            ></span>
                            Tickets
                        </span>
                    </div>
                </div>

                <div v-else class="py-12 text-center">
                    <BarChart3 :size="40" class="mx-auto mb-3 text-cyan-500" />
                    <p :class="mutedClass">
                        No activity found for this period.
                    </p>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="grid gap-5 lg:grid-cols-2">
                <!-- Orders -->
                <div class="rounded-2xl border p-6" :class="cardClass">
                    <h2
                        class="mb-5 flex items-center gap-3 text-lg font-semibold"
                        :class="titleClass"
                    >
                        <ShoppingCart :size="21" class="text-cyan-500" />
                        Order Statistics
                    </h2>

                    <div
                        v-for="row in orderRows"
                        :key="row.label"
                        class="flex items-center justify-between border-b py-3 text-sm"
                        :class="isDark ? 'border-white/10' : 'border-slate-100'"
                    >
                        <span :class="mutedClass">{{ row.label }}</span>
                        <strong :class="titleClass">{{ row.value }}</strong>
                    </div>

                    <h3
                        class="mb-3 mt-6 text-sm font-semibold"
                        :class="titleClass"
                    >
                        Order Value by Currency
                    </h3>

                    <div
                        v-for="item in analytics.orders?.amount_by_currency ||
                        []"
                        :key="item.currency"
                        class="flex justify-between py-2 text-sm"
                    >
                        <span :class="mutedClass">{{ item.currency }}</span>

                        <strong class="text-cyan-500">
                            {{ formatCurrency(item.amount, item.currency) }}
                        </strong>
                    </div>

                    <p
                        v-if="!analytics.orders?.amount_by_currency?.length"
                        class="text-sm"
                        :class="mutedClass"
                    >
                        No order value recorded.
                    </p>

                    <p class="mt-4 text-xs" :class="mutedClass">
                        Order value is not necessarily paid revenue.
                    </p>
                </div>

                <!-- Returns -->
                <div class="rounded-2xl border p-6" :class="cardClass">
                    <h2
                        class="mb-5 flex items-center gap-3 text-lg font-semibold"
                        :class="titleClass"
                    >
                        <Package :size="21" class="text-amber-500" />
                        Returns & Exchanges
                    </h2>

                    <div
                        v-for="row in returnRows"
                        :key="row.label"
                        class="flex items-center justify-between border-b py-3 text-sm"
                        :class="isDark ? 'border-white/10' : 'border-slate-100'"
                    >
                        <span :class="mutedClass">{{ row.label }}</span>
                        <strong :class="titleClass">{{ row.value }}</strong>
                    </div>

                    <h3
                        class="mb-3 mt-6 text-sm font-semibold"
                        :class="titleClass"
                    >
                        Completed Refunds
                    </h3>

                    <div
                        v-for="item in analytics.returns
                            ?.completed_refunds_by_currency || []"
                        :key="item.currency"
                        class="flex justify-between py-2 text-sm"
                    >
                        <span :class="mutedClass">{{ item.currency }}</span>

                        <strong class="text-amber-500">
                            {{ formatCurrency(item.amount, item.currency) }}
                        </strong>
                    </div>

                    <p
                        v-if="
                            !analytics.returns?.completed_refunds_by_currency
                                ?.length
                        "
                        class="text-sm"
                        :class="mutedClass"
                    >
                        No completed refunds recorded.
                    </p>
                </div>

                <!-- Tickets -->
                <div class="rounded-2xl border p-6" :class="cardClass">
                    <h2
                        class="mb-5 flex items-center gap-3 text-lg font-semibold"
                        :class="titleClass"
                    >
                        <Ticket :size="21" class="text-violet-500" />
                        Support Tickets
                    </h2>

                    <div
                        v-for="row in ticketRows"
                        :key="row.label"
                        class="flex items-center justify-between border-b py-3 text-sm"
                        :class="isDark ? 'border-white/10' : 'border-slate-100'"
                    >
                        <span :class="mutedClass">{{ row.label }}</span>
                        <strong :class="titleClass">{{ row.value }}</strong>
                    </div>

                    <div
                        class="mt-5 flex items-center justify-between rounded-xl p-4"
                        :class="isDark ? 'bg-white/5' : 'bg-slate-50'"
                    >
                        <span
                            class="flex items-center gap-2 text-sm"
                            :class="mutedClass"
                        >
                            <Clock3 :size="17" />
                            Avg. Resolution Time
                        </span>

                        <strong :class="titleClass">
                            {{ averageResolution }}
                        </strong>
                    </div>
                </div>

                <!-- Messages -->
                <div class="rounded-2xl border p-6" :class="cardClass">
                    <h2
                        class="mb-5 flex items-center gap-3 text-lg font-semibold"
                        :class="titleClass"
                    >
                        <Bot :size="21" class="text-emerald-500" />
                        AI & Email Activity
                    </h2>

                    <div
                        v-for="row in messageRows"
                        :key="row.label"
                        class="flex items-center justify-between border-b py-3 text-sm"
                        :class="isDark ? 'border-white/10' : 'border-slate-100'"
                    >
                        <span :class="mutedClass">{{ row.label }}</span>

                        <strong
                            :class="
                                row.label === 'Emails Failed'
                                    ? 'text-red-500'
                                    : titleClass
                            "
                        >
                            {{ row.value }}
                        </strong>
                    </div>

                    <p class="mt-5 text-xs leading-relaxed" :class="mutedClass">
                        AI-assisted messages represent recorded activity. Gemini
                        draft generations may not be included.
                    </p>
                </div>
            </div>

            <!-- Categories -->
            <div class="rounded-2xl border p-6" :class="cardClass">
                <h2
                    class="mb-6 flex items-center gap-3 text-lg font-semibold"
                    :class="titleClass"
                >
                    <MessageSquare :size="21" class="text-cyan-500" />
                    Ticket Categories
                </h2>

                <div v-if="categories.length" class="space-y-5">
                    <div
                        v-for="category in categories"
                        :key="category.category"
                        class="flex items-center gap-4"
                    >
                        <span
                            class="w-28 shrink-0 text-xs sm:w-40 sm:text-sm"
                            :class="mutedClass"
                        >
                            {{ readableLabel(category.category) }}
                        </span>

                        <div
                            class="h-3 flex-1 overflow-hidden rounded-full"
                            :class="isDark ? 'bg-white/10' : 'bg-slate-100'"
                        >
                            <div
                                class="h-full rounded-full bg-cyan-500"
                                :style="{
                                    width: categoryWidth(category.total),
                                }"
                            ></div>
                        </div>

                        <strong
                            class="w-8 text-right text-sm"
                            :class="titleClass"
                        >
                            {{ category.total }}
                        </strong>
                    </div>
                </div>

                <p v-else class="py-8 text-center text-sm" :class="mutedClass">
                    No ticket categories found.
                </p>
            </div>

            <!-- Store Information -->
            <div
                v-if="analytics.store"
                class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-5"
                :class="cardClass"
            >
                <div>
                    <p class="text-xs" :class="mutedClass">CURRENT STORE</p>

                    <h3 class="mt-1 font-semibold" :class="titleClass">
                        {{ analytics.store.store_name }}
                    </h3>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <span
                        class="rounded-full px-3 py-1.5 text-xs font-medium capitalize"
                        :class="
                            analytics.store.status === 'connected'
                                ? isDark
                                    ? 'bg-emerald-500/10 text-emerald-400'
                                    : 'bg-emerald-100 text-emerald-700'
                                : isDark
                                  ? 'bg-amber-500/10 text-amber-400'
                                  : 'bg-amber-100 text-amber-700'
                        "
                    >
                        {{ analytics.store.status }}
                    </span>

                    <span
                        class="flex items-center gap-2 text-xs"
                        :class="mutedClass"
                    >
                        <CheckCircle2
                            v-if="analytics.store.status === 'connected'"
                            :size="15"
                        />
                        <XCircle v-else :size="15" />
                        {{ analytics.store.platform }}
                    </span>
                </div>
            </div>
        </template>

        <!-- Empty State -->
        <div
            v-else-if="!error"
            class="rounded-2xl border p-12 text-center"
            :class="cardClass"
        >
            <BarChart3 :size="42" class="mx-auto mb-4 text-cyan-500" />

            <h2 class="text-xl font-semibold" :class="titleClass">
                No Analytics Available
            </h2>

            <p class="mt-2 text-sm" :class="mutedClass">
                Select a store to view your business analytics.
            </p>
        </div>
    </div>
</template>
