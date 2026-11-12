<script setup>
import { ref, computed, onMounted } from "vue";
import { RouterLink } from "vue-router";
import { useTheme } from "../composables/useTheme";

import {
    Store,
    ShoppingBag,
    RotateCcw,
    MessageSquareText,
    ArrowUpRight,
    Plus,
    Sparkles,
    CheckCircle2,
    Clock3,
    Activity,
    RefreshCw,
    AlertCircle,
    CreditCard,
    CalendarDays,
    ShieldCheck,
    ExternalLink,
} from "lucide-vue-next";

// Shared reactive theme
const { isDark } = useTheme();

// Dashboard state
const user = ref(null);
const stores = ref([]);
const loading = ref(true);
const error = ref("");

// Subscription state
const subscription = ref(null);
const subscriptionLoading = ref(true);
const subscriptionError = ref("");
const portalLoading = ref(false);
const portalError = ref("");

// Upcoming modules
const upcomingModules = [
    {
        name: "Order Management",
        desc: "Track and manage customer orders from one workspace.",
        icon: ShoppingBag,
    },
    {
        name: "Returns & Refunds",
        desc: "Simplify returns and improve post-purchase experiences.",
        icon: RotateCcw,
    },
    {
        name: "Support Tickets",
        desc: "Manage customer conversations and support requests.",
        icon: MessageSquareText,
    },
];

// Store statistics
const connectedStores = computed(
    () => stores.value.filter((store) => store.status === "connected").length,
);

const disconnectedStores = computed(
    () => stores.value.filter((store) => store.status !== "connected").length,
);

const stats = computed(() => [
    {
        title: "Total Stores",
        value: stores.value.length,
        icon: Store,
        iconColor: isDark.value ? "text-cyan-400" : "text-cyan-700",
        iconBg: isDark.value ? "bg-cyan-500/10" : "bg-cyan-50",
    },
    {
        title: "Connected Stores",
        value: connectedStores.value,
        icon: CheckCircle2,
        iconColor: isDark.value ? "text-emerald-400" : "text-emerald-700",
        iconBg: isDark.value ? "bg-emerald-500/10" : "bg-emerald-50",
    },
    {
        title: "Disconnected Stores",
        value: disconnectedStores.value,
        icon: Clock3,
        iconColor: isDark.value ? "text-amber-400" : "text-amber-700",
        iconBg: isDark.value ? "bg-amber-500/10" : "bg-amber-50",
    },
]);

// Reactive theme classes
const cardClass = computed(() =>
    isDark.value
        ? "bg-[#172235] border-white/10 hover:border-white/20"
        : "bg-white border-slate-200 hover:border-slate-300 shadow-sm",
);

const titleClass = computed(() =>
    isDark.value ? "text-white" : "text-slate-900",
);

const mutedClass = computed(() =>
    isDark.value ? "text-slate-400" : "text-slate-600",
);

const subtleClass = computed(() =>
    isDark.value ? "text-slate-500" : "text-slate-500",
);

const rowClass = computed(() =>
    isDark.value
        ? "bg-white/[0.03] border-white/5"
        : "bg-slate-50 border-slate-200",
);

const accentClass = computed(() =>
    isDark.value
        ? "text-cyan-400 hover:text-cyan-300"
        : "text-cyan-700 hover:text-cyan-800",
);

// Subscription status styling
const subscriptionStatusClass = computed(() => {
    const status = subscription.value?.status;

    if (status === "active" || status === "trialing") {
        return isDark.value
            ? "bg-emerald-500/10 text-emerald-400 border-emerald-500/20"
            : "bg-emerald-50 text-emerald-700 border-emerald-200";
    }

    if (status === "past_due" || status === "unpaid") {
        return isDark.value
            ? "bg-amber-500/10 text-amber-400 border-amber-500/20"
            : "bg-amber-50 text-amber-700 border-amber-200";
    }

    return isDark.value
        ? "bg-slate-500/10 text-slate-300 border-white/10"
        : "bg-slate-100 text-slate-700 border-slate-200";
});

