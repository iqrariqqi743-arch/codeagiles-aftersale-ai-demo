<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { RouterLink, useRouter } from "vue-router";
import {
    ArrowLeft,
    ShoppingBag,
    Search,
    RefreshCw,
    Plus,
    X,
    Eye,
    ChevronLeft,
    ChevronRight,
    PackageCheck,
    Clock3,
    Truck,
    AlertCircle,
    CheckCircle2,
    Store,
    Mail,
    Phone,
    CalendarDays,
    CreditCard,
    MapPin,
    FlaskConical,
    Package,
    Hash,
    Palette,
    Ruler,
    Layers3,
    CloudDownload,
} from "lucide-vue-next";
import { useTheme } from "../composables/useTheme";

const router = useRouter();
const { isDark } = useTheme();

const PAGE_SIZE = 20;

const STATUS_OPTIONS = [
    "all",
    "pending",
    "processing",
    "completed",
    "on-hold",
    "cancelled",
    "refunded",
    "failed",
];

const ORDER_TYPE_OPTIONS = [
    { value: "all", label: "All Orders" },
    { value: "real", label: "Real Orders" },
    { value: "demo", label: "Demo Orders" },
];

const statusStyles = {
    pending: "bg-amber-500/10 text-amber-500",
    processing: "bg-blue-500/10 text-blue-500",
    completed: "bg-emerald-500/10 text-emerald-500",
    "on-hold": "bg-orange-500/10 text-orange-500",
    cancelled: "bg-red-500/10 text-red-500",
    refunded: "bg-purple-500/10 text-purple-500",
    failed: "bg-rose-500/10 text-rose-500",
};

const orders = ref([]);
const stores = ref([]);
const loading = ref(true);
const generating = ref(false);
const syncing = ref(false);

const search = ref("");
const statusFilter = ref("all");
const storeFilter = ref("all");
const orderTypeFilter = ref("all");
const syncStoreId = ref("");
const page = ref(1);

const selectedOrder = ref(null);
const showDemoModal = ref(false);
const demoStoreId = ref("");

const error = ref("");
const success = ref("");

const cardClass = computed(() =>
    isDark.value
        ? "bg-[#172235] border-white/10"
        : "bg-white border-slate-200 shadow-sm",
);

const titleClass = computed(() =>
    isDark.value ? "text-white" : "text-slate-900",
);

const mutedClass = computed(() =>
    isDark.value ? "text-slate-400" : "text-slate-500",
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

const connectedWooStores = computed(() =>
    stores.value.filter(
        (store) =>
            store.status === "connected" &&
            store.platform?.toLowerCase() === "woocommerce",
    ),
);

const demoStores = computed(() =>
    stores.value.filter((store) => store.status !== "connected"),
);

const formatStatus = (status) =>
    String(status || "pending")
        .replace(/-/g, " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const formatMoney = (amount, currency = "USD") => {
    const value = Number(amount);

    if (!Number.isFinite(value)) return "—";

    try {
        return new Intl.NumberFormat("en-US", {
            style: "currency",
            currency: currency || "USD",
        }).format(value);
    } catch {
        return `${currency || "USD"} ${value.toFixed(2)}`;
    }
};

const formatDate = (value) => {
    if (!value) return "—";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "—";

    return date.toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const extractOrders = (data) => {
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.orders)) return data.orders;
    if (Array.isArray(data?.data?.orders)) return data.data.orders;
    if (Array.isArray(data?.data)) return data.data;
    throw new Error("Unexpected orders API response");
};

const isDemoOrder = (order) =>
    order?.is_demo === true ||
    order?.is_demo === "true" ||
    order?.is_demo === 1 ||
    order?.is_demo === "1";

const apiRequest = async (path, options = {}) => {
    const token = localStorage.getItem("accessToken");

    const response = await fetch(`/api${path}`, {
        ...options,
        headers: {
            Accept: "application/json",
            ...(options.body ? { "Content-Type": "application/json" } : {}),
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...options.headers,
        },
    });

    if (response.status === 401) {
        localStorage.removeItem("accessToken");
        localStorage.removeItem("user");
        router.replace("/login");
        throw new Error("Session expired. Please log in again.");
    }

    const data = await response.json().catch(() => null);

    if (!response.ok || data?.success === false) {
        const validationMessage = data?.errors
            ? Object.values(data.errors).flat().join(" ")
            : "";

        throw new Error(
            validationMessage ||
                data?.message ||
                `Request failed (${response.status})`,
        );
    }

    return data;
};

