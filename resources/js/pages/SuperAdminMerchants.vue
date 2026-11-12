<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import {
    Store,
    Search,
    Users,
    Eye,
    ChevronLeft,
    ChevronRight,
    LoaderCircle,
    RefreshCw,
    X,
    Mail,
    CalendarDays,
    ExternalLink,
    AlertCircle,
} from "lucide-vue-next";

import { useTheme } from "../composables/useTheme";

const router = useRouter();
const { isDark } = useTheme();

// ==========================================
// STATE
// ==========================================

const merchants = ref([]);
const loading = ref(false);
const error = ref("");

const search = ref("");
const page = ref(1);
const perPage = 10;
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: null,
    to: null,
});

const selectedMerchant = ref(null);
const merchantStores = ref([]);
const detailsLoading = ref(false);
const detailsError = ref("");
const showDetails = ref(false);

let searchTimer = null;
let listRequestId = 0;

// ==========================================
// HELPERS
// ==========================================

const cardClass = computed(() =>
    isDark.value
        ? "bg-[#172235] border-white/10 text-white"
        : "bg-white border-slate-200 text-slate-900",
);

const mutedClass = computed(() =>
    isDark.value ? "text-slate-400" : "text-slate-500",
);

const inputClass = computed(() =>
    isDark.value
        ? "bg-[#0b1220] border-white/10 text-white placeholder:text-slate-500"
        : "bg-slate-50 border-slate-200 text-slate-900 placeholder:text-slate-400",
);

const formatDate = (date) => {
    if (!date) return "—";

    const parsed = new Date(String(date).replace(" ", "T"));

    if (Number.isNaN(parsed.getTime())) return "—";

    return parsed.toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const getToken = () => localStorage.getItem("accessToken");

const handleUnauthorized = (status) => {
    if (status === 401) {
        localStorage.removeItem("accessToken");
        localStorage.removeItem("user");
        router.replace("/login");
        return true;
    }

    if (status === 403) {
        router.replace("/dashboard");
        return true;
    }

    return false;
};

// ==========================================
// FETCH MERCHANTS
// ==========================================

const fetchMerchants = async () => {
    const requestId = ++listRequestId;

    loading.value = true;
    error.value = "";

    try {
        const token = getToken();

        if (!token) {
            router.replace("/login");
            return;
        }

        const params = new URLSearchParams({
            page: String(page.value),
            per_page: String(perPage),
        });

        if (search.value.trim()) {
            params.set("search", search.value.trim());
        }

        const response = await fetch(
            `/api/super-admin/merchants?${params.toString()}`,
            {
                headers: {
                    Accept: "application/json",
                    Authorization: `Bearer ${token}`,
                },
            },
        );

        if (requestId !== listRequestId) return;

        if (handleUnauthorized(response.status)) return;

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || "Failed to fetch merchants.");
        }

        const data = result.data || {};

        merchants.value = Array.isArray(data.data) ? data.data : [];

        pagination.value = {
            current_page: Number(data.current_page) || 1,
            last_page: Number(data.last_page) || 1,
            total: Number(data.total) || 0,
            from: data.from ?? null,
            to: data.to ?? null,
        };
    } catch (err) {
        if (requestId !== listRequestId) return;

        error.value = err.message || "Unable to load merchants.";
    } finally {
        if (requestId === listRequestId) {
            loading.value = false;
        }
    }
};

// ==========================================
// SEARCH & PAGINATION
// ==========================================

watch(search, () => {
    clearTimeout(searchTimer);

    searchTimer = setTimeout(() => {
        page.value = 1;
        fetchMerchants();
    }, 400);
});

const changePage = (newPage) => {
    if (newPage < 1 || newPage > pagination.value.last_page || loading.value) {
        return;
    }

    page.value = newPage;
    fetchMerchants();
};

// ==========================================
// MERCHANT DETAILS
// ==========================================

const viewMerchant = async (merchant) => {
    showDetails.value = true;
    selectedMerchant.value = merchant;
    merchantStores.value = [];
    detailsError.value = "";
    detailsLoading.value = true;

    try {
        const token = getToken();

        if (!token) {
            router.replace("/login");
            return;
        }

        const response = await fetch(
            `/api/super-admin/merchants/${merchant.id}`,
            {
                headers: {
                    Accept: "application/json",
                    Authorization: `Bearer ${token}`,
                },
            },
        );

        if (handleUnauthorized(response.status)) return;

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || "Failed to fetch merchant details.",
            );
        }

        selectedMerchant.value = result.merchant || merchant;
        merchantStores.value = Array.isArray(result.stores)
            ? result.stores
            : [];
    } catch (err) {
        detailsError.value = err.message || "Unable to load merchant details.";
    } finally {
        detailsLoading.value = false;
    }
};

const closeDetails = () => {
    showDetails.value = false;
    selectedMerchant.value = null;
    merchantStores.value = [];
    detailsError.value = "";
};

// ==========================================
// LIFECYCLE
// ==========================================

onMounted(fetchMerchants);
</script>

