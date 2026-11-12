<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import {
    CreditCard,
    Check,
    CheckCircle2,
    AlertCircle,
    ArrowRight,
    ExternalLink,
    RefreshCw,
    LoaderCircle,
    ShieldCheck,
    Sparkles,
    Crown,
    Zap,
    CalendarDays,
    Wallet,
    X,
    Store,
    ShoppingCart,
    Ticket,
    Bot,
    ChartNoAxesCombined,
    RotateCcw,
} from "lucide-vue-next";

import { useTheme } from "../composables/useTheme";

const router = useRouter();
const route = useRoute();
const { isDark } = useTheme();

const loading = ref(true);
const refreshing = ref(false);
const checkoutLoading = ref("");
const portalLoading = ref(false);

const error = ref("");
const success = ref("");

const subscription = ref(null);
const plans = ref([]);
const plansError = ref("");

const cardClass = computed(() =>
    isDark.value
        ? "border-white/10 bg-[#172235] text-white"
        : "border-slate-200 bg-white text-slate-900",
);

const mutedClass = computed(() =>
    isDark.value ? "text-slate-400" : "text-slate-500",
);

const innerClass = computed(() =>
    isDark.value
        ? "border-white/10 bg-white/5"
        : "border-slate-200 bg-slate-50",
);

const status = computed(() =>
    String(subscription.value?.status || "inactive").toLowerCase(),
);

const currentPlan = computed(() =>
    String(subscription.value?.plan || "").toLowerCase(),
);

const hasActiveSubscription = computed(() => {
    if (!["active", "trialing"].includes(status.value)) {
        return false;
    }

    const end = subscription.value?.current_period_end;

    if (!end) return true;

    const endDate = new Date(end);

    return !Number.isNaN(endDate.getTime()) && endDate.getTime() > Date.now();
});

const currentPlanDetails = computed(() =>
    plans.value.find((plan) => plan.slug === currentPlan.value),
);

function getPlanIcon(slug) {
    if (slug === "starter") return Zap;
    if (slug === "growth") return Sparkles;
    if (slug === "pro") return Crown;
    return CreditCard;
}

function formatLimit(value) {
    if (value === -1 || value === "-1") {
        return "Unlimited";
    }

    if (value === null || value === undefined) {
        return "Not specified";
    }

    return Number(value).toLocaleString("en-US");
}