const handleApiError = (err, fallback) => {
    error.value = err?.message || fallback;
};

const fetchOrders = async () => {
    try {
        loading.value = true;
        error.value = "";

        const data = await apiRequest("/orders");
        orders.value = extractOrders(data);
    } catch (err) {
        handleApiError(err, "Failed to load orders");
    } finally {
        loading.value = false;
    }
};

const fetchStores = async () => {
    try {
        const data = await apiRequest("/stores");

        stores.value = Array.isArray(data?.stores)
            ? data.stores
            : Array.isArray(data?.data?.stores)
              ? data.data.stores
              : [];

        if (
            !connectedWooStores.value.some(
                (store) => String(store.id) === syncStoreId.value,
            )
        ) {
            syncStoreId.value = connectedWooStores.value.length
                ? String(connectedWooStores.value[0].id)
                : "";
        }
    } catch (err) {
        handleApiError(err, "Failed to load stores");
    }
};

const handleSyncWooCommerce = async () => {
    if (!syncStoreId.value) {
        error.value = "Please select a connected WooCommerce store.";
        return;
    }

    try {
        syncing.value = true;
        error.value = "";
        success.value = "";

        const data = await apiRequest("/orders/sync-woocommerce", {
            method: "POST",
            body: JSON.stringify({
                store_id: Number(syncStoreId.value),
            }),
        });

        const summary = data?.summary || {};

        success.value =
            `WooCommerce sync completed! ` +
            `${summary.totalFetched ?? 0} orders fetched, ` +
            `${summary.insertedOrders ?? 0} new orders added, and ` +
            `${summary.updatedOrders ?? 0} existing orders updated.`;

        await fetchOrders();
    } catch (err) {
        handleApiError(err, "Failed to sync WooCommerce orders");
    } finally {
        syncing.value = false;
    }
};

const openDemoModal = () => {
    error.value = "";
    demoStoreId.value = demoStores.value.length
        ? String(demoStores.value[0].id)
        : "";

    showDemoModal.value = true;
};

const handleGenerateDemo = async () => {
    if (!demoStoreId.value) {
        error.value = "Please select a disconnected store.";
        return;
    }

    try {
        generating.value = true;
        error.value = "";
        success.value = "";

        const data = await apiRequest("/orders/demo", {
            method: "POST",
            body: JSON.stringify({
                store_id: Number(demoStoreId.value),
            }),
        });

        showDemoModal.value = false;
        success.value = data?.message || "Demo orders generated successfully";

        await fetchOrders();
    } catch (err) {
        handleApiError(err, "Failed to generate demo orders");
    } finally {
        generating.value = false;
    }
};

const filteredOrders = computed(() => {
    const keyword = search.value.toLowerCase().trim();

    return orders.value.filter((order) => {
        const matchesSearch = [
            order.order_number,
            order.customer_name,
            order.customer_email,
            order.store_name,
            order.customer_phone,
        ].some((value) =>
            String(value || "")
                .toLowerCase()
                .includes(keyword),
        );

        const matchesStatus =
            statusFilter.value === "all" || order.status === statusFilter.value;

        const matchesStore =
            storeFilter.value === "all" ||
            String(order.store_id) === storeFilter.value;

        const demo = isDemoOrder(order);

        const matchesOrderType =
            orderTypeFilter.value === "all" ||
            (orderTypeFilter.value === "demo" && demo) ||
            (orderTypeFilter.value === "real" && !demo);

        return (
            matchesSearch && matchesStatus && matchesStore && matchesOrderType
        );
    });
});

watch([search, statusFilter, storeFilter, orderTypeFilter], () => {
    page.value = 1;
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredOrders.value.length / PAGE_SIZE)),
);

const safePage = computed(() => Math.min(page.value, totalPages.value));

const paginatedOrders = computed(() =>
    filteredOrders.value.slice(
        (safePage.value - 1) * PAGE_SIZE,
        safePage.value * PAGE_SIZE,
    ),
);

const demoOrdersCount = computed(() => orders.value.filter(isDemoOrder).length);

const realOrdersCount = computed(
    () => orders.value.length - demoOrdersCount.value,
);

