<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import {
    ShieldCheck,
    CreditCard,
    Search,
    RefreshCw,
    CheckCircle2,
    Clock3,
    Ban,
    AlertCircle,
    LoaderCircle,
    ChevronLeft,
    ChevronRight,
    Eye,
    X,
    UserRound,
    CalendarDays,
} from "lucide-vue-next";

import { useTheme } from "../composables/useTheme";

const router = useRouter();
const { isDark } = useTheme();

// ==========================================
// STATE
// ==========================================

const subscriptions = ref([]);

const stats = ref({
    total_subscriptions: 0,
    active_subscriptions: 0,
    trialing_subscriptions: 0,
    canceled_subscriptions: 0,
    past_due_subscriptions: 0,
});

const loading = ref(true);
const refreshing = ref(false);
const error = ref("");

const search = ref("");
const planFilter = ref("");
const statusFilter = ref("");

const page = ref(1);
const perPage = 10;

const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: null,
    to: null,
});

const showModal = ref(false);
const detailsLoading = ref(false);
const detailsError = ref("");
const selectedSubscription = ref(null);
const selectedPlan = ref(null);

let searchTimer = null;
let requestId = 0;
let detailsRequestId = 0;

// ==========================================
// THEME
// ==========================================

const cardClass = computed(() =>
    isDark.value
        ? "bg-[#172235] border-white/10 text-white"
        : "bg-white border-slate-200 text-slate-900",
);

const inputClass = computed(() =>
    isDark.value
        ? "bg-[#0b1220] border-white/10 text-white placeholder:text-slate-500"
        : "bg-slate-50 border-slate-200 text-slate-900 placeholder:text-slate-400",
);

const mutedClass = computed(() =>
    isDark.value ? "text-slate-400" : "text-slate-500",
);

const borderClass = computed(() =>
    isDark.value ? "border-white/10" : "border-slate-200",
);

// ==========================================
// HELPERS
// ==========================================

const getToken = () => localStorage.getItem("accessToken");

const formatDate = (date) => {
    if (!date) return "—";

    const parsed = new Date(date);

    if (Number.isNaN(parsed.getTime())) return "—";

    return parsed.toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const formatText = (value) => {
    if (!value) return "—";

    return String(value)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (char) => char.toUpperCase());
};

const formatPrice = (plan) => {
    if (!plan) return "—";

    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: plan.currency || "USD",
    }).format(Number(plan.price || 0));
};

const statusClass = (status) => {
    switch (String(status || "").toLowerCase()) {
        case "active":
            return "bg-emerald-500/10 text-emerald-500";
        case "trialing":
            return "bg-cyan-500/10 text-cyan-500";
        case "past_due":
            return "bg-orange-500/10 text-orange-500";
        case "canceled":
        case "cancelled":
            return "bg-red-500/10 text-red-500";
        default:
            return "bg-slate-500/10 text-slate-400";
    }
};

const handleAuthError = (status) => {
    if (status === 401) {
        localStorage.removeItem("accessToken");
        localStorage.removeItem("user");
        router.replace("/login");
        return true;
    }

    if (status === 403) {
        error.value = "Access denied. Super Admin permission required.";
        return true;
    }

    return false;
};

