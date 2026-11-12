<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import {
    ShieldCheck,
    Layers3,
    RefreshCw,
    Pencil,
    X,
    Save,
    LoaderCircle,
    AlertCircle,
    CheckCircle2,
    Store,
    ShoppingCart,
    Ticket,
    Sparkles,
    BarChart3,
    RotateCcw,
} from "lucide-vue-next";

import { useTheme } from "../composables/useTheme";

const router = useRouter();
const { isDark } = useTheme();

const plans = ref([]);
const loading = ref(true);
const refreshing = ref(false);
const saving = ref(false);
const error = ref("");
const success = ref("");
const showModal = ref(false);
const editingPlan = ref(null);
const formError = ref("");
const validationErrors = ref({});

const form = ref({});
let successTimer = null;

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

const getToken = () => localStorage.getItem("accessToken");

const apiRequest = async (url, options = {}) => {
    const token = getToken();

    if (!token) {
        router.replace("/login");
        throw new Error("Authentication required.");
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

    const contentType = response.headers.get("content-type") || "";

    if (!contentType.includes("application/json")) {
        throw new Error(
            `Server returned an invalid response (HTTP ${response.status}).`,
        );
    }

    const result = await response.json();

    if (!response.ok || result.success === false) {
        const firstError = result.errors
            ? Object.values(result.errors).flat()[0]
            : null;

        const err = new Error(
            firstError || result.message || "Something went wrong.",
        );

        err.status = response.status;
        err.errors = result.errors || {};
        throw err;
    }

    return result;
};

const handleAuthError = (err) => {
    if (err.status === 401) {
        localStorage.removeItem("accessToken");
        localStorage.removeItem("user");
        router.replace("/login");
        return true;
    }

    if (err.status === 403) {
        error.value = "Access denied. Super Admin permission required.";
        return true;
    }

    return false;
};

const showSuccess = (message) => {
    clearTimeout(successTimer);
    success.value = message;

    successTimer = setTimeout(() => {
        success.value = "";
    }, 4000);
};

const fetchPlans = async (isRefresh = false) => {
    if (isRefresh) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    error.value = "";

    try {
        const result = await apiRequest("/api/super-admin/plans");

        plans.value = Array.isArray(result.plans) ? result.plans : [];
    } catch (err) {
        if (!handleAuthError(err)) {
            error.value = err.message;
        }
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
};

const formatLimit = (value) =>
    Number(value) === -1 ? "Unlimited" : Number(value || 0).toLocaleString();

const formatPrice = (plan) =>
    new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: plan.currency || "USD",
    }).format(Number(plan.price || 0));

const formatAccess = (value) => {
    if (!value || value === "none") return "Not Included";

    return value.charAt(0).toUpperCase() + value.slice(1);
};

const openEditModal = (plan) => {
    editingPlan.value = plan;

    form.value = {
        name: plan.name || "",
        description: plan.description || "",
        max_stores: Number(plan.max_stores),
        max_orders_per_month: Number(plan.max_orders_per_month),
        max_tickets_per_month: Number(plan.max_tickets_per_month),
        ai_access: plan.ai_access || "none",
        analytics_access: plan.analytics_access || "basic",
        returns_enabled: Boolean(plan.returns_enabled),
        is_active: Boolean(plan.is_active),
        sort_order: Number(plan.sort_order || 0),
    };

    formError.value = "";
    validationErrors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    if (saving.value) return;

    showModal.value = false;
    editingPlan.value = null;
    formError.value = "";
    validationErrors.value = {};
};

const handleSubmit = async () => {
    if (!editingPlan.value || saving.value) return;

    formError.value = "";
    validationErrors.value = {};

    if (!form.value.name.trim()) {
        formError.value = "Plan name is required.";
        return;
    }

    saving.value = true;

    try {
        const payload = {
            name: form.value.name.trim(),
            description: form.value.description,
            max_stores: Number(form.value.max_stores),
            max_orders_per_month: Number(form.value.max_orders_per_month),
            max_tickets_per_month: Number(form.value.max_tickets_per_month),
            ai_access: form.value.ai_access,
            analytics_access: form.value.analytics_access,
            returns_enabled: form.value.returns_enabled,
            is_active: form.value.is_active,
            sort_order: Number(form.value.sort_order),
        };

        await apiRequest(`/api/super-admin/plans/${editingPlan.value.id}`, {
            method: "PUT",
            body: JSON.stringify(payload),
        });

        showModal.value = false;
        editingPlan.value = null;

        showSuccess("Subscription plan updated successfully.");
        await fetchPlans(true);
    } catch (err) {
        if (handleAuthError(err)) return;

        formError.value = err.message;
        validationErrors.value = err.errors || {};
    } finally {
        saving.value = false;
    }
};