const stats = computed(() => [
    {
        title: "Total Orders",
        value: orders.value.length,
        icon: ShoppingBag,
        color: "text-cyan-500",
        bg: "bg-cyan-500/10",
    },
    {
        title: "Completed",
        value: orders.value.filter((o) => o.status === "completed").length,
        icon: PackageCheck,
        color: "text-emerald-500",
        bg: "bg-emerald-500/10",
    },
    {
        title: "Processing",
        value: orders.value.filter((o) => o.status === "processing").length,
        icon: Truck,
        color: "text-blue-500",
        bg: "bg-blue-500/10",
    },
    {
        title: "Pending",
        value: orders.value.filter((o) => o.status === "pending").length,
        icon: Clock3,
        color: "text-amber-500",
        bg: "bg-amber-500/10",
    },
]);

const selectedOrderItems = computed(() =>
    Array.isArray(selectedOrder.value?.items) ? selectedOrder.value.items : [],
);

const formatVariation = (variation) => {
    if (!variation) return [];

    if (typeof variation === "string") {
        try {
            variation = JSON.parse(variation);
        } catch {
            return [];
        }
    }

    if (
        !variation ||
        typeof variation !== "object" ||
        Array.isArray(variation)
    ) {
        return [];
    }

    return Object.entries(variation).filter(
        ([, value]) => value !== null && value !== undefined && value !== "",
    );
};

const orderDetails = computed(() => {
    const order = selectedOrder.value;
    if (!order) return [];

    return [
        { icon: Store, label: "Store", value: order.store_name },
        {
            icon: ShoppingBag,
            label: "Order Status",
            value: formatStatus(order.status),
        },
        {
            icon: Mail,
            label: "Customer",
            value: `${order.customer_name || "—"}${
                order.customer_email ? ` (${order.customer_email})` : ""
            }`,
        },
        { icon: Phone, label: "Phone", value: order.customer_phone },
        {
            icon: CreditCard,
            label: "Payment Method",
            value: order.payment_method,
        },
        {
            icon: MapPin,
            label: "Shipping Address",
            value: order.shipping_address,
        },
        {
            icon: CalendarDays,
            label: "Order Date",
            value: formatDate(order.order_date),
        },
    ];
});

const onKeyDown = (event) => {
    if (event.key === "Escape" && !generating.value && !syncing.value) {
        selectedOrder.value = null;
        showDemoModal.value = false;
    }
};

onMounted(() => {
    fetchOrders();
    fetchStores();
    window.addEventListener("keydown", onKeyDown);
});

onUnmounted(() => {
    window.removeEventListener("keydown", onKeyDown);
});
</script>