function formatPrice(price) {
    const value = Number(price);

    if (!Number.isFinite(value)) {
        return "N/A";
    }

    return value.toLocaleString("en-US", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

function formatDate(value) {
    if (!value) return "Not available";

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return "Not available";
    }

    return date.toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
}

function formatAccess(value) {
    const access = String(value || "").toLowerCase();

    if (access === "none") return "Not included";
    if (access === "basic") return "Basic access";
    if (access === "full") return "Full access";
    if (access === "advanced") return "Advanced access";

    return "Not specified";
}

function getPlanFeatures(plan) {
    return [
        {
            label: `${formatLimit(plan.max_stores)} connected ${
                Number(plan.max_stores) === 1 ? "store" : "stores"
            }`,
            icon: Store,
            enabled: Number(plan.max_stores) !== 0,
        },
        {
            label: `${formatLimit(plan.max_orders_per_month)} orders/month`,
            icon: ShoppingCart,
            enabled: Number(plan.max_orders_per_month) !== 0,
        },
        {
            label: `${formatLimit(plan.max_tickets_per_month)} tickets/month`,
            icon: Ticket,
            enabled: Number(plan.max_tickets_per_month) !== 0,
        },
        {
            label:
                plan.ai_access === "none"
                    ? "AI replies not included"
                    : `${formatAccess(plan.ai_access)} to AI replies`,
            icon: Bot,
            enabled: plan.ai_access !== "none",
        },
        {
            label: `${formatAccess(plan.analytics_access)} analytics`,
            icon: ChartNoAxesCombined,
            enabled: true,
        },
        {
            label: plan.returns_enabled
                ? "Returns management included"
                : "Returns management not included",
            icon: RotateCcw,
            enabled: Boolean(plan.returns_enabled),
        },
    ];
}

function clearMessages() {
    error.value = "";
    success.value = "";
}

function getErrorMessage(data, fallback) {
    const validationError = data?.errors
        ? Object.values(data.errors).flat()[0]
        : null;

    return validationError || data?.message || fallback;
}

async function apiRequest(url, options = {}) {
    const token = localStorage.getItem("accessToken");

    if (!token) {
        router.replace("/login");
        throw new Error("Please log in to continue.");
    }

    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${token}`,
            ...(options.body ? { "Content-Type": "application/json" } : {}),
            ...options.headers,
        },
    });

    let data = {};

    try {
        data = await response.json();
    } catch {
        throw new Error("Invalid response from server.");
    }

    if (response.status === 401) {
        localStorage.removeItem("accessToken");
        localStorage.removeItem("user");

        router.replace("/login");

        throw new Error("Session expired. Please log in again.");
    }

    if (!response.ok || data.success === false) {
        throw new Error(getErrorMessage(data, "Something went wrong."));
    }

    return data;
}

async function fetchPlans() {
    plansError.value = "";

    try {
        const data = await apiRequest("/api/subscription/plans");

        if (!Array.isArray(data.plans)) {
            throw new Error("Invalid subscription plans response.");
        }

        plans.value = data.plans.map((plan) => ({
            ...plan,
            slug: String(plan.slug || "").toLowerCase(),
            returns_enabled:
                plan.returns_enabled === true ||
                plan.returns_enabled === 1 ||
                plan.returns_enabled === "1",
        }));
    } catch (err) {
        plansError.value = err.message;
    }
}

async function fetchSubscription(isRefresh = false) {
    if (isRefresh) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    error.value = "";

    try {
        const data = await apiRequest("/api/subscription/me");

        subscription.value =
            data.subscription?.data ??
            data.subscription ??
            data.data?.subscription ??
            null;
    } catch (err) {
        error.value = err.message;
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
}

async function refreshAll() {
    refreshing.value = true;

    try {
        await Promise.all([fetchSubscription(true), fetchPlans()]);
    } finally {
        refreshing.value = false;
    }
}

function redirectToStripe(url) {
    if (typeof url !== "string") return false;

    try {
        const destination = new URL(url);

        if (destination.protocol !== "https:") {
            return false;
        }

        const hostname = destination.hostname.toLowerCase();

        const allowed =
            hostname === "checkout.stripe.com" ||
            hostname.endsWith(".checkout.stripe.com") ||
            hostname === "billing.stripe.com" ||
            hostname.endsWith(".billing.stripe.com");

        if (!allowed) return false;

        window.location.assign(destination.href);
        return true;
    } catch {
        return false;
    }
}

async function subscribe(plan) {
    if (checkoutLoading.value || portalLoading.value) return;

    clearMessages();

    if (hasActiveSubscription.value) {
        error.value =
            "You already have an active subscription. Use Manage Billing to change your plan.";
        return;
    }

    checkoutLoading.value = plan.slug;

    try {
        const data = await apiRequest("/api/stripe/checkout", {
            method: "POST",
            body: JSON.stringify({
                plan: plan.slug,
            }),
        });

        const checkoutUrl =
            data.url ||
            data.checkout_url ||
            data.checkoutUrl ||
            data.session?.url ||
            data.data?.url;

        if (!redirectToStripe(checkoutUrl)) {
            throw new Error(
                "Stripe checkout URL was not returned. Please verify the checkout API response.",
            );
        }
    } catch (err) {
        error.value = err.message;
        checkoutLoading.value = "";
    }
}

async function manageBilling() {
    if (portalLoading.value || checkoutLoading.value) return;

    clearMessages();
    portalLoading.value = true;

    try {
        const data = await apiRequest("/api/subscription/portal", {
            method: "POST",
            body: JSON.stringify({}),
        });

        const portalUrl =
            data.url || data.portal_url || data.portalUrl || data.data?.url;

        if (!redirectToStripe(portalUrl)) {
            throw new Error(
                "Billing portal URL was not returned. Please verify the portal API response.",
            );
        }
    } catch (err) {
        error.value = err.message;
        portalLoading.value = false;
    }
}

onMounted(async () => {
    if (route.query.payment === "success") {
        success.value =
            "Returned from Stripe. Checking your subscription status. Activation may take a moment.";
    }

    if (route.query.payment === "cancelled") {
        error.value =
            "Checkout was cancelled. No subscription change has been confirmed.";
    }

    await Promise.all([fetchPlans(), fetchSubscription()]);

    if (route.query.payment === "success") {
        router.replace("/dashboard/billing");
    }
});
</script>

<template>
    <main class="w-full space-y-8">
        <!-- HEADER -->
        <div
            class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div
                    class="mb-3 flex items-center gap-2 text-sm"
                    :class="mutedClass"
                >
                    <CreditCard :size="17" />
                    <span>Account</span>
                    <span>/</span>
                    <span>Billing & Subscription</span>
                </div>

                <h1
                    class="flex items-center gap-3 text-2xl font-bold sm:text-3xl"
                >
                    <CreditCard :size="30" class="text-cyan-500" />
                    Billing & Subscription
                </h1>

                <p class="mt-2 text-sm" :class="mutedClass">
                    Compare plans, view usage limits and manage your
                    subscription.
                </p>
            </div>

            <button
                type="button"
                :disabled="refreshing || loading"
                @click="refreshAll"
                class="inline-flex items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition disabled:opacity-50"
                :class="cardClass"
            >
                <RefreshCw :size="17" :class="{ 'animate-spin': refreshing }" />
                Refresh Status
            </button>
        </div>

        <!-- ERROR -->
        <div
            v-if="error"
            role="alert"
            class="flex items-start gap-3 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-500"
        >
            <AlertCircle :size="20" class="shrink-0" />
            <span class="flex-1">{{ error }}</span>

            <button
                type="button"
                aria-label="Dismiss error"
                @click="error = ''"
            >
                <X :size="18" />
            </button>
        </div>

        <!-- SUCCESS -->
        <div
            v-if="success"
            role="status"
            class="flex items-start gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-500"
        >
            <CheckCircle2 :size="20" class="shrink-0" />
            <span class="flex-1">{{ success }}</span>

            <button
                type="button"
                aria-label="Dismiss message"
                @click="success = ''"
            >
                <X :size="18" />
            </button>
        </div>

        <!-- LOADING -->
        <div
            v-if="loading"
            class="flex min-h-[250px] flex-col items-center justify-center gap-4"
        >
            <LoaderCircle :size="38" class="animate-spin text-cyan-500" />

            <p class="text-sm" :class="mutedClass">
                Loading your subscription...
            </p>
        </div>

        <template v-else>
            <!-- CURRENT SUBSCRIPTION -->
            <section
                class="overflow-hidden rounded-2xl border p-6 shadow-sm md:p-8"
                :class="cardClass"
            >
                <div
                    class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between"
                >
                    <div>
                        <div class="mb-3 flex items-center gap-2">
                            <Wallet :size="21" class="text-cyan-500" />

                            <h2 class="text-xl font-bold">
                                Current Subscription
                            </h2>
                        </div>

                        <p class="text-sm" :class="mutedClass">
                            Your subscription details and account access.
                        </p>
                    </div>

                    <span
                        class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold capitalize"
                        :class="
                            hasActiveSubscription
                                ? 'bg-emerald-500/10 text-emerald-500'
                                : 'bg-amber-500/10 text-amber-500'
                        "
                    >
                        <CheckCircle2 v-if="hasActiveSubscription" :size="15" />

                        <AlertCircle v-else :size="15" />

                        {{
                            hasActiveSubscription
                                ? status
                                : subscription?.status || "No active plan"
                        }}
                    </span>
                </div>

                <div
                    v-if="hasActiveSubscription"
                    class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div class="rounded-xl border p-4" :class="innerClass">
                        <p class="text-xs" :class="mutedClass">Your Plan</p>

                        <h3 class="mt-2 text-xl font-bold capitalize">
                            {{
                                currentPlanDetails?.name ||
                                subscription?.plan ||
                                "Active Plan"
                            }}
                        </h3>
                    </div>

                    <div class="rounded-xl border p-4" :class="innerClass">
                        <p class="text-xs" :class="mutedClass">
                            Billing Period End
                        </p>

                        <div class="mt-2 flex items-center gap-2">
                            <CalendarDays :size="18" class="text-cyan-500" />

                            <span class="font-semibold">
                                {{
                                    formatDate(subscription?.current_period_end)
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-xl border p-4" :class="innerClass">
                        <p class="text-xs" :class="mutedClass">
                            Connected Store Limit
                        </p>

                        <h3 class="mt-2 text-xl font-bold">
                            {{
                                currentPlanDetails
                                    ? formatLimit(currentPlanDetails.max_stores)
                                    : "Not available"
                            }}
                        </h3>
                    </div>

                    <div
                        v-if="currentPlanDetails"
                        class="rounded-xl border p-4"
                        :class="innerClass"
                    >
                        <p class="text-xs" :class="mutedClass">
                            Monthly Order Limit
                        </p>

                        <h3 class="mt-2 text-xl font-bold">
                            {{
                                formatLimit(
                                    currentPlanDetails.max_orders_per_month,
                                )
                            }}
                        </h3>
                    </div>

                    <div
                        v-if="currentPlanDetails"
                        class="rounded-xl border p-4"
                        :class="innerClass"
                    >
                        <p class="text-xs" :class="mutedClass">
                            Monthly Ticket Limit
                        </p>

                        <h3 class="mt-2 text-xl font-bold">
                            {{
                                formatLimit(
                                    currentPlanDetails.max_tickets_per_month,
                                )
                            }}
                        </h3>
                    </div>

                    <div
                        v-if="currentPlanDetails"
                        class="rounded-xl border p-4"
                        :class="innerClass"
                    >
                        <p class="text-xs" :class="mutedClass">AI Access</p>

                        <h3 class="mt-2 text-xl font-bold">
                            {{ formatAccess(currentPlanDetails.ai_access) }}
                        </h3>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-7 rounded-xl border border-amber-500/20 bg-amber-500/10 p-5"
                >
                    <div class="flex items-start gap-3">
                        <AlertCircle
                            :size="23"
                            class="shrink-0 text-amber-500"
                        />

                        <div>
                            <h3 class="font-bold text-amber-500">
                                An active subscription is required
                            </h3>

                            <p
                                class="mt-2 text-sm leading-relaxed"
                                :class="mutedClass"
                            >
                                Select a subscription plan below to activate
                                your account and access WooCommerce support
                                features.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="hasActiveSubscription"
                    class="mt-6 flex flex-wrap gap-3"
                >
                    <button
                        type="button"
                        :disabled="portalLoading"
                        @click="manageBilling"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-500 px-5 py-3 text-sm font-bold text-slate-950 transition hover:bg-cyan-400 disabled:opacity-60"
                    >
                        <LoaderCircle
                            v-if="portalLoading"
                            :size="17"
                            class="animate-spin"
                        />

                        <ExternalLink v-else :size="17" />

                        Manage Billing
                    </button>
                </div>
            </section>

            <!-- PRICING HEADER -->
            <section>
                <div class="mb-6 text-center">
                    <div
                        class="mb-3 inline-flex items-center gap-2 rounded-full bg-cyan-500/10 px-4 py-2 text-xs font-bold text-cyan-500"
                    >
                        <Sparkles :size="15" />
                        SIMPLE MONTHLY PRICING
                    </div>

                    <h2 class="text-2xl font-bold sm:text-3xl">
                        Choose the Right Plan
                    </h2>

                    <p
                        class="mx-auto mt-3 max-w-xl text-sm leading-relaxed"
                        :class="mutedClass"
                    >
                        Compare monthly orders, support tickets, connected
                        stores and available features.
                    </p>
                </div>

                <div
                    v-if="plansError"
                    class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-500"
                >
                    {{ plansError }}
                </div>

                <div
                    v-if="!plans.length && !plansError"
                    class="rounded-xl border p-8 text-center"
                    :class="cardClass"
                >
                    <p :class="mutedClass">
                        No subscription plans are currently available.
                    </p>
                </div>

                <!-- DYNAMIC PLAN CARDS -->
                <div v-else class="grid gap-6 lg:grid-cols-3">
                    <article
                        v-for="plan in plans"
                        :key="plan.id"
                        class="relative flex flex-col overflow-hidden rounded-2xl border p-6 transition duration-200 hover:-translate-y-1 hover:shadow-xl"
                        :class="[
                            cardClass,
                            plan.slug === 'growth'
                                ? 'border-cyan-500 ring-1 ring-cyan-500/40'
                                : '',
                        ]"
                    >
                        <div
                            v-if="plan.slug === 'growth'"
                            class="absolute right-0 top-0 rounded-bl-xl bg-cyan-500 px-4 py-2 text-[11px] font-bold text-slate-950"
                        >
                            MOST POPULAR
                        </div>

                        <div
                            class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-500"
                        >
                            <component
                                :is="getPlanIcon(plan.slug)"
                                :size="25"
                            />
                        </div>

                        <h3 class="text-xl font-bold">
                            {{ plan.name }}
                        </h3>

                        <p
                            class="mt-2 min-h-[40px] text-sm"
                            :class="mutedClass"
                        >
                            {{ plan.description }}
                        </p>

                        <div class="mt-6 flex items-end gap-1">
                            <span class="text-4xl font-extrabold">
                                ${{ formatPrice(plan.price) }}
                            </span>

                            <span class="mb-1 text-sm" :class="mutedClass">
                                /{{ plan.billing_interval || "month" }}
                            </span>
                        </div>

                        <p class="mt-2 text-xs" :class="mutedClass">
                            {{ plan.currency || "USD" }} · Subscription
                        </p>

                        <!-- QUICK LIMITS -->
                        <div class="mt-6 grid grid-cols-2 gap-3">
                            <div
                                class="rounded-xl border p-3"
                                :class="innerClass"
                            >
                                <ShoppingCart
                                    :size="18"
                                    class="mb-2 text-cyan-500"
                                />

                                <p class="text-lg font-bold">
                                    {{ formatLimit(plan.max_orders_per_month) }}
                                </p>

                                <p class="text-xs" :class="mutedClass">
                                    Orders / month
                                </p>
                            </div>

                            <div
                                class="rounded-xl border p-3"
                                :class="innerClass"
                            >
                                <Ticket :size="18" class="mb-2 text-cyan-500" />

                                <p class="text-lg font-bold">
                                    {{
                                        formatLimit(plan.max_tickets_per_month)
                                    }}
                                </p>

                                <p class="text-xs" :class="mutedClass">
                                    Tickets / month
                                </p>
                            </div>

                            <div
                                class="rounded-xl border p-3"
                                :class="innerClass"
                            >
                                <Store :size="18" class="mb-2 text-cyan-500" />

                                <p class="text-lg font-bold">
                                    {{ formatLimit(plan.max_stores) }}
                                </p>

                                <p class="text-xs" :class="mutedClass">
                                    Connected stores
                                </p>
                            </div>

                            <div
                                class="rounded-xl border p-3"
                                :class="innerClass"
                            >
                                <Bot :size="18" class="mb-2 text-cyan-500" />

                                <p class="text-lg font-bold capitalize">
                                    {{ plan.ai_access }}
                                </p>

                                <p class="text-xs" :class="mutedClass">
                                    AI access
                                </p>
                            </div>
                        </div>

                        <!-- SUBSCRIBE BUTTON -->
                        <button
                            type="button"
                            :disabled="
                                Boolean(checkoutLoading) ||
                                portalLoading ||
                                hasActiveSubscription
                            "
                            @click="subscribe(plan)"
                            class="mt-7 flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-bold transition disabled:cursor-not-allowed disabled:opacity-60"
                            :class="
                                plan.slug === 'growth'
                                    ? 'bg-cyan-500 text-slate-950 hover:bg-cyan-400'
                                    : isDark
                                      ? 'bg-white/10 text-white hover:bg-white/20'
                                      : 'bg-slate-900 text-white hover:bg-slate-800'
                            "
                        >
                            <LoaderCircle
                                v-if="checkoutLoading === plan.slug"
                                :size="17"
                                class="animate-spin"
                            />

                            <CheckCircle2
                                v-else-if="
                                    hasActiveSubscription &&
                                    currentPlan === plan.slug
                                "
                                :size="17"
                            />

                            <ArrowRight v-else :size="17" />

                            {{
                                checkoutLoading === plan.slug
                                    ? "Opening Stripe..."
                                    : hasActiveSubscription &&
                                        currentPlan === plan.slug
                                      ? "Current Plan"
                                      : hasActiveSubscription
                                        ? "Manage Billing to Change"
                                        : `Subscribe to ${plan.name}`
                            }}
                        </button>

                        <div
                            class="my-6 border-t"
                            :class="
                                isDark ? 'border-white/10' : 'border-slate-200'
                            "
                        ></div>

                        <p class="mb-4 text-sm font-bold">What's included</p>

                        <!-- ALL FEATURES FROM DATABASE -->
                        <ul class="flex-1 space-y-4">
                            <li
                                v-for="(feature, index) in getPlanFeatures(
                                    plan,
                                )"
                                :key="index"
                                class="flex items-start gap-3 text-sm"
                                :class="feature.enabled ? '' : 'opacity-50'"
                            >
                                <Check
                                    v-if="feature.enabled"
                                    :size="17"
                                    class="mt-0.5 shrink-0 text-emerald-500"
                                />

                                <X
                                    v-else
                                    :size="17"
                                    class="mt-0.5 shrink-0 text-red-500"
                                />

                                <span>{{ feature.label }}</span>
                            </li>
                        </ul>
                    </article>
                </div>
            </section>

            <!-- SECURITY FOOTER -->
            <section
                class="flex flex-col gap-4 rounded-2xl border p-6 sm:flex-row sm:items-center"
                :class="cardClass"
            >
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500"
                >
                    <ShieldCheck :size="25" />
                </div>

                <div class="flex-1">
                    <h3 class="font-bold">Secure Subscription Billing</h3>

                    <p class="mt-1 text-sm" :class="mutedClass">
                        Checkout and subscription management are handled through
                        Stripe. Subscription access is confirmed by the backend.
                    </p>
                </div>

                <button
                    v-if="hasActiveSubscription"
                    type="button"
                    :disabled="portalLoading"
                    @click="manageBilling"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold disabled:opacity-60"
                    :class="cardClass"
                >
                    <ExternalLink :size="16" />
                    Billing Portal
                </button>
            </section>
        </template>
    </main>
</template>