// Format subscription dates
function formatDate(value) {
    if (!value) {
        return "N/A";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return "N/A";
    }

    return date.toLocaleDateString("en-US", {
        day: "numeric",
        month: "long",
        year: "numeric",
        timeZone: "UTC",
    });
}

// Safely read API JSON responses
async function readJsonResponse(response) {
    const contentType = response.headers.get("content-type") || "";

    if (!contentType.includes("application/json")) {
        throw new Error(
            `API returned an unexpected response (HTTP ${response.status}). Check Laravel API routes.`,
        );
    }

    return response.json();
}

// Shared authentication headers
function getAuthHeaders() {
    const token = localStorage.getItem("accessToken");

    if (!token) {
        throw new Error("Authentication token not found. Please log in again.");
    }

    return {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
    };
}

// Fetch dashboard data
async function loadDashboard() {
    loading.value = true;
    error.value = "";

    try {
        const headers = getAuthHeaders();

        const [profileResponse, storesResponse] = await Promise.all([
            fetch("/api/auth/me", { headers }),
            fetch("/api/stores", { headers }),
        ]);

        const [profileData, storesData] = await Promise.all([
            readJsonResponse(profileResponse),
            readJsonResponse(storesResponse),
        ]);

        if (!profileResponse.ok || !profileData.success) {
            throw new Error(
                profileData.message || "Unable to load your profile.",
            );
        }

        if (!storesResponse.ok || !storesData.success) {
            throw new Error(
                storesData.message || "Unable to load your stores.",
            );
        }

        user.value = profileData.user;

        stores.value = Array.isArray(storesData.stores)
            ? storesData.stores
            : [];
    } catch (err) {
        console.error("Dashboard Error:", err);

        error.value = err.message || "Could not load dashboard data.";
    } finally {
        loading.value = false;
    }
}

// Fetch logged-in owner's subscription
async function loadSubscription() {
    subscriptionLoading.value = true;
    subscriptionError.value = "";

    try {
        const headers = getAuthHeaders();

        const response = await fetch("/api/subscription/me", {
            method: "GET",
            headers,
        });

        const data = await readJsonResponse(response);

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to load your subscription.",
            );
        }

        subscription.value = data.subscription ?? null;
    } catch (err) {
        console.error("Subscription Error:", err);

        subscription.value = null;
        subscriptionError.value =
            err.message || "Could not load subscription data.";
    } finally {
        subscriptionLoading.value = false;
    }
}

// Open the authenticated owner's Stripe Customer Portal
async function manageSubscription() {
    if (portalLoading.value) return;

    portalLoading.value = true;
    portalError.value = "";

    try {
        const response = await fetch("/api/subscription/portal", {
            method: "POST",
            headers: getAuthHeaders(),
        });

        const data = await readJsonResponse(response);

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to open billing portal.");
        }

        const url = data.portal_url;
        if (!url || !/^https:\/\/billing\.stripe\.com\//.test(url)) {
            throw new Error("Invalid billing portal URL returned by server.");
        }

        window.location.assign(url);
    } catch (err) {
        console.error("Billing Portal Error:", err);
        portalError.value = err.message || "Could not open billing portal.";
        portalLoading.value = false;
    }
}

// Refresh all dashboard data
function refreshDashboard() {
    loadDashboard();
    loadSubscription();
}

const isRefreshing = computed(() => loading.value || subscriptionLoading.value);

onMounted(() => {
    refreshDashboard();
});
</script>