<template>
    <div class="max-w-7xl mx-auto space-y-7 pb-8">
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
            class="flex flex-col xl:flex-row xl:items-center justify-between gap-5"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-widest text-cyan-500 mb-2"
                >
                    ORDER MANAGEMENT
                </p>

                <h1 class="text-3xl font-bold" :class="titleClass">Orders</h1>

                <p class="text-sm mt-2" :class="mutedClass">
                    Monitor real and demo customer orders across your stores.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <select
                    v-model="syncStoreId"
                    :disabled="syncing || connectedWooStores.length === 0"
                    aria-label="Select WooCommerce store to sync"
                    class="border rounded-xl px-4 py-3 text-sm outline-none min-w-[190px]"
                    :class="inputClass"
                >
                    <option value="">Select WooCommerce Store</option>
                    <option
                        v-for="store in connectedWooStores"
                        :key="store.id"
                        :value="String(store.id)"
                    >
                        {{ store.store_name }}
                    </option>
                </select>

                <button
                    type="button"
                    @click="handleSyncWooCommerce"
                    :disabled="syncing || loading || !syncStoreId"
                    class="inline-flex items-center gap-2 px-4 py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <RefreshCw v-if="syncing" :size="17" class="animate-spin" />
                    <CloudDownload v-else :size="17" />
                    {{ syncing ? "Syncing..." : "Sync WooCommerce" }}
                </button>

                <button
                    type="button"
                    @click="fetchOrders"
                    :disabled="loading || syncing"
                    class="inline-flex items-center gap-2 px-4 py-3 rounded-xl border text-sm disabled:opacity-50"
                    :class="secondaryClass"
                >
                    <RefreshCw
                        :size="17"
                        :class="{ 'animate-spin': loading }"
                    />
                    Refresh
                </button>

                <button
                    type="button"
                    @click="openDemoModal"
                    class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-semibold text-sm"
                >
                    <Plus :size="18" />
                    Generate Demo Orders
                </button>
            </div>
        </div>

        <!-- No connected store -->
        <div
            v-if="connectedWooStores.length === 0"
            class="flex items-start gap-3 rounded-xl p-4 bg-amber-500/10 border border-amber-500/20 text-amber-500"
        >
            <AlertCircle :size="19" class="shrink-0 mt-0.5" />

            <div>
                <p class="text-sm font-semibold">
                    No connected WooCommerce store
                </p>

                <p class="text-xs mt-1">
                    Connect your WooCommerce store before syncing real orders.
                </p>

                <RouterLink
                    to="/dashboard/stores"
                    class="inline-flex items-center gap-1 mt-2 text-sm font-semibold underline"
                >
                    <Store :size="15" />
                    Connect Store
                </RouterLink>
            </div>
        </div>

        <!-- Alerts -->
        <div
            v-if="error"
            role="alert"
            class="flex items-start gap-3 rounded-xl p-4 bg-red-500/10 border border-red-500/20 text-red-400"
        >
            <AlertCircle :size="19" class="shrink-0" />
            <span class="text-sm">{{ error }}</span>
        </div>

        <div
            v-if="success"
            role="status"
            class="flex items-start gap-3 rounded-xl p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-500"
        >
            <CheckCircle2 :size="19" class="shrink-0" />
            <span class="text-sm">{{ success }}</span>
        </div>

        <!-- Real and Demo Counts -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div
                class="rounded-2xl border p-5 flex items-center justify-between"
                :class="cardClass"
            >
                <div>
                    <p class="text-sm" :class="mutedClass">
                        Real WooCommerce Orders
                    </p>

                    <h3 class="text-3xl font-bold mt-2" :class="titleClass">
                        {{
                            loading && !orders.length ? "..." : realOrdersCount
                        }}
                    </h3>

                    <p class="text-xs text-emerald-500 mt-2">
                        Imported from connected stores
                    </p>
                </div>

                <div class="p-4 rounded-xl bg-emerald-500/10">
                    <Store :size="25" class="text-emerald-500" />
                </div>
            </div>

            <div
                class="rounded-2xl border p-5 flex items-center justify-between"
                :class="cardClass"
            >
                <div>
                    <p class="text-sm" :class="mutedClass">Demo Orders</p>

                    <h3 class="text-3xl font-bold mt-2" :class="titleClass">
                        {{
                            loading && !orders.length ? "..." : demoOrdersCount
                        }}
                    </h3>

                    <p class="text-xs text-violet-500 mt-2">
                        Sample orders for testing
                    </p>
                </div>

                <div class="p-4 rounded-xl bg-violet-500/10">
                    <FlaskConical :size="25" class="text-violet-500" />
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            <div
                v-for="stat in stats"
                :key="stat.title"
                class="rounded-2xl border p-5"
                :class="cardClass"
            >
                <div class="inline-flex p-3 rounded-xl" :class="stat.bg">
                    <component :is="stat.icon" :size="21" :class="stat.color" />
                </div>

                <p class="text-sm mt-4" :class="mutedClass">
                    {{ stat.title }}
                </p>

                <h3 class="text-3xl font-bold mt-1" :class="titleClass">
                    {{ loading && !orders.length ? "..." : stat.value }}
                </h3>
            </div>
        </div>

        <!-- Filters -->
        <div class="rounded-2xl border p-5" :class="cardClass">
            <div class="mb-4">
                <h2 class="text-base font-semibold" :class="titleClass">
                    Filter Orders
                </h2>

                <p class="text-xs mt-1" :class="mutedClass">
                    Search and filter real or demo orders.
                </p>
            </div>

            <div class="flex flex-col lg:flex-row gap-4">
                <div
                    class="flex items-center gap-3 border rounded-xl px-4 flex-1"
                    :class="inputClass"
                >
                    <Search :size="18" :class="mutedClass" />

                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search order, customer, email..."
                        aria-label="Search orders"
                        class="w-full bg-transparent outline-none py-3 text-sm"
                    />
                </div>

                <select
                    v-model="orderTypeFilter"
                    aria-label="Filter by order type"
                    class="border rounded-xl px-4 py-3 text-sm outline-none"
                    :class="inputClass"
                >
                    <option
                        v-for="option in ORDER_TYPE_OPTIONS"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>

                <select
                    v-model="storeFilter"
                    aria-label="Filter by store"
                    class="border rounded-xl px-4 py-3 text-sm outline-none"
                    :class="inputClass"
                >
                    <option value="all">All Stores</option>
                    <option
                        v-for="store in stores"
                        :key="store.id"
                        :value="String(store.id)"
                    >
                        {{ store.store_name }}
                    </option>
                </select>

                <select
                    v-model="statusFilter"
                    aria-label="Filter by status"
                    class="border rounded-xl px-4 py-3 text-sm outline-none"
                    :class="inputClass"
                >
                    <option
                        v-for="status in STATUS_OPTIONS"
                        :key="status"
                        :value="status"
                    >
                        {{
                            status === "all"
                                ? "All Statuses"
                                : formatStatus(status)
                        }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="rounded-2xl border overflow-hidden" :class="cardClass">
            <div
                class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
            >
                <div>
                    <h2 class="text-lg font-semibold" :class="titleClass">
                        {{
                            orderTypeFilter === "all"
                                ? "All Orders"
                                : orderTypeFilter === "real"
                                  ? "Real WooCommerce Orders"
                                  : "Demo Orders"
                        }}
                    </h2>

                    <p class="text-xs mt-1" :class="mutedClass">
                        {{ filteredOrders.length }} matching orders
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 text-xs px-3 py-2 rounded-full bg-emerald-500/10 text-emerald-500"
                    >
                        <Store :size="13" />
                        {{ realOrdersCount }} Real
                    </span>

                    <span
                        class="inline-flex items-center gap-1.5 text-xs px-3 py-2 rounded-full bg-violet-500/10 text-violet-500"
                    >
                        <FlaskConical :size="13" />
                        {{ demoOrdersCount }} Demo
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[950px]">
                    <thead
                        :class="
                            isDark
                                ? 'bg-white/5 text-slate-400'
                                : 'bg-slate-50 text-slate-500'
                        "
                    >
                        <tr class="text-xs uppercase tracking-wide">
                            <th class="px-5 py-4 font-medium">Order</th>
                            <th class="px-5 py-4 font-medium">Type</th>
                            <th class="px-5 py-4 font-medium">Customer</th>
                            <th class="px-5 py-4 font-medium">Store</th>
                            <th class="px-5 py-4 font-medium">Amount</th>
                            <th class="px-5 py-4 font-medium">Status</th>
                            <th class="px-5 py-4 font-medium">Date</th>
                            <th class="px-5 py-4 font-medium text-right">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-if="loading && orders.length === 0">
                            <td
                                colspan="8"
                                class="text-center py-16"
                                :class="mutedClass"
                            >
                                <RefreshCw
                                    :size="25"
                                    class="animate-spin mx-auto mb-3 text-cyan-500"
                                />
                                Loading orders...
                            </td>
                        </tr>

                        <tr v-else-if="paginatedOrders.length === 0">
                            <td colspan="8" class="text-center py-16">
                                <ShoppingBag
                                    :size="38"
                                    class="text-cyan-500 mx-auto mb-3"
                                />

                                <p class="font-semibold" :class="titleClass">
                                    No orders found
                                </p>

                                <p class="text-sm mt-2" :class="mutedClass">
                                    Try changing your filters or generate demo
                                    orders.
                                </p>
                            </td>
                        </tr>

                        <tr
                            v-for="order in paginatedOrders"
                            v-else
                            :key="order.id"
                            class="border-t"
                            :class="
                                isDark
                                    ? 'border-white/5 hover:bg-white/[0.03]'
                                    : 'border-slate-100 hover:bg-slate-50'
                            "
                        >
                            <td class="px-5 py-4">
                                <p
                                    class="text-sm font-semibold"
                                    :class="titleClass"
                                >
                                    #{{ order.order_number }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    v-if="isDemoOrder(order)"
                                    class="inline-flex items-center gap-1.5 text-[11px] font-semibold rounded-full px-3 py-1.5 bg-violet-500/10 text-violet-500"
                                >
                                    <FlaskConical :size="13" />
                                    DEMO
                                </span>

                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 text-[11px] font-semibold rounded-full px-3 py-1.5 bg-emerald-500/10 text-emerald-500"
                                >
                                    <Store :size="13" />
                                    REAL
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm font-medium"
                                    :class="titleClass"
                                >
                                    {{ order.customer_name || "—" }}
                                </p>

                                <p class="text-xs mt-1" :class="mutedClass">
                                    {{ order.customer_email || "—" }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-sm" :class="mutedClass">
                                {{ order.store_name || "—" }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm font-semibold"
                                :class="titleClass"
                            >
                                {{
                                    formatMoney(
                                        order.total_amount,
                                        order.currency,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-3 py-1.5 text-xs font-medium"
                                    :class="
                                        statusStyles[order.status] ||
                                        'bg-slate-500/10 text-slate-500'
                                    "
                                >
                                    {{ formatStatus(order.status) }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-sm" :class="mutedClass">
                                {{ formatDate(order.order_date) }}
                            </td>

                            <td class="px-5 py-4 text-right">
                                <button
                                    type="button"
                                    @click="selectedOrder = order"
                                    class="inline-flex items-center gap-2 text-cyan-500 hover:text-cyan-400 text-sm font-medium"
                                >
                                    <Eye :size="17" />
                                    View
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                class="flex flex-col sm:flex-row justify-between items-center gap-4 border-t p-5"
                :class="isDark ? 'border-white/10' : 'border-slate-200'"
            >
                <p class="text-sm" :class="mutedClass">
                    {{
                        filteredOrders.length === 0
                            ? "Showing 0 orders"
                            : `Showing ${(safePage - 1) * PAGE_SIZE + 1}–${Math.min(
                                  safePage * PAGE_SIZE,
                                  filteredOrders.length,
                              )} of ${filteredOrders.length}`
                    }}
                </p>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        :disabled="safePage <= 1"
                        @click="page = Math.max(1, page - 1)"
                        aria-label="Previous page"
                        class="p-2 border rounded-lg disabled:opacity-40"
                        :class="secondaryClass"
                    >
                        <ChevronLeft :size="18" />
                    </button>

                    <span class="text-sm" :class="titleClass">
                        {{ safePage }} / {{ totalPages }}
                    </span>

                    <button
                        type="button"
                        :disabled="safePage >= totalPages"
                        @click="page = Math.min(totalPages, page + 1)"
                        aria-label="Next page"
                        class="p-2 border rounded-lg disabled:opacity-40"
                        :class="secondaryClass"
                    >
                        <ChevronRight :size="18" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Generate Demo Modal -->
        <div
            v-if="showDemoModal"
            class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4"
            @mousedown.self="!generating && (showDemoModal = false)"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="demo-modal-title"
                class="w-full max-w-md rounded-2xl border p-6 shadow-2xl"
                :class="cardClass"
            >
                <div class="flex items-center justify-between mb-5">
                    <h2
                        id="demo-modal-title"
                        class="text-xl font-semibold"
                        :class="titleClass"
                    >
                        Generate Demo Orders
                    </h2>

                    <button
                        type="button"
                        @click="showDemoModal = false"
                        :disabled="generating"
                        aria-label="Close"
                        :class="mutedClass"
                    >
                        <X :size="21" />
                    </button>
                </div>

                <p class="text-sm mb-5" :class="mutedClass">
                    Select a disconnected store to generate 12 sample orders for
                    testing.
                </p>

                <div v-if="demoStores.length === 0" class="space-y-4">
                    <p class="text-sm text-amber-500">
                        No disconnected stores available.
                    </p>

                    <RouterLink
                        to="/dashboard/stores"
                        class="inline-flex items-center gap-2 text-cyan-500 text-sm font-medium"
                    >
                        <Store :size="17" />
                        Go to Stores
                    </RouterLink>
                </div>

                <form
                    v-else
                    class="space-y-5"
                    @submit.prevent="handleGenerateDemo"
                >
                    <div>
                        <label
                            for="demo-store"
                            class="block text-sm mb-2"
                            :class="titleClass"
                        >
                            Select Store
                        </label>

                        <select
                            id="demo-store"
                            v-model="demoStoreId"
                            required
                            class="w-full border rounded-xl px-4 py-3 outline-none"
                            :class="inputClass"
                        >
                            <option
                                v-for="store in demoStores"
                                :key="store.id"
                                :value="String(store.id)"
                            >
                                {{ store.store_name }}
                            </option>
                        </select>
                    </div>

                    <div
                        class="flex items-start gap-2 p-3 rounded-xl bg-violet-500/10 text-violet-500 text-xs"
                    >
                        <FlaskConical :size="17" class="shrink-0" />
                        These are sample orders, not real WooCommerce purchases.
                        Existing demo orders will not be duplicated.
                    </div>

                    <button
                        type="submit"
                        :disabled="generating"
                        class="w-full flex items-center justify-center gap-2 bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-semibold py-3 rounded-xl disabled:opacity-50"
                    >
                        <RefreshCw
                            v-if="generating"
                            :size="18"
                            class="animate-spin"
                        />
                        <Plus v-else :size="18" />

                        {{
                            generating
                                ? "Generating..."
                                : "Generate 12 Demo Orders"
                        }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Order Details Modal -->
        <div
            v-if="selectedOrder"
            class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4"
            @mousedown.self="selectedOrder = null"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="order-details-title"
                class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl border p-6 shadow-2xl"
                :class="cardClass"
            >
                <div class="flex items-start justify-between gap-3 mb-6">
                    <div>
                        <p class="text-xs text-cyan-500 font-semibold mb-2">
                            ORDER DETAILS
                        </p>

                        <h2
                            id="order-details-title"
                            class="text-xl font-bold"
                            :class="titleClass"
                        >
                            Order #{{ selectedOrder.order_number }}
                        </h2>

                        <span
                            v-if="isDemoOrder(selectedOrder)"
                            class="inline-flex items-center gap-1 text-xs text-violet-500 mt-2"
                        >
                            <FlaskConical :size="14" />
                            Demo Order
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1 text-xs text-emerald-500 mt-2"
                        >
                            <Store :size="14" />
                            Real WooCommerce Order
                        </span>
                    </div>

                    <button
                        type="button"
                        @click="selectedOrder = null"
                        aria-label="Close"
                        :class="mutedClass"
                    >
                        <X :size="22" />
                    </button>
                </div>

                <!-- Order Information -->
                <div class="space-y-4">
                    <div
                        v-for="item in orderDetails"
                        :key="item.label"
                        class="flex items-start gap-3 border-b pb-3"
                        :class="isDark ? 'border-white/10' : 'border-slate-100'"
                    >
                        <component
                            :is="item.icon"
                            :size="18"
                            class="text-cyan-500 mt-1 shrink-0"
                        />

                        <div class="min-w-0">
                            <p class="text-xs" :class="mutedClass">
                                {{ item.label }}
                            </p>

                            <p
                                class="text-sm font-medium mt-1 break-words"
                                :class="titleClass"
                            >
                                {{ item.value || "—" }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Order Products -->
                <div class="mt-7">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div>
                            <h3 class="text-base font-bold" :class="titleClass">
                                Order Products
                            </h3>

                            <p class="text-xs mt-1" :class="mutedClass">
                                Products included in this order
                            </p>
                        </div>

                        <span
                            class="px-3 py-1.5 rounded-full bg-cyan-500/10 text-cyan-500 text-xs font-semibold"
                        >
                            {{ selectedOrderItems.length }}
                            {{
                                selectedOrderItems.length === 1
                                    ? "Product"
                                    : "Products"
                            }}
                        </span>
                    </div>

                    <div
                        v-if="selectedOrderItems.length === 0"
                        class="rounded-xl border border-dashed p-7 text-center"
                        :class="isDark ? 'border-white/10' : 'border-slate-200'"
                    >
                        <Package
                            :size="32"
                            class="mx-auto mb-3 text-slate-400"
                        />

                        <p class="text-sm font-medium" :class="titleClass">
                            No product details available
                        </p>

                        <p class="text-xs mt-1" :class="mutedClass">
                            Product information has not been added to this
                            order.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="(product, index) in selectedOrderItems"
                            :key="
                                product.id || `${product.product_sku}-${index}`
                            "
                            class="rounded-xl border p-3 sm:p-4"
                            :class="
                                isDark
                                    ? 'border-white/10 bg-[#0f1726]/70'
                                    : 'border-slate-200 bg-slate-50'
                            "
                        >
                            <div class="flex flex-col sm:flex-row gap-4">
                                <!-- Product Image -->
                                <div
                                    class="w-full sm:w-24 h-40 sm:h-24 rounded-xl shrink-0 overflow-hidden flex items-center justify-center"
                                    :class="
                                        isDark
                                            ? 'bg-white/5'
                                            : 'bg-slate-200/70'
                                    "
                                >
                                    <img
                                        v-if="product.product_image"
                                        :src="product.product_image"
                                        :alt="product.product_name || 'Product'"
                                        class="w-full h-full object-cover"
                                        loading="lazy"
                                        @error="
                                            (event) => {
                                                event.target.style.display =
                                                    'none';
                                                event.target.parentElement
                                                    ?.querySelector(
                                                        '.image-fallback',
                                                    )
                                                    ?.classList.remove(
                                                        'hidden',
                                                    );
                                            }
                                        "
                                    />

                                    <div
                                        class="image-fallback flex flex-col items-center justify-center gap-2 text-slate-400"
                                        :class="{
                                            hidden: product.product_image,
                                        }"
                                    >
                                        <Package :size="28" />
                                        <span class="text-[10px]"
                                            >No Image</span
                                        >
                                    </div>
                                </div>

                                <!-- Product Information -->
                                <div class="flex-1 min-w-0">
                                    <div
                                        class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2"
                                    >
                                        <div class="min-w-0">
                                            <h4
                                                class="text-sm font-bold break-words"
                                                :class="titleClass"
                                            >
                                                {{
                                                    product.product_name ||
                                                    "Unnamed Product"
                                                }}
                                            </h4>

                                            <p
                                                class="text-xs mt-1 flex items-center gap-1"
                                                :class="mutedClass"
                                            >
                                                <Hash :size="12" />
                                                SKU:
                                                {{ product.product_sku || "—" }}
                                            </p>
                                        </div>

                                        <p
                                            class="text-sm font-bold text-cyan-500 shrink-0"
                                        >
                                            {{
                                                formatMoney(
                                                    product.unit_price,
                                                    selectedOrder.currency,
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap gap-2 mt-3">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs"
                                            :class="
                                                isDark
                                                    ? 'bg-white/5 text-slate-300'
                                                    : 'bg-white text-slate-600 border border-slate-200'
                                            "
                                        >
                                            <Package :size="13" />
                                            Qty: {{ product.quantity ?? 1 }}
                                        </span>

                                        <span
                                            v-if="product.color"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs"
                                            :class="
                                                isDark
                                                    ? 'bg-white/5 text-slate-300'
                                                    : 'bg-white text-slate-600 border border-slate-200'
                                            "
                                        >
                                            <Palette :size="13" />
                                            {{ product.color }}
                                        </span>

                                        <span
                                            v-if="product.size"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs"
                                            :class="
                                                isDark
                                                    ? 'bg-white/5 text-slate-300'
                                                    : 'bg-white text-slate-600 border border-slate-200'
                                            "
                                        >
                                            <Ruler :size="13" />
                                            Size: {{ product.size }}
                                        </span>
                                    </div>

                                    <!-- Variation -->
                                    <div
                                        v-if="
                                            formatVariation(product.variation)
                                                .length
                                        "
                                        class="mt-3 pt-3 border-t border-slate-500/10"
                                    >
                                        <p
                                            class="text-[11px] font-semibold mb-2 flex items-center gap-1"
                                            :class="mutedClass"
                                        >
                                            <Layers3 :size="12" />
                                            Additional Details
                                        </p>

                                        <div class="flex flex-wrap gap-2">
                                            <span
                                                v-for="[
                                                    key,
                                                    value,
                                                ] in formatVariation(
                                                    product.variation,
                                                )"
                                                :key="key"
                                                class="text-[11px] rounded-md px-2 py-1"
                                                :class="
                                                    isDark
                                                        ? 'bg-white/5 text-slate-400'
                                                        : 'bg-white text-slate-500 border border-slate-200'
                                                "
                                            >
                                                {{ formatStatus(key) }}:
                                                {{ String(value) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Total -->
                <div
                    class="rounded-xl p-4 mt-5 flex justify-between items-center"
                    :class="isDark ? 'bg-white/5' : 'bg-slate-50'"
                >
                    <div>
                        <p class="text-sm" :class="mutedClass">Total Amount</p>

                        <p class="text-xs mt-1" :class="mutedClass">
                            {{ selectedOrderItems.length }}
                            {{
                                selectedOrderItems.length === 1
                                    ? "product"
                                    : "products"
                            }}
                            in this order
                        </p>
                    </div>

                    <span class="text-xl font-bold" :class="titleClass">
                        {{
                            formatMoney(
                                selectedOrder.total_amount,
                                selectedOrder.currency,
                            )
                        }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
