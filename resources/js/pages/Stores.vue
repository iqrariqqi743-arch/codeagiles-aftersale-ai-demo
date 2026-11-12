<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from "vue";

import { RouterLink, useRouter } from "vue-router";
import { useTheme } from "../composables/useTheme";

import {
    ArrowLeft,
    Globe,
    Plus,
    RefreshCw,
    Store,
    Trash2,
    X,
    CheckCircle2,
    Clock3,
    AlertCircle,
    Search,
    Plug,
    PlugZap,
    Unplug,
    KeyRound,
    Eye,
    EyeOff,
    ShieldCheck,
} from "lucide-vue-next";

const router = useRouter();
const { isDark } = useTheme();

const stores = ref([]);
const loading = ref(true);
const saving = ref(false);
const deletingId = ref(null);

const showAddModal = ref(false);
const showConnectModal = ref(false);

const selectedStore = ref(null);
const connecting = ref(false);
const disconnectingId = ref(null);

const showSecret = ref(false);

const error = ref("");
const modalError = ref("");
const success = ref("");
const search = ref("");

const form = reactive({
    store_name: "",
    store_url: "",
});

const credentials = reactive({
    consumer_key: "",
    consumer_secret: "",
});

// Theme styles
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
        ? "bg-[#0f1726] border-white/10 text-white placeholder:text-slate-500"
        : "bg-slate-50 border-slate-200 text-slate-900 placeholder:text-slate-400",
);

const secondaryClass = computed(() =>
    isDark.value
        ? "border-white/10 text-slate-300 hover:bg-white/5"
        : "border-slate-200 text-slate-700 hover:bg-slate-50",
);

// Extract stores from different API response formats
function extractStores(data) {
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.stores)) return data.stores;
    if (Array.isArray(data?.data)) return data.data;
    if (Array.isArray(data?.data?.stores)) return data.data.stores;
    if (Array.isArray(data?.result?.stores)) return data.result.stores;

    throw new Error("Unexpected stores API response format");
}