<template>
    <div
        class="mx-auto max-w-7xl space-y-8 pb-6 transition-colors duration-200"
        :class="isDark ? 'text-white' : 'text-slate-900'"
    >
        <!-- Welcome Section -->
        <section
            class="flex flex-col justify-between gap-5 md:flex-row md:items-center"
        >
            <div>
                <div class="mb-3 flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-cyan-500"></span>

                    <p
                        class="text-xs font-bold tracking-[0.16em]"
                        :class="isDark ? 'text-cyan-400' : 'text-cyan-700'"
                    >
                        YOUR WORKSPACE
                    </p>
                </div>

                <h2
                    class="text-2xl font-bold tracking-tight md:text-3xl"
                    :class="titleClass"
                >
                    Welcome back{{ user?.name ? `, ${user.name}` : "" }} 👋
                </h2>

                <p class="mt-2 text-sm md:text-base" :class="mutedClass">
                    Here's what's happening with your AfterSale AI workspace.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Refresh Button -->
                <button
                    type="button"
                    :disabled="isRefreshing"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium transition disabled:opacity-50"
                    :class="
                        isDark
                            ? 'border-white/10 text-slate-300 hover:bg-white/5'
                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                    "
                    @click="refreshDashboard"
                >
                    <RefreshCw
                        :size="17"
                        :class="{ 'animate-spin': isRefreshing }"
                    />
                    Refresh
                </button>

                <!-- Manage Stores -->
                <RouterLink
                    to="/dashboard/stores"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-500 px-5 py-3 font-semibold text-slate-950 shadow-lg shadow-cyan-500/10 transition hover:bg-cyan-400"
                >
                    <Plus :size="19" />
                    Manage Stores
                </RouterLink>
            </div>
        </section>

        <!-- Error Message -->
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
            <AlertCircle :size="19" class="mt-0.5 shrink-0" />

            <p class="text-sm">
                {{ error }}
            </p>
        </div>

        <!-- Statistics Cards -->
        <section class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="stat in stats"
                :key="stat.title"
                class="rounded-2xl border p-6 transition-all duration-200"
                :class="cardClass"
            >
                <div class="flex items-center justify-between">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl"
                        :class="stat.iconBg"
                    >
                        <component
                            :is="stat.icon"
                            :size="23"
                            :class="stat.iconColor"
                        />
                    </div>

                    <Activity :size="17" :class="subtleClass" />
                </div>

                <p class="mt-6 text-sm" :class="mutedClass">
                    {{ stat.title }}
                </p>

                <h3 class="mt-2 text-3xl font-bold" :class="titleClass">
                    {{ loading ? "..." : error ? "—" : stat.value }}
                </h3>

                <p class="mt-3 text-xs" :class="subtleClass">
                    Based on your registered stores
                </p>
            </div>
        </section>

        <!-- My Subscription Section -->
        <section
            class="overflow-hidden rounded-2xl border transition-all duration-200"
            :class="cardClass"
        >
            <div
                class="flex flex-col justify-between gap-4 border-b p-5 md:flex-row md:items-center md:p-6"
                :class="isDark ? 'border-white/10' : 'border-slate-200'"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
                        :class="
                            isDark
                                ? 'bg-cyan-500/10 text-cyan-400'
                                : 'bg-cyan-50 text-cyan-700'
                        "
                    >
                        <CreditCard :size="24" />
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold" :class="titleClass">
                            My Subscription
                        </h3>

                        <p class="mt-1 text-sm" :class="mutedClass">
                            Your AfterSale AI subscription and billing details
                        </p>
                    </div>
                </div>

                <span
                    v-if="subscription && !subscriptionLoading"
                    class="inline-flex w-fit items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold capitalize"
                    :class="subscriptionStatusClass"
                >
                    <CheckCircle2 v-if="subscription.is_active" :size="14" />
                    <AlertCircle v-else :size="14" />

                    {{ subscription.status.replaceAll("_", " ") }}
                </span>
            </div>

            <!-- Subscription Loading -->
            <div
                v-if="subscriptionLoading"
                class="grid gap-5 p-6 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div v-for="item in 4" :key="item" class="space-y-3">
                    <div
                        class="h-4 w-24 animate-pulse rounded"
                        :class="isDark ? 'bg-white/10' : 'bg-slate-100'"
                    ></div>

                    <div
                        class="h-8 w-36 animate-pulse rounded"
                        :class="isDark ? 'bg-white/10' : 'bg-slate-100'"
                    ></div>
                </div>
            </div>

            <!-- Subscription Error -->
            <div
                v-else-if="subscriptionError"
                class="flex flex-col items-start gap-3 p-6"
            >
                <p class="flex items-center gap-2 text-sm text-red-500">
                    <AlertCircle :size="18" />
                    {{ subscriptionError }}
                </p>

                <button
                    type="button"
                    class="text-sm font-semibold"
                    :class="accentClass"
                    @click="loadSubscription"
                >
                    Try again
                </button>
            </div>

            <!-- No Subscription -->
            <div
                v-else-if="!subscription"
                class="flex flex-col items-center justify-center px-6 py-12 text-center"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-2xl"
                    :class="
                        isDark
                            ? 'bg-white/5 text-slate-400'
                            : 'bg-slate-100 text-slate-600'
                    "
                >
                    <CreditCard :size="27" />
                </div>

                <h4 class="mt-4 text-lg font-semibold" :class="titleClass">
                    No subscription found
                </h4>

                <p class="mt-2 max-w-md text-sm" :class="mutedClass">
                    Your account does not have a subscription yet.
                </p>
            </div>

            <!-- Subscription Details -->
            <div
                v-else
                class="grid gap-6 p-5 sm:grid-cols-2 lg:grid-cols-4 md:p-6"
            >
                <!-- Current Plan -->
                <div>
                    <div class="flex items-center gap-2">
                        <ShieldCheck
                            :size="16"
                            :class="isDark ? 'text-cyan-400' : 'text-cyan-700'"
                        />

                        <p class="text-sm" :class="mutedClass">Current Plan</p>
                    </div>

                    <h4 class="mt-3 text-2xl font-bold" :class="titleClass">
                        {{ subscription.plan_name }}
                    </h4>

                    <p class="mt-1 text-sm" :class="mutedClass">
                        <template v-if="subscription.price !== null">
                            ${{ subscription.price }} /
                            {{ subscription.billing_interval }}
                        </template>

                        <template v-else> Plan pricing unavailable </template>
                    </p>
                </div>

                <!-- Subscription Status -->
                <div>
                    <div class="flex items-center gap-2">
                        <Activity
                            :size="16"
                            :class="isDark ? 'text-cyan-400' : 'text-cyan-700'"
                        />

                        <p class="text-sm" :class="mutedClass">
                            Subscription Status
                        </p>
                    </div>

                    <h4
                        class="mt-3 text-xl font-bold capitalize"
                        :class="
                            subscription.is_active
                                ? 'text-emerald-500'
                                : titleClass
                        "
                    >
                        {{ subscription.status.replaceAll("_", " ") }}
                    </h4>

                    <p class="mt-1 text-xs" :class="mutedClass">
                        {{
                            subscription.is_active
                                ? "Your subscription is active"
                                : "Check your subscription status"
                        }}
                    </p>
                </div>

                <!-- Billing Period -->
                <div>
                    <div class="flex items-center gap-2">
                        <CalendarDays
                            :size="16"
                            :class="isDark ? 'text-cyan-400' : 'text-cyan-700'"
                        />

                        <p class="text-sm" :class="mutedClass">
                            Current Period Ends
                        </p>
                    </div>

                    <h4 class="mt-3 text-lg font-bold" :class="titleClass">
                        {{ formatDate(subscription.current_period_end) }}
                    </h4>

                    <p class="mt-1 text-xs" :class="mutedClass">
                        {{
                            subscription.cancel_at_period_end
                                ? "Subscription ends on this date"
                                : subscription.is_active
                                  ? "Expected renewal date"
                                  : "Current billing period end"
                        }}
                    </p>
                </div>

                <!-- Auto Renewal -->
                <div>
                    <div class="flex items-center gap-2">
                        <RefreshCw
                            :size="16"
                            :class="isDark ? 'text-cyan-400' : 'text-cyan-700'"
                        />

                        <p class="text-sm" :class="mutedClass">Auto Renewal</p>
                    </div>

                    <h4 class="mt-3 text-lg font-bold" :class="titleClass">
                        {{
                            subscription.cancel_at_period_end
                                ? "Cancellation Scheduled"
                                : subscription.is_active
                                  ? "Enabled"
                                  : "Not Active"
                        }}
                    </h4>

                    <p class="mt-1 text-xs" :class="mutedClass">
                        {{
                            subscription.cancel_at_period_end
                                ? "Will not renew automatically"
                                : subscription.is_active
                                  ? "Renews unless canceled"
                                  : "Renewal depends on subscription status"
                        }}
                    </p>
                </div>
            </div>

            <!-- Stripe Customer Portal action -->
            <div
                v-if="
                    !subscriptionLoading && subscription && !subscriptionError
                "
                class="border-t px-5 py-4 md:px-6"
                :class="isDark ? 'border-white/10' : 'border-slate-200'"
            >
                <div
                    class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center"
                >
                    <p class="text-sm" :class="mutedClass">
                        Change your plan, update payment methods or view
                        invoices securely on Stripe.
                    </p>
                    <button
                        type="button"
                        :disabled="portalLoading"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-cyan-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="manageSubscription"
                    >
                        <RefreshCw
                            v-if="portalLoading"
                            :size="17"
                            class="animate-spin"
                        />
                        <ExternalLink v-else :size="17" />
                        {{
                            portalLoading
                                ? "Opening Stripe..."
                                : "Manage Subscription"
                        }}
                    </button>
                </div>
                <p
                    v-if="portalError"
                    role="alert"
                    class="mt-3 text-sm text-red-500"
                >
                    {{ portalError }}
                </p>
            </div>
        </section>

        <!-- Stores and AI Section -->
        <section class="grid gap-6 xl:grid-cols-3">
            <!-- Your Stores -->
            <div
                class="rounded-2xl border p-5 transition-colors md:p-6 xl:col-span-2"
                :class="cardClass"
            >
                <div class="mb-6 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold" :class="titleClass">
                            Your Stores
                        </h3>

                        <p class="mt-1 text-sm" :class="mutedClass">
                            Recently added stores
                        </p>
                    </div>

                    <RouterLink
                        to="/dashboard/stores"
                        class="flex items-center gap-1 text-sm font-medium"
                        :class="accentClass"
                    >
                        View all
                        <ArrowUpRight :size="16" />
                    </RouterLink>
                </div>

                <!-- Loading Skeleton -->
                <div
                    v-if="loading"
                    class="space-y-3"
                    aria-label="Loading stores"
                >
                    <div
                        v-for="item in 3"
                        :key="item"
                        class="h-19 animate-pulse rounded-xl"
                        :class="isDark ? 'bg-white/5' : 'bg-slate-100'"
                    ></div>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="py-12 text-center">
                    <AlertCircle :size="34" class="mx-auto text-red-400" />

                    <p class="mt-3 text-sm" :class="mutedClass">
                        Stores could not be loaded.
                    </p>
                </div>

                <!-- Empty State -->
                <div v-else-if="stores.length === 0" class="py-12 text-center">
                    <div
                        class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl"
                        :class="isDark ? 'bg-white/5' : 'bg-slate-100'"
                    >
                        <Store :size="30" :class="mutedClass" />
                    </div>

                    <h4 class="mt-4 font-semibold" :class="titleClass">
                        No stores yet
                    </h4>

                    <p class="mt-2 text-sm" :class="mutedClass">
                        Add a store to start setting up your workspace.
                    </p>

                    <RouterLink
                        to="/dashboard/stores"
                        class="mt-5 inline-flex text-sm font-medium"
                        :class="accentClass"
                    >
                        Add your first store
                    </RouterLink>
                </div>

                <!-- Stores List -->
                <div v-else class="space-y-3">
                    <div
                        v-for="store in stores.slice(0, 5)"
                        :key="store.id"
                        class="flex items-center gap-4 rounded-xl border p-4"
                        :class="rowClass"
                    >
                        <div
                            class="rounded-xl p-3"
                            :class="isDark ? 'bg-cyan-500/10' : 'bg-cyan-100'"
                        >
                            <Store
                                :size="19"
                                :class="
                                    isDark ? 'text-cyan-400' : 'text-cyan-700'
                                "
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-semibold"
                                :class="titleClass"
                            >
                                {{ store.store_name }}
                            </p>

                            <p
                                class="mt-1 truncate text-xs"
                                :class="mutedClass"
                            >
                                {{ store.store_url }}
                            </p>
                        </div>

                        <span
                            class="shrink-0 rounded-full px-3 py-1.5 text-xs font-medium capitalize"
                            :class="
                                store.status === 'connected'
                                    ? isDark
                                        ? 'bg-emerald-500/10 text-emerald-400'
                                        : 'bg-emerald-100 text-emerald-700'
                                    : isDark
                                      ? 'bg-amber-500/10 text-amber-400'
                                      : 'bg-amber-100 text-amber-700'
                            "
                        >
                            {{ store.status }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- AI Information Card -->
            <div
                class="flex flex-col justify-between rounded-2xl border p-6 transition-colors"
                :class="
                    isDark
                        ? 'border-cyan-500/20 bg-gradient-to-br from-[#173b52] to-[#172235]'
                        : 'border-cyan-200 bg-gradient-to-br from-cyan-50 via-white to-blue-50 shadow-sm'
                "
            >
                <div>
                    <div
                        class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl"
                        :class="isDark ? 'bg-cyan-400/10' : 'bg-cyan-100'"
                    >
                        <Sparkles
                            :size="25"
                            :class="isDark ? 'text-cyan-400' : 'text-cyan-700'"
                        />
                    </div>

                    <h3 class="text-xl font-semibold" :class="titleClass">
                        AI-Powered After-Sales
                    </h3>

                    <p class="mt-4 text-sm leading-relaxed" :class="mutedClass">
                        We're building a smarter way to manage orders, returns
                        and customer support — all in one workspace.
                    </p>
                </div>

                <!-- Development Status -->
                <div
                    class="mt-8 rounded-xl border p-4"
                    :class="
                        isDark
                            ? 'border-white/10 bg-white/5'
                            : 'border-cyan-100 bg-white/80'
                    "
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-wider"
                        :class="isDark ? 'text-cyan-300' : 'text-cyan-700'"
                    >
                        Development Status
                    </p>

                    <p class="mt-2 text-sm" :class="titleClass">
                        Workspace and store management
                    </p>

                    <div class="mt-3 flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-amber-400"></span>

                        <span class="text-xs" :class="mutedClass">
                            AI automation coming soon
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Upcoming Modules -->
        <section>
            <div class="mb-5">
                <h3 class="text-xl font-semibold" :class="titleClass">
                    Coming Next
                </h3>

                <p class="mt-1 text-sm" :class="mutedClass">
                    Modules planned for your workspace
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <div
                    v-for="module in upcomingModules"
                    :key="module.name"
                    class="rounded-2xl border p-6 transition-all duration-200"
                    :class="cardClass"
                >
                    <div
                        class="w-fit rounded-xl p-3"
                        :class="isDark ? 'bg-white/5' : 'bg-slate-100'"
                    >
                        <component
                            :is="module.icon"
                            :size="23"
                            :class="
                                isDark ? 'text-slate-300' : 'text-slate-700'
                            "
                        />
                    </div>

                    <h4 class="mt-5 font-semibold" :class="titleClass">
                        {{ module.name }}
                    </h4>

                    <p class="mt-2 text-sm" :class="mutedClass">
                        {{ module.desc }}
                    </p>

                    <div
                        class="mt-5 flex items-center gap-2 text-xs"
                        :class="subtleClass"
                    >
                        <Clock3 :size="14" />
                        Coming soon
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