const totalPlans = computed(() => plans.value.length);

const activePlans = computed(
    () => plans.value.filter((plan) => Boolean(plan.is_active)).length,
);

const inactivePlans = computed(
    () => plans.value.filter((plan) => !Boolean(plan.is_active)).length,
);

onMounted(() => fetchPlans());
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
                    <span>Plans Management</span>
                </div>

                <h1
                    class="flex items-center gap-3 text-2xl font-bold sm:text-3xl"
                >
                    <Layers3 :size="30" class="text-cyan-500" />
                    Plans Management
                </h1>

                <p class="mt-2 text-sm" :class="mutedClass">
                    Manage subscription plan limits and feature access.
                </p>
            </div>

            <button
                type="button"
                :disabled="refreshing"
                @click="fetchPlans(true)"
                class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition disabled:opacity-50"
                :class="cardClass"
            >
                <RefreshCw :size="17" :class="{ 'animate-spin': refreshing }" />
                Refresh
            </button>
        </div>

        <!-- SUCCESS -->
        <div
            v-if="success"
            class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-500"
        >
            <CheckCircle2 :size="20" />
            {{ success }}
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
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                v-for="item in [
                    { title: 'Total Plans', value: totalPlans, icon: Layers3 },
                    {
                        title: 'Active Plans',
                        value: activePlans,
                        icon: CheckCircle2,
                    },
                    {
                        title: 'Inactive Plans',
                        value: inactivePlans,
                        icon: AlertCircle,
                    },
                ]"
                :key="item.title"
                class="rounded-2xl border p-5 shadow-sm"
                :class="cardClass"
            >
                <div class="flex items-center justify-between">
                    <p class="text-sm" :class="mutedClass">
                        {{ item.title }}
                    </p>
                    <div class="rounded-xl bg-cyan-500/10 p-2.5">
                        <component
                            :is="item.icon"
                            :size="22"
                            class="text-cyan-500"
                        />
                    </div>
                </div>

                <h2 class="mt-4 text-3xl font-bold">
                    {{ loading ? "..." : item.value }}
                </h2>
            </div>
        </div>

        <!-- PLANS -->
        <div
            v-if="loading"
            class="flex items-center justify-center gap-3 py-20"
            :class="mutedClass"
        >
            <LoaderCircle :size="24" class="animate-spin text-cyan-500" />
            Loading subscription plans...
        </div>

        <div
            v-else-if="plans.length === 0"
            class="rounded-2xl border py-20 text-center"
            :class="cardClass"
        >
            <Layers3 :size="45" class="mx-auto mb-4 opacity-50" />
            <p class="font-medium">No subscription plans found</p>
        </div>

        <div v-else class="grid grid-cols-1 gap-5 xl:grid-cols-3">
            <div
                v-for="plan in plans"
                :key="plan.id"
                class="overflow-hidden rounded-2xl border shadow-sm"
                :class="cardClass"
            >
                <div class="border-b p-6" :class="borderClass">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-xl font-bold">{{ plan.name }}</h2>

                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="
                                plan.is_active
                                    ? 'bg-emerald-500/10 text-emerald-500'
                                    : 'bg-red-500/10 text-red-500'
                            "
                        >
                            {{ plan.is_active ? "Active" : "Inactive" }}
                        </span>
                    </div>

                    <p class="mt-2 text-sm" :class="mutedClass">
                        {{ plan.description || "Subscription plan" }}
                    </p>

                    <div class="mt-6 flex items-end gap-2">
                        <span class="text-4xl font-bold">
                            {{ formatPrice(plan) }}
                        </span>
                        <span class="pb-1 text-sm" :class="mutedClass">
                            / {{ plan.billing_interval }}
                        </span>
                    </div>
                </div>

                <div class="space-y-4 p-6">
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm">
                            <Store :size="17" class="text-cyan-500" />
                            Stores
                        </span>
                        <strong>{{ formatLimit(plan.max_stores) }}</strong>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm">
                            <ShoppingCart :size="17" class="text-cyan-500" />
                            Monthly Orders
                        </span>
                        <strong>
                            {{ formatLimit(plan.max_orders_per_month) }}
                        </strong>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm">
                            <Ticket :size="17" class="text-cyan-500" />
                            Monthly Tickets
                        </span>
                        <strong>
                            {{ formatLimit(plan.max_tickets_per_month) }}
                        </strong>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm">
                            <Sparkles :size="17" class="text-cyan-500" />
                            AI Access
                        </span>
                        <strong>{{ formatAccess(plan.ai_access) }}</strong>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm">
                            <BarChart3 :size="17" class="text-cyan-500" />
                            Analytics
                        </span>
                        <strong>
                            {{ formatAccess(plan.analytics_access) }}
                        </strong>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm">
                            <RotateCcw :size="17" class="text-cyan-500" />
                            Returns
                        </span>
                        <strong>
                            {{ plan.returns_enabled ? "Enabled" : "Disabled" }}
                        </strong>
                    </div>

                    <button
                        type="button"
                        @click="openEditModal(plan)"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-500 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400"
                    >
                        <Pencil :size="17" />
                        Edit Plan
                    </button>
                </div>
            </div>
        </div>

        <!-- EDIT MODAL -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4"
            @click.self="closeModal"
        >
            <div
                role="dialog"
                aria-modal="true"
                class="w-full max-w-xl overflow-hidden rounded-2xl border shadow-2xl"
                :class="cardClass"
            >
                <div
                    class="flex items-center justify-between border-b p-5"
                    :class="borderClass"
                >
                    <div>
                        <h2 class="text-xl font-bold">
                            Edit {{ editingPlan?.name }} Plan
                        </h2>
                        <p class="mt-1 text-sm" :class="mutedClass">
                            Update subscription limits and features.
                        </p>
                    </div>

                    <button
                        type="button"
                        :disabled="saving"
                        @click="closeModal"
                        class="rounded-lg p-2 hover:bg-cyan-500/10"
                    >
                        <X :size="20" />
                    </button>
                </div>

                <form @submit.prevent="handleSubmit">
                    <div class="max-h-[65vh] space-y-4 overflow-y-auto p-5">
                        <div
                            v-if="formError"
                            class="rounded-lg bg-red-500/10 p-3 text-sm text-red-500"
                        >
                            {{ formError }}
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Plan Name
                            </label>
                            <input
                                v-model="form.name"
                                required
                                class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                                :class="inputClass"
                            />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Description
                            </label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                                :class="inputClass"
                            />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div
                                v-for="field in [
                                    { key: 'max_stores', label: 'Max Stores' },
                                    {
                                        key: 'max_orders_per_month',
                                        label: 'Monthly Orders',
                                    },
                                    {
                                        key: 'max_tickets_per_month',
                                        label: 'Monthly Tickets',
                                    },
                                ]"
                                :key="field.key"
                            >
                                <label class="mb-2 block text-sm font-medium">
                                    {{ field.label }}
                                </label>

                                <input
                                    v-model.number="form[field.key]"
                                    type="number"
                                    min="-1"
                                    required
                                    class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                                    :class="inputClass"
                                />
                            </div>
                        </div>

                        <p class="text-xs" :class="mutedClass">
                            Use -1 for unlimited access.
                        </p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium">
                                    AI Access
                                </label>

                                <select
                                    v-model="form.ai_access"
                                    class="w-full rounded-xl border px-4 py-3 text-sm"
                                    :class="inputClass"
                                >
                                    <option value="none">None</option>
                                    <option value="basic">Basic</option>
                                    <option value="full">Full</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium">
                                    Analytics Access
                                </label>

                                <select
                                    v-model="form.analytics_access"
                                    class="w-full rounded-xl border px-4 py-3 text-sm"
                                    :class="inputClass"
                                >
                                    <option value="basic">Basic</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label
                                class="flex items-center gap-3 rounded-xl border p-4"
                                :class="borderClass"
                            >
                                <input
                                    v-model="form.returns_enabled"
                                    type="checkbox"
                                    class="h-4 w-4 accent-cyan-500"
                                />
                                <span class="text-sm">Returns Enabled</span>
                            </label>

                            <label
                                class="flex items-center gap-3 rounded-xl border p-4"
                                :class="borderClass"
                            >
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 accent-cyan-500"
                                />
                                <span class="text-sm">Plan Active</span>
                            </label>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Sort Order
                            </label>
                            <input
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                class="w-full rounded-xl border px-4 py-3 text-sm"
                                :class="inputClass"
                            />
                        </div>

                        <p
                            class="rounded-xl border p-3 text-xs"
                            :class="[borderClass, mutedClass]"
                        >
                            Stripe prices are managed separately. Editing plan
                            limits does not change existing Stripe subscription
                            prices.
                        </p>
                    </div>

                    <div
                        class="flex justify-end gap-3 border-t p-5"
                        :class="borderClass"
                    >
                        <button
                            type="button"
                            :disabled="saving"
                            @click="closeModal"
                            class="rounded-xl border px-4 py-2.5 text-sm font-semibold"
                            :class="cardClass"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="saving"
                            class="inline-flex items-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-cyan-400 disabled:opacity-50"
                        >
                            <LoaderCircle
                                v-if="saving"
                                :size="17"
                                class="animate-spin"
                            />
                            <Save v-else :size="17" />
                            {{ saving ? "Saving..." : "Update Plan" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</template>