<template>
    <div class="w-full">
        <!-- HEADER -->
        <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="mb-3 flex items-center gap-2 text-cyan-500">
                    <Users :size="20" />
                    <span class="text-sm font-medium">
                        Platform Administration
                    </span>
                </div>

                <h2 class="text-2xl md:text-3xl font-bold">
                    Merchants Management
                </h2>

                <p class="mt-2 text-sm" :class="mutedClass">
                    View and manage registered store owners and their stores.
                </p>
            </div>

            <button
                type="button"
                :disabled="loading"
                @click="fetchMerchants"
                class="flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium transition disabled:opacity-50"
                :class="cardClass"
            >
                <RefreshCw :size="17" :class="{ 'animate-spin': loading }" />
                Refresh
            </button>
        </div>

        <!-- SUMMARY -->
        <div
            class="mb-6 rounded-2xl border p-5 flex items-center gap-4"
            :class="cardClass"
        >
            <div class="rounded-xl bg-cyan-500/10 p-3 text-cyan-500">
                <Users :size="25" />
            </div>

            <div>
                <p class="text-sm" :class="mutedClass">Total Merchants</p>
                <p class="text-2xl font-bold">
                    {{ pagination.total.toLocaleString("en-US") }}
                </p>
            </div>
        </div>

        <!-- MERCHANTS TABLE -->
        <div
            class="overflow-hidden rounded-2xl border shadow-sm"
            :class="cardClass"
        >
            <!-- TABLE HEADER -->
            <div
                class="flex flex-wrap items-center justify-between gap-4 border-b p-5"
                :class="isDark ? 'border-white/10' : 'border-slate-200'"
            >
                <div>
                    <h3 class="font-semibold text-lg">All Merchants</h3>
                    <p class="text-xs mt-1" :class="mutedClass">
                        Search and view merchant accounts
                    </p>
                </div>

                <div class="relative w-full sm:w-72">
                    <Search
                        :size="18"
                        class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                    />

                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search name or email..."
                        class="w-full rounded-xl border py-3 pl-10 pr-4 text-sm outline-none focus:border-cyan-500"
                        :class="inputClass"
                    />
                </div>
            </div>

            <!-- LOADING -->
            <div
                v-if="loading"
                class="flex items-center justify-center gap-3 py-16"
                :class="mutedClass"
            >
                <LoaderCircle :size="23" class="animate-spin text-cyan-500" />
                Loading merchants...
            </div>

            <!-- ERROR -->
            <div
                v-else-if="error"
                class="m-5 rounded-xl border border-red-500/30 bg-red-500/10 p-5 text-red-500"
            >
                <div class="flex items-center gap-2">
                    <AlertCircle :size="18" />
                    <span>{{ error }}</span>
                </div>

                <button
                    type="button"
                    class="mt-3 text-sm underline"
                    @click="fetchMerchants"
                >
                    Try again
                </button>
            </div>

            <!-- EMPTY -->
            <div
                v-else-if="merchants.length === 0"
                class="flex flex-col items-center justify-center py-16"
                :class="mutedClass"
            >
                <Users :size="36" class="mb-3 opacity-50" />
                <p class="font-medium">No merchants found</p>
                <p class="mt-1 text-sm">
                    Try searching with a different name or email.
                </p>
            </div>

            <!-- TABLE -->
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left">
                    <thead
                        class="text-xs uppercase"
                        :class="
                            isDark
                                ? 'bg-white/5 text-slate-400'
                                : 'bg-slate-50 text-slate-500'
                        "
                    >
                        <tr>
                            <th class="px-5 py-4 font-semibold">Merchant</th>
                            <th class="px-5 py-4 font-semibold">Role</th>
                            <th class="px-5 py-4 font-semibold">Stores</th>
                            <th class="px-5 py-4 font-semibold">Joined</th>
                            <th class="px-5 py-4 font-semibold text-right">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="merchant in merchants"
                            :key="merchant.id"
                            class="border-t transition-colors"
                            :class="
                                isDark
                                    ? 'border-white/10 hover:bg-white/5'
                                    : 'border-slate-100 hover:bg-slate-50'
                            "
                        >
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-cyan-500/10 font-bold text-cyan-500"
                                    >
                                        {{
                                            merchant.name
                                                ?.charAt(0)
                                                ?.toUpperCase() || "M"
                                        }}
                                    </div>

                                    <div>
                                        <p class="text-sm font-semibold">
                                            {{ merchant.name }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs"
                                            :class="mutedClass"
                                        >
                                            {{ merchant.email }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full bg-cyan-500/10 px-3 py-1 text-xs font-medium text-cyan-500"
                                >
                                    {{ merchant.role }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <Store :size="16" class="text-cyan-500" />
                                    <span class="text-sm font-semibold">
                                        {{ merchant.total_stores ?? 0 }}
                                    </span>
                                </div>
                            </td>

                            <td class="px-5 py-4 text-sm" :class="mutedClass">
                                {{ formatDate(merchant.created_at) }}
                            </td>

                            <td class="px-5 py-4 text-right">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg bg-cyan-500/10 px-3 py-2 text-sm font-medium text-cyan-500 hover:bg-cyan-500/20"
                                    @click="viewMerchant(merchant)"
                                >
                                    <Eye :size="16" />
                                    View
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4"
                :class="isDark ? 'border-white/10' : 'border-slate-200'"
            >
                <p class="text-sm" :class="mutedClass">
                    Showing {{ pagination.from ?? 0 }}–{{
                        pagination.to ?? 0
                    }}
                    of {{ pagination.total }} merchants
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="page <= 1 || loading"
                        class="rounded-lg border p-2 disabled:opacity-40"
                        :class="cardClass"
                        @click="changePage(page - 1)"
                    >
                        <ChevronLeft :size="18" />
                    </button>

                    <span class="px-3 text-sm">
                        {{ pagination.current_page }}
                        /
                        {{ pagination.last_page }}
                    </span>

                    <button
                        type="button"
                        :disabled="page >= pagination.last_page || loading"
                        class="rounded-lg border p-2 disabled:opacity-40"
                        :class="cardClass"
                        @click="changePage(page + 1)"
                    >
                        <ChevronRight :size="18" />
                    </button>
                </div>
            </div>
        </div>

        <!-- MERCHANT DETAILS MODAL -->
        <div
            v-if="showDetails"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4"
            @click.self="closeDetails"
        >
            <div
                class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl border shadow-2xl"
                :class="cardClass"
                role="dialog"
                aria-modal="true"
                aria-label="Merchant details"
            >
                <div
                    class="sticky top-0 z-10 flex items-center justify-between border-b p-5"
                    :class="
                        isDark
                            ? 'bg-[#172235] border-white/10'
                            : 'bg-white border-slate-200'
                    "
                >
                    <div>
                        <h3 class="text-lg font-bold">Merchant Details</h3>
                        <p class="text-xs mt-1" :class="mutedClass">
                            Account information and connected stores
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg p-2 hover:bg-cyan-500/10"
                        @click="closeDetails"
                    >
                        <X :size="21" />
                    </button>
                </div>

                <div class="p-5">
                    <div
                        v-if="selectedMerchant"
                        class="rounded-xl border p-4"
                        :class="
                            isDark
                                ? 'border-white/10 bg-white/5'
                                : 'border-slate-200 bg-slate-50'
                        "
                    >
                        <div class="flex items-center gap-3 mb-5">
                            <div
                                class="w-12 h-12 rounded-full bg-cyan-500/10 text-cyan-500 flex items-center justify-center font-bold text-lg"
                            >
                                {{
                                    selectedMerchant.name
                                        ?.charAt(0)
                                        ?.toUpperCase()
                                }}
                            </div>

                            <div>
                                <p class="font-semibold">
                                    {{ selectedMerchant.name }}
                                </p>
                                <p class="text-xs" :class="mutedClass">
                                    Merchant #{{ selectedMerchant.id }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3 text-sm">
                            <div class="flex items-center gap-3">
                                <Mail :size="17" class="text-cyan-500" />
                                <span>{{ selectedMerchant.email }}</span>
                            </div>

                            <div class="flex items-center gap-3">
                                <CalendarDays
                                    :size="17"
                                    class="text-cyan-500"
                                />
                                <span>
                                    Joined
                                    {{
                                        formatDate(selectedMerchant.created_at)
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <h4 class="mt-6 mb-4 font-semibold flex items-center gap-2">
                        <Store :size="19" class="text-cyan-500" />
                        Merchant Stores ({{ merchantStores.length }})
                    </h4>

                    <div
                        v-if="detailsLoading"
                        class="flex items-center justify-center gap-2 py-10"
                        :class="mutedClass"
                    >
                        <LoaderCircle
                            :size="20"
                            class="animate-spin text-cyan-500"
                        />
                        Loading stores...
                    </div>

                    <div
                        v-else-if="detailsError"
                        class="rounded-xl bg-red-500/10 p-4 text-sm text-red-500"
                    >
                        {{ detailsError }}
                    </div>

                    <div
                        v-else-if="merchantStores.length === 0"
                        class="rounded-xl border p-5 text-center text-sm"
                        :class="mutedClass"
                    >
                        No stores found for this merchant.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="store in merchantStores"
                            :key="store.id"
                            class="rounded-xl border p-4"
                            :class="
                                isDark
                                    ? 'border-white/10 bg-white/5'
                                    : 'border-slate-200 bg-slate-50'
                            "
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <div>
                                    <p class="font-semibold text-sm">
                                        {{ store.store_name }}
                                    </p>
                                    <p class="text-xs mt-1" :class="mutedClass">
                                        {{ store.platform }}
                                    </p>
                                </div>

                                <span
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="
                                        store.status === 'connected'
                                            ? 'bg-emerald-500/10 text-emerald-500'
                                            : 'bg-amber-500/10 text-amber-500'
                                    "
                                >
                                    {{ store.status }}
                                </span>
                            </div>

                            <a
                                v-if="store.store_url"
                                :href="store.store_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-3 inline-flex items-center gap-2 break-all text-xs text-cyan-500 hover:underline"
                            >
                                {{ store.store_url }}
                                <ExternalLink :size="14" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