// Laravel API helper
async function apiRequest(path, options = {}) {
    const token = localStorage.getItem("accessToken");

    if (!token) {
        localStorage.removeItem("user");
        router.replace("/login");
        throw new Error("Please log in again.");
    }

    const response = await fetch(`/api${path}`, {
        ...options,
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${token}`,
            ...(options.body ? { "Content-Type": "application/json" } : {}),
            ...(options.headers || {}),
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
        throw new Error(
            `Unexpected API response (HTTP ${response.status}). Check Laravel routes.`,
        );
    }

    const data = await response.json();

    if (!response.ok || data?.success === false) {
        const validationMessage = data?.errors
            ? Object.values(data.errors).flat().join(" ")
            : "";

        throw new Error(
            validationMessage ||
                data?.message ||
                `Request failed (HTTP ${response.status})`,
        );
    }

    return data;
}

// Fetch stores
async function getStores() {
    try {
        loading.value = true;
        error.value = "";

        const data = await apiRequest("/stores");

        stores.value = extractStores(data);
    } catch (err) {
        error.value = err.message || "Failed to load stores";
    } finally {
        loading.value = false;
    }
}

function resetCredentials() {
    credentials.consumer_key = "";
    credentials.consumer_secret = "";
    showSecret.value = false;
}

function closeModals() {
    if (saving.value || connecting.value) return;

    showAddModal.value = false;
    showConnectModal.value = false;
    selectedStore.value = null;
    resetCredentials();
    modalError.value = "";
}

function handleEscape(event) {
    if (event.key === "Escape") {
        closeModals();
    }
}

watch([showAddModal, showConnectModal], ([addOpen, connectOpen]) => {
    if (addOpen || connectOpen) {
        window.addEventListener("keydown", handleEscape);
    } else {
        window.removeEventListener("keydown", handleEscape);
    }
});

onUnmounted(() => {
    window.removeEventListener("keydown", handleEscape);
});

function openAddModal() {
    form.store_name = "";
    form.store_url = "";
    modalError.value = "";
    showAddModal.value = true;
}

function openConnectModal(store) {
    selectedStore.value = store;
    resetCredentials();
    modalError.value = "";
    showConnectModal.value = true;
}

function handleModalBackdrop(event) {
    if (event.target === event.currentTarget) {
        closeModals();
    }
}

// Add store
async function handleAddStore() {
    try {
        saving.value = true;
        modalError.value = "";
        error.value = "";
        success.value = "";

        const data = await apiRequest("/stores", {
            method: "POST",
            body: JSON.stringify({
                store_name: form.store_name.trim(),
                store_url: form.store_url.trim(),
            }),
        });

        const newStore = data?.store || data?.data?.store;

        if (newStore?.id != null) {
            stores.value = [
                newStore,
                ...stores.value.filter(
                    (store) => String(store.id) !== String(newStore.id),
                ),
            ];
        }

        search.value = "";
        showAddModal.value = false;

        form.store_name = "";
        form.store_url = "";

        success.value = "Store added successfully.";

        await getStores();
    } catch (err) {
        modalError.value = err.message || "Failed to add store";
    } finally {
        saving.value = false;
    }
}

// Connect WooCommerce
async function handleConnect() {
    if (!selectedStore.value) return;

    const consumerKey = credentials.consumer_key.trim();
    const consumerSecret = credentials.consumer_secret.trim();

    if (!consumerKey || !consumerSecret) {
        modalError.value = "Both API credentials are required.";
        return;
    }

    try {
        connecting.value = true;
        modalError.value = "";
        error.value = "";
        success.value = "";

        const data = await apiRequest(
            `/stores/${selectedStore.value.id}/connect`,
            {
                method: "POST",
                body: JSON.stringify({
                    consumer_key: consumerKey,
                    consumer_secret: consumerSecret,
                }),
            },
        );

        const updatedStore = data?.store;

        if (updatedStore?.id != null) {
            stores.value = stores.value.map((store) =>
                String(store.id) === String(updatedStore.id)
                    ? updatedStore
                    : store,
            );
        }

        resetCredentials();
        selectedStore.value = null;
        showConnectModal.value = false;

        success.value = "WooCommerce connected successfully!";

        await getStores();
    } catch (err) {
        modalError.value = err.message || "Unable to connect WooCommerce";
    } finally {
        connecting.value = false;
    }
}

// Disconnect WooCommerce
async function handleDisconnect(store) {
    if (!window.confirm(`Disconnect "${store.store_name}" from WooCommerce?`)) {
        return;
    }

    try {
        disconnectingId.value = store.id;
        error.value = "";
        success.value = "";

        const data = await apiRequest(`/stores/${store.id}/disconnect`, {
            method: "POST",
        });

        stores.value = stores.value.map((item) =>
            String(item.id) === String(store.id)
                ? data?.store || {
                      ...item,
                      status: "disconnected",
                      connected_at: null,
                  }
                : item,
        );

        success.value = "WooCommerce disconnected successfully.";

        await getStores();
    } catch (err) {
        error.value = err.message || "Failed to disconnect WooCommerce";
    } finally {
        disconnectingId.value = null;
    }
}

// Delete store
async function handleDelete(id) {
    if (!window.confirm("Are you sure you want to delete this store?")) {
        return;
    }

    try {
        deletingId.value = id;
        error.value = "";
        success.value = "";

        await apiRequest(`/stores/${id}`, {
            method: "DELETE",
        });

        stores.value = stores.value.filter(
            (store) => String(store.id) !== String(id),
        );

        success.value = "Store deleted successfully.";
    } catch (err) {
        error.value = err.message || "Failed to delete store";
    } finally {
        deletingId.value = null;
    }
}

// Search
const filteredStores = computed(() => {
    const keyword = search.value.toLowerCase().trim();

    return stores.value.filter((store) =>
        [store.store_name, store.store_url, store.platform, store.status].some(
            (value) =>
                String(value || "")
                    .toLowerCase()
                    .includes(keyword),
        ),
    );
});

// Statistics
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
        color: isDark.value ? "text-cyan-400" : "text-cyan-700",
        bg: isDark.value ? "bg-cyan-500/10" : "bg-cyan-50",
    },
    {
        title: "Connected",
        value: connectedStores.value,
        icon: CheckCircle2,
        color: isDark.value ? "text-emerald-400" : "text-emerald-700",
        bg: isDark.value ? "bg-emerald-500/10" : "bg-emerald-50",
    },
    {
        title: "Disconnected",
        value: disconnectedStores.value,
        icon: Clock3,
        color: isDark.value ? "text-amber-400" : "text-amber-700",
        bg: isDark.value ? "bg-amber-500/10" : "bg-amber-50",
    },
]);

onMounted(getStores);
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
                    STORE MANAGEMENT
                </p>

                <h1 class="text-3xl font-bold" :class="titleClass">
                    My Stores
                </h1>

                <p class="mt-2 text-sm" :class="mutedClass">
                    Manage and connect your WooCommerce stores.
                </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-500 px-5 py-3 font-semibold text-slate-950 hover:bg-cyan-400"
                @click="openAddModal"
            >
                <Plus :size="19" />
                Add Store
            </button>
        </div>

        <!-- Error Alert -->
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

        <!-- Success Alert -->
        <div
            v-if="success"
            role="status"
            class="flex items-center gap-3 rounded-xl border p-4"
            :class="
                isDark
                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-700'
            "
        >
            <CheckCircle2 :size="19" class="shrink-0" />
            <span class="text-sm">{{ success }}</span>
        </div>

        <!-- Statistics -->
        <div class="grid gap-4 sm:grid-cols-3">
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

                    <div>
                        <p class="text-xs" :class="mutedClass">
                            {{ stat.title }}
                        </p>

                        <h3 class="mt-1 text-2xl font-bold" :class="titleClass">
                            {{
                                loading && stores.length === 0
                                    ? "..."
                                    : stat.value
                            }}
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Refresh -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row">
            <div
                class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 sm:max-w-md"
                :class="inputClass"
            >
                <Search :size="18" :class="mutedClass" />

                <input
                    v-model="search"
                    type="search"
                    placeholder="Search stores..."
                    aria-label="Search stores"
                    class="w-full bg-transparent text-sm outline-none"
                />
            </div>

            <button
                type="button"
                :disabled="loading"
                class="inline-flex items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm disabled:opacity-50"
                :class="secondaryClass"
                @click="getStores"
            >
                <RefreshCw :size="17" :class="{ 'animate-spin': loading }" />
                Refresh Stores
            </button>
        </div>

        <!-- Loading -->
        <div
            v-if="loading && stores.length === 0"
            class="rounded-2xl border p-12 text-center"
            :class="cardClass"
        >
            <RefreshCw
                :size="26"
                class="mx-auto mb-4 animate-spin text-cyan-500"
            />

            <p :class="mutedClass">Loading stores...</p>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="filteredStores.length === 0"
            class="rounded-2xl border p-12 text-center"
            :class="cardClass"
        >
            <Store :size="42" class="mx-auto mb-4 text-cyan-500" />

            <h2 class="text-xl font-semibold" :class="titleClass">
                {{ search ? "No matching stores" : "No stores found" }}
            </h2>

            <p class="mt-2 text-sm" :class="mutedClass">
                {{
                    error
                        ? "Could not verify the store list."
                        : search
                          ? "Try a different search keyword."
                          : "Add your first WooCommerce store to get started."
                }}
            </p>
        </div>

        <!-- Stores Grid -->
        <div v-else class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="store in filteredStores"
                :key="store.id"
                class="rounded-2xl border p-6 transition"
                :class="cardClass"
            >
                <div class="flex items-start justify-between gap-3">
                    <div
                        class="rounded-xl p-3"
                        :class="isDark ? 'bg-cyan-500/10' : 'bg-cyan-50'"
                    >
                        <Store
                            :size="24"
                            :class="isDark ? 'text-cyan-400' : 'text-cyan-700'"
                        />
                    </div>

                    <span
                        class="rounded-full px-3 py-1.5 text-xs font-medium capitalize"
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
                        {{ store.status || "disconnected" }}
                    </span>
                </div>

                <h3
                    class="mt-5 break-words text-xl font-semibold"
                    :class="titleClass"
                >
                    {{ store.store_name }}
                </h3>

                <div class="mt-2 flex items-start gap-2" :class="mutedClass">
                    <Globe :size="16" class="mt-0.5 shrink-0" />

                    <p class="break-all text-sm">
                        {{ store.store_url }}
                    </p>
                </div>

                <div
                    class="mt-5 flex items-center gap-2 text-xs"
                    :class="mutedClass"
                >
                    <ShieldCheck :size="15" />

                    {{
                        store.status === "connected"
                            ? "WooCommerce API connected"
                            : "WooCommerce API not connected"
                    }}
                </div>

                <!-- Connection Action -->
                <div class="mt-5">
                    <button
                        v-if="store.status === 'connected'"
                        type="button"
                        :disabled="
                            disconnectingId === store.id ||
                            deletingId === store.id
                        "
                        class="flex w-full items-center justify-center gap-2 rounded-xl border py-3 text-sm font-semibold transition disabled:opacity-50"
                        :class="
                            isDark
                                ? 'border-red-500/30 text-red-400 hover:bg-red-500/10'
                                : 'border-red-200 text-red-600 hover:bg-red-50'
                        "
                        @click="handleDisconnect(store)"
                    >
                        <RefreshCw
                            v-if="disconnectingId === store.id"
                            :size="17"
                            class="animate-spin"
                        />

                        <Unplug v-else :size="17" />

                        {{
                            disconnectingId === store.id
                                ? "Disconnecting..."
                                : "Disconnect WooCommerce"
                        }}
                    </button>

                    <button
                        v-else
                        type="button"
                        :disabled="deletingId === store.id"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-500 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-400 disabled:opacity-50"
                        @click="openConnectModal(store)"
                    >
                        <PlugZap :size="18" />
                        Connect WooCommerce
                    </button>
                </div>

                <!-- Card Footer -->
                <div
                    class="mt-6 flex items-center justify-between border-t pt-5"
                    :class="isDark ? 'border-white/10' : 'border-slate-200'"
                >
                    <span class="text-sm capitalize" :class="mutedClass">
                        {{ store.platform || "woocommerce" }}
                    </span>

                    <button
                        type="button"
                        :disabled="
                            deletingId === store.id ||
                            disconnectingId === store.id
                        "
                        class="flex items-center gap-2 text-sm text-red-500 hover:text-red-400 disabled:opacity-50"
                        @click="handleDelete(store.id)"
                    >
                        <RefreshCw
                            v-if="deletingId === store.id"
                            :size="17"
                            class="animate-spin"
                        />

                        <Trash2 v-else :size="17" />

                        {{ deletingId === store.id ? "Deleting..." : "Delete" }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Store Modal -->
        <div
            v-if="showAddModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            @mousedown.self="handleModalBackdrop"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="add-store-title"
                class="w-full max-w-md rounded-2xl border p-6 shadow-2xl"
                :class="cardClass"
            >
                <div class="mb-6 flex items-center justify-between">
                    <h2
                        id="add-store-title"
                        class="text-xl font-semibold"
                        :class="titleClass"
                    >
                        Add WooCommerce Store
                    </h2>

                    <button
                        type="button"
                        :disabled="saving"
                        aria-label="Close"
                        :class="mutedClass"
                        @click="closeModals"
                    >
                        <X :size="22" />
                    </button>
                </div>

                <div
                    v-if="modalError"
                    role="alert"
                    class="mb-4 rounded-xl border border-red-500/20 bg-red-500/10 p-3 text-sm text-red-400"
                >
                    {{ modalError }}
                </div>

                <form class="space-y-5" @submit.prevent="handleAddStore">
                    <div>
                        <label
                            for="store-name"
                            class="mb-2 block text-sm"
                            :class="titleClass"
                        >
                            Store Name
                        </label>

                        <input
                            id="store-name"
                            v-model="form.store_name"
                            required
                            maxlength="150"
                            placeholder="My Online Store"
                            class="w-full rounded-xl border px-4 py-3 outline-none focus:border-cyan-500"
                            :class="inputClass"
                        />
                    </div>

                    <div>
                        <label
                            for="store-url"
                            class="mb-2 block text-sm"
                            :class="titleClass"
                        >
                            Store URL
                        </label>

                        <input
                            id="store-url"
                            v-model="form.store_url"
                            type="url"
                            required
                            placeholder="https://example.com"
                            class="w-full rounded-xl border px-4 py-3 outline-none focus:border-cyan-500"
                            :class="inputClass"
                        />
                    </div>

                    <p class="text-xs" :class="mutedClass">
                        Stores remain disconnected until WooCommerce API
                        credentials are verified.
                    </p>

                    <button
                        type="submit"
                        :disabled="saving"
                        class="w-full rounded-xl bg-cyan-500 py-3 font-semibold text-slate-950 hover:bg-cyan-400 disabled:opacity-60"
                    >
                        {{ saving ? "Saving..." : "Add Store" }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Connect WooCommerce Modal -->
        <div
            v-if="showConnectModal && selectedStore"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            @mousedown.self="handleModalBackdrop"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="connect-store-title"
                class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border p-6 shadow-2xl"
                :class="cardClass"
            >
                <div class="mb-6 flex items-start justify-between gap-3">
                    <div>
                        <div class="mb-2 flex items-center gap-2">
                            <Plug :size="20" class="text-cyan-500" />

                            <span class="text-xs font-semibold text-cyan-500">
                                WOOCOMMERCE INTEGRATION
                            </span>
                        </div>

                        <h2
                            id="connect-store-title"
                            class="text-xl font-semibold"
                            :class="titleClass"
                        >
                            Connect {{ selectedStore.store_name }}
                        </h2>

                        <p class="mt-2 break-all text-sm" :class="mutedClass">
                            {{ selectedStore.store_url }}
                        </p>
                    </div>

                    <button
                        type="button"
                        :disabled="connecting"
                        aria-label="Close"
                        :class="mutedClass"
                        @click="closeModals"
                    >
                        <X :size="22" />
                    </button>
                </div>

                <div
                    v-if="modalError"
                    role="alert"
                    class="mb-5 rounded-xl border border-red-500/20 bg-red-500/10 p-3 text-sm text-red-400"
                >
                    {{ modalError }}
                </div>

                <form class="space-y-5" @submit.prevent="handleConnect">
                    <!-- Consumer Key -->
                    <div>
                        <label
                            for="consumer-key"
                            class="mb-2 block text-sm font-medium"
                            :class="titleClass"
                        >
                            WooCommerce Consumer Key
                        </label>

                        <div
                            class="flex items-center gap-3 rounded-xl border px-4"
                            :class="inputClass"
                        >
                            <KeyRound :size="18" :class="mutedClass" />

                            <input
                                id="consumer-key"
                                v-model="credentials.consumer_key"
                                type="text"
                                required
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="ck_..."
                                class="w-full bg-transparent py-3 text-sm outline-none"
                            />
                        </div>
                    </div>

                    <!-- Consumer Secret -->
                    <div>
                        <label
                            for="consumer-secret"
                            class="mb-2 block text-sm font-medium"
                            :class="titleClass"
                        >
                            WooCommerce Consumer Secret
                        </label>

                        <div
                            class="flex items-center gap-3 rounded-xl border px-4"
                            :class="inputClass"
                        >
                            <KeyRound :size="18" :class="mutedClass" />

                            <input
                                id="consumer-secret"
                                v-model="credentials.consumer_secret"
                                :type="showSecret ? 'text' : 'password'"
                                required
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="cs_..."
                                class="w-full bg-transparent py-3 text-sm outline-none"
                            />

                            <button
                                type="button"
                                :aria-label="
                                    showSecret
                                        ? 'Hide consumer secret'
                                        : 'Show consumer secret'
                                "
                                :class="mutedClass"
                                @click="showSecret = !showSecret"
                            >
                                <EyeOff v-if="showSecret" :size="18" />

                                <Eye v-else :size="18" />
                            </button>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div
                        class="rounded-xl border p-4"
                        :class="
                            isDark
                                ? 'border-white/10 bg-white/5'
                                : 'border-slate-200 bg-slate-50'
                        "
                    >
                        <p
                            class="mb-2 text-sm font-semibold"
                            :class="titleClass"
                        >
                            How to get API credentials
                        </p>

                        <ol
                            class="list-decimal space-y-1 pl-5 text-xs leading-relaxed"
                            :class="mutedClass"
                        >
                            <li>Log in to your WordPress admin.</li>
                            <li>
                                Open WooCommerce → Settings → Advanced → REST
                                API.
                            </li>
                            <li>Click Add Key.</li>
                            <li>
                                Select appropriate API permissions and generate
                                the key.
                            </li>
                            <li>
                                Copy the Consumer Key and Consumer Secret here.
                            </li>
                        </ol>
                    </div>

                    <!-- Security -->
                    <p
                        class="flex items-start gap-2 text-xs leading-relaxed"
                        :class="mutedClass"
                    >
                        <ShieldCheck
                            :size="16"
                            class="shrink-0 text-emerald-500"
                        />

                        Credentials are sent to your backend for verification
                        and encrypted storage. Never share them publicly.
                    </p>

                    <!-- Connect Button -->
                    <button
                        type="submit"
                        :disabled="connecting"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-500 py-3 font-semibold text-slate-950 hover:bg-cyan-400 disabled:opacity-60"
                    >
                        <RefreshCw
                            v-if="connecting"
                            :size="18"
                            class="animate-spin"
                        />

                        <PlugZap v-else :size="18" />

                        {{
                            connecting
                                ? "Verifying Connection..."
                                : "Connect WooCommerce"
                        }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