const apiRequest = async (url) => {
    const token = getToken();

    if (!token) {
        router.replace("/login");
        throw new Error("Authentication required.");
    }

    const response = await fetch(url, {
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${token}`,
        },
    });

    let result = {};

    try {
        result = await response.json();
    } catch {
        throw new Error("Invalid server response.");
    }

    if (!response.ok || result.success === false) {
        const err = new Error(result.message || "Something went wrong.");

        err.status = response.status;
        throw err;
    }

    return result;
};

// ==========================================
// FETCH SUBSCRIPTIONS
// ==========================================

const fetchSubscriptions = async (isRefresh = false) => {
    const currentRequest = ++requestId;

    if (isRefresh) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    error.value = "";

    try {
        const params = new URLSearchParams({
            page: String(page.value),
            per_page: String(perPage),
        });

        if (search.value.trim()) {
            params.set("search", search.value.trim());
        }

        if (planFilter.value) {
            params.set("plan", planFilter.value);
        }

        if (statusFilter.value) {
            params.set("status", statusFilter.value);
        }

        const result = await apiRequest(
            `/api/super-admin/subscriptions?${params.toString()}`,
        );

        if (currentRequest !== requestId) return;

        subscriptions.value = result.data?.data || [];

        pagination.value = {
            current_page: result.data?.current_page || 1,
            last_page: result.data?.last_page || 1,
            total: result.data?.total || 0,
            from: result.data?.from ?? null,
            to: result.data?.to ?? null,
        };

        stats.value = {
            total_subscriptions: Number(result.stats?.total_subscriptions) || 0,
            active_subscriptions:
                Number(result.stats?.active_subscriptions) || 0,
            trialing_subscriptions:
                Number(result.stats?.trialing_subscriptions) || 0,
            canceled_subscriptions:
                Number(result.stats?.canceled_subscriptions) || 0,
            past_due_subscriptions:
                Number(result.stats?.past_due_subscriptions) || 0,
        };
    } catch (err) {
        if (currentRequest !== requestId) return;

        if (!handleAuthError(err.status)) {
            error.value = err.message;
        }
    } finally {
        if (currentRequest === requestId) {
            loading.value = false;
            refreshing.value = false;
        }
    }
};

// ==========================================
// FILTERS & PAGINATION
// ==========================================

watch(search, () => {
    clearTimeout(searchTimer);

    searchTimer = setTimeout(() => {
        page.value = 1;
        fetchSubscriptions();
    }, 350);
});

watch([planFilter, statusFilter], () => {
    clearTimeout(searchTimer);
    page.value = 1;
    fetchSubscriptions();
});

const changePage = (newPage) => {
    if (newPage < 1 || newPage > pagination.value.last_page || loading.value) {
        return;
    }

    page.value = newPage;
    fetchSubscriptions();
};

// ==========================================
// SUBSCRIPTION DETAILS
// ==========================================

const openDetails = async (subscription) => {
    const currentRequest = ++detailsRequestId;

    selectedSubscription.value = subscription;
    selectedPlan.value = null;
    detailsError.value = "";
    detailsLoading.value = true;
    showModal.value = true;

    try {
        const result = await apiRequest(
            `/api/super-admin/subscriptions/${subscription.id}`,
        );

        if (currentRequest !== detailsRequestId) return;

        selectedSubscription.value = result.subscription;
        selectedPlan.value = result.plan_details;
    } catch (err) {
        if (currentRequest !== detailsRequestId) return;

        if (!handleAuthError(err.status)) {
            detailsError.value = err.message;
        }
    } finally {
        if (currentRequest === detailsRequestId) {
            detailsLoading.value = false;
        }
    }
};

const closeModal = () => {
    detailsRequestId++;
    showModal.value = false;
    selectedSubscription.value = null;
    selectedPlan.value = null;
    detailsError.value = "";
    detailsLoading.value = false;
};

// ==========================================
// SUMMARY CARDS
// ==========================================

const summaryCards = computed(() => [
    {
        title: "Total Subscriptions",
        value: stats.value.total_subscriptions,
        icon: CreditCard,
        color: "text-cyan-500",
        bg: "bg-cyan-500/10",
    },
    {
        title: "Active",
        value: stats.value.active_subscriptions,
        icon: CheckCircle2,
        color: "text-emerald-500",
        bg: "bg-emerald-500/10",
    },
    {
        title: "Trialing",
        value: stats.value.trialing_subscriptions,
        icon: Clock3,
        color: "text-blue-500",
        bg: "bg-blue-500/10",
    },
    {
        title: "Canceled",
        value: stats.value.canceled_subscriptions,
        icon: Ban,
        color: "text-red-500",
        bg: "bg-red-500/10",
    },
    {
        title: "Past Due",
        value: stats.value.past_due_subscriptions,
        icon: AlertCircle,
        color: "text-orange-500",
        bg: "bg-orange-500/10",
    },
]);

// ==========================================
// LIFECYCLE
// ==========================================

onMounted(() => fetchSubscriptions());

onUnmounted(() => {
    clearTimeout(searchTimer);
    requestId++;
    detailsRequestId++;
});
</script>

<template>
    <main class="w-full">
        <!-- HEADER -->
        <div
            class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div
                    class="mb-3 flex items-center gap-2 text-sm"
                    :class="mutedClass"
                >
                    <ShieldCheck :size="17" />
                    <span>Super Admin</span>
                    <span>/</span>
                    <span>Subscriptions Management</span>
                </div>

                <h1
                    class="flex items-center gap-3 text-2xl font-bold sm:text-3xl"
                >
                    <CreditCard :size="30" class="text-cyan-500" />
                    Subscriptions Management
                </h1>

                <p class="mt-2 text-sm" :class="mutedClass">
                    Monitor merchant subscriptions and billing statuses.
                </p>
            </div>

            <button
                type="button"
                :disabled="refreshing || loading"
                @click="fetchSubscriptions(true)"
                class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition disabled:opacity-50"
                :class="cardClass"
            >
                <RefreshCw :size="17" :class="{ 'animate-spin': refreshing }" />
                Refresh
            </button>
        </div>

        <!-- ERROR -->
        <div
            v-if="error"
            class="mb-6 flex items-center gap-3 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-500"
        >
            <AlertCircle :size="20" />
            <span class="flex-1">{{ error }}</span>
            <button type="button" @click="error = ''">
                <X :size="18" />
            </button>
        </div>

        <!-- STATS -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div
                v-for="card in summaryCards"
                :key="card.title"
                class="rounded-2xl border p-5 shadow-sm"
                :class="cardClass"
            >
                <div class="flex items-center justify-between">
                    <p class="text-sm" :class="mutedClass">
                        {{ card.title }}
                    </p>

                    <div class="rounded-xl p-2.5" :class="card.bg">
                        <component
                            :is="card.icon"
                            :size="22"
                            :class="card.color"
                        />
                    </div>
                </div>

                <h2 class="mt-4 text-3xl font-bold">
                    {{ loading ? "..." : card.value }}
                </h2>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="mb-6 rounded-2xl border p-5" :class="cardClass">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="relative">
                    <Search
                        :size="18"
                        class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                    />

                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search merchant, email or ID..."
                        class="w-full rounded-xl border py-3 pl-10 pr-4 text-sm outline-none focus:border-cyan-500"
                        :class="inputClass"
                    />
                </div>

                <select
                    v-model="planFilter"
                    class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                    :class="inputClass"
                >
                    <option value="">All Plans</option>
                    <option value="starter">Starter</option>
                    <option value="growth">Growth</option>
                    <option value="pro">Pro</option>
                </select>

                <select
                    v-model="statusFilter"
                    class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                    :class="inputClass"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="trialing">Trialing</option>
                    <option value="past_due">Past Due</option>
                    <option value="canceled">Canceled</option>
                    <option value="incomplete">Incomplete</option>
                    <option value="unpaid">Unpaid</option>
                </select>
            </div>
        </div>

        <!-- TABLE -->
        <div class="overflow-hidden rounded-2xl border" :class="cardClass">
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b p-5"
                :class="borderClass"
            >
                <div>
                    <h3 class="text-lg font-bold">Merchant Subscriptions</h3>
                    <p class="mt-1 text-sm" :class="mutedClass">
                        View subscription plans and merchant billing status.
                    </p>
                </div>

                <span
                    class="rounded-full border px-3 py-1 text-xs"
                    :class="[borderClass, mutedClass]"
                >
                    {{ pagination.total }} Subscriptions
                </span>
            </div>

            <div
                v-if="loading"
                class="flex items-center justify-center gap-3 py-20"
                :class="mutedClass"
            >
                <LoaderCircle :size="24" class="animate-spin text-cyan-500" />
                Loading subscriptions...
            </div>

            <div
                v-else-if="subscriptions.length === 0"
                class="py-20 text-center"
            >
                <CreditCard :size="45" class="mx-auto mb-4 opacity-50" />
                <p class="font-medium">No subscriptions found</p>
                <p class="mt-2 text-sm" :class="mutedClass">
                    Try changing your search or filters.
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[950px] text-left">
                    <thead class="border-b" :class="borderClass">
                        <tr
                            class="text-xs uppercase tracking-wider"
                            :class="mutedClass"
                        >
                            <th class="px-5 py-4 font-semibold">Merchant</th>
                            <th class="px-5 py-4 font-semibold">Plan</th>
                            <th class="px-5 py-4 font-semibold">Status</th>
                            <th class="px-5 py-4 font-semibold">Created</th>
                            <th class="px-5 py-4 font-semibold">Period End</th>
                            <th class="px-5 py-4 text-right font-semibold">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="subscription in subscriptions"
                            :key="subscription.id"
                            class="border-b transition last:border-b-0 hover:bg-cyan-500/5"
                            :class="borderClass"
                        >
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan-500/10 font-bold text-cyan-500"
                                    >
                                        {{
                                            subscription.merchant_name
                                                ?.charAt(0)
                                                ?.toUpperCase() || "M"
                                        }}
                                    </div>

                                    <div>
                                        <p class="font-semibold">
                                            {{
                                                subscription.merchant_name ||
                                                "Unknown Merchant"
                                            }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs"
                                            :class="mutedClass"
                                        >
                                            {{
                                                subscription.merchant_email ||
                                                "—"
                                            }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs"
                                            :class="mutedClass"
                                        >
                                            Subscription #{{ subscription.id }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full bg-cyan-500/10 px-3 py-1.5 text-xs font-semibold text-cyan-500"
                                >
                                    {{ formatText(subscription.plan) }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-3 py-1.5 text-xs font-semibold"
                                    :class="statusClass(subscription.status)"
                                >
                                    {{ formatText(subscription.status) }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-sm" :class="mutedClass">
                                {{ formatDate(subscription.created_at) }}
                            </td>

                            <td class="px-5 py-4 text-sm" :class="mutedClass">
                                {{
                                    formatDate(subscription.current_period_end)
                                }}
                            </td>

                            <td class="px-5 py-4 text-right">
                                <button
                                    type="button"
                                    title="View Subscription"
                                    @click="openDetails(subscription)"
                                    class="rounded-lg p-2 text-cyan-500 transition hover:bg-cyan-500/10"
                                >
                                    <Eye :size="18" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div
                class="flex flex-wrap items-center justify-between gap-4 border-t px-5 py-4"
                :class="borderClass"
            >
                <p class="text-sm" :class="mutedClass">
                    Page {{ pagination.current_page }} of
                    {{ pagination.last_page }}
                    • {{ pagination.total }} total subscriptions
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="page <= 1 || loading"
                        @click="changePage(page - 1)"
                        class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold disabled:opacity-40"
                        :class="cardClass"
                    >
                        <ChevronLeft :size="17" />
                        Previous
                    </button>

                    <button
                        type="button"
                        :disabled="page >= pagination.last_page || loading"
                        @click="changePage(page + 1)"
                        class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold disabled:opacity-40"
                        :class="cardClass"
                    >
                        Next
                        <ChevronRight :size="17" />
                    </button>
                </div>
            </div>
        </div>

        <!-- DETAILS MODAL -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4"
            @click.self="closeModal"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-label="Subscription Details"
                class="w-full max-w-xl overflow-hidden rounded-2xl border shadow-2xl"
                :class="cardClass"
            >
                <div
                    class="flex items-center justify-between border-b p-5"
                    :class="borderClass"
                >
                    <div>
                        <h2 class="text-xl font-bold">Subscription Details</h2>
                        <p class="mt-1 text-sm" :class="mutedClass">
                            Merchant subscription information.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="closeModal"
                        class="rounded-lg p-2 hover:bg-cyan-500/10"
                    >
                        <X :size="20" />
                    </button>
                </div>

                <div class="max-h-[70vh] overflow-y-auto p-5">
                    <div
                        v-if="detailsLoading"
                        class="flex items-center justify-center gap-3 py-16"
                    >
                        <LoaderCircle
                            :size="24"
                            class="animate-spin text-cyan-500"
                        />
                        Loading details...
                    </div>

                    <div
                        v-else-if="detailsError"
                        class="rounded-xl bg-red-500/10 p-4 text-sm text-red-500"
                    >
                        {{ detailsError }}
                    </div>

                    <div v-else-if="selectedSubscription" class="space-y-5">
                        <div
                            class="flex items-center gap-3 rounded-xl border p-4"
                            :class="borderClass"
                        >
                            <UserRound :size="25" class="text-cyan-500" />

                            <div>
                                <p class="font-semibold">
                                    {{
                                        selectedSubscription.merchant_name ||
                                        "Unknown Merchant"
                                    }}
                                </p>
                                <p class="text-sm" :class="mutedClass">
                                    {{ selectedSubscription.merchant_email }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs" :class="mutedClass">
                                    Subscription ID
                                </p>
                                <p class="mt-1 font-semibold">
                                    #{{ selectedSubscription.id }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs" :class="mutedClass">
                                    Merchant ID
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{ selectedSubscription.user_id }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs" :class="mutedClass">Plan</p>
                                <p class="mt-1 font-semibold">
                                    {{ formatText(selectedSubscription.plan) }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs" :class="mutedClass">
                                    Status
                                </p>
                                <span
                                    class="mt-1 inline-block rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="
                                        statusClass(selectedSubscription.status)
                                    "
                                >
                                    {{
                                        formatText(selectedSubscription.status)
                                    }}
                                </span>
                            </div>

                            <div>
                                <p class="text-xs" :class="mutedClass">
                                    Created
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{
                                        formatDate(
                                            selectedSubscription.created_at,
                                        )
                                    }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs" :class="mutedClass">
                                    Period End
                                </p>
                                <p class="mt-1 font-semibold">
                                    {{
                                        formatDate(
                                            selectedSubscription.current_period_end,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="selectedPlan"
                            class="rounded-xl border p-4"
                            :class="borderClass"
                        >
                            <div class="mb-3 flex items-center gap-2">
                                <CalendarDays
                                    :size="19"
                                    class="text-cyan-500"
                                />
                                <h3 class="font-semibold">Plan Details</h3>
                            </div>

                            <div class="flex items-center justify-between">
                                <span :class="mutedClass">Price</span>
                                <strong>
                                    {{ formatPrice(selectedPlan) }}
                                    / {{ selectedPlan.billing_interval }}
                                </strong>
                            </div>

                            <div class="mt-3 flex items-center justify-between">
                                <span :class="mutedClass">Max Stores</span>
                                <strong>
                                    {{
                                        Number(selectedPlan.max_stores) === -1
                                            ? "Unlimited"
                                            : selectedPlan.max_stores
                                    }}
                                </strong>
                            </div>

                            <div class="mt-3 flex items-center justify-between">
                                <span :class="mutedClass">
                                    Monthly Orders
                                </span>
                                <strong>
                                    {{
                                        Number(
                                            selectedPlan.max_orders_per_month,
                                        ) === -1
                                            ? "Unlimited"
                                            : selectedPlan.max_orders_per_month
                                    }}
                                </strong>
                            </div>

                            <div class="mt-3 flex items-center justify-between">
                                <span :class="mutedClass">
                                    Monthly Tickets
                                </span>
                                <strong>
                                    {{
                                        Number(
                                            selectedPlan.max_tickets_per_month,
                                        ) === -1
                                            ? "Unlimited"
                                            : selectedPlan.max_tickets_per_month
                                    }}
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t p-5" :class="borderClass">
                    <button
                        type="button"
                        @click="closeModal"
                        class="rounded-xl bg-cyan-500 px-5 py-2.5 text-sm font-semibold text-slate-950 hover:bg-cyan-400"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </main>
</template>
