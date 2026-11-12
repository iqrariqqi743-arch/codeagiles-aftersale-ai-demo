<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import {
    RotateCcw,
    Plus,
    Search,
    RefreshCw,
    CheckCircle2,
    Clock3,
    XCircle,
    Wallet,
    Eye,
    X,
    Loader2,
    ChevronLeft,
    ChevronRight,
    Package,
    Store,
    User,
    Mail,
    CalendarDays,
    FileText,
    Filter,
} from "lucide-vue-next";
import { useTheme } from "../composables/useTheme";

const router = useRouter();
const { isDark } = useTheme();

const returns = ref([]);
const stores = ref([]);
const orders = ref([]);
const tickets = ref([]);

const loading = ref(true);
const saving = ref(false);
const detailsLoading = ref(false);
const updating = ref(false);

const error = ref("");
const notice = ref("");

const search = ref("");
const statusFilter = ref("all");
const typeFilter = ref("all");
const storeFilter = ref("all");
const page = ref(1);

const showCreate = ref(false);
const showDetails = ref(false);
const selectedReturn = ref(null);

const emptyForm = () => ({
    store_id: "",
    order_id: "",
    ticket_id: "",
    customer_name: "",
    customer_email: "",
    reason: "",
    description: "",
    request_type: "return",
    refund_amount: "0",
});

const form = ref(emptyForm());
const notes = ref("");

const returnStatuses = [
    "pending",
    "approved",
    "rejected",
    "received",
    "completed",
    "cancelled",
];

const refundStatuses = [
    "not_started",
    "pending",
    "processing",
    "completed",
    "failed",
];

const requestTypes = ["return", "exchange", "refund"];

const returnTransitions = {
    pending: ["approved", "rejected", "cancelled"],
    approved: ["received", "completed", "cancelled"],
    received: ["completed"],
    rejected: [],
    completed: [],
    cancelled: [],
};

const refundTransitions = {
    not_started: ["pending"],
    pending: ["processing", "failed"],
    processing: ["completed", "failed"],
    failed: ["pending"],
    completed: [],
};

const panel = computed(() =>
    isDark.value ? "border-white/10 bg-[#111827]" : "border-slate-200 bg-white",
);

const muted = computed(() =>
    isDark.value ? "text-slate-400" : "text-slate-500",
);

const inputClass = computed(
    () =>
        `w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 ${
            isDark.value
                ? "border-white/10 bg-[#0b1220] text-white placeholder:text-slate-500"
                : "border-slate-200 bg-white text-slate-900 placeholder:text-slate-400"
        }`,
);

const primaryButton =
    "inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50";

const secondaryButton = computed(
    () =>
        `inline-flex items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-medium transition disabled:opacity-40 ${
            isDark.value
                ? "border-white/10 bg-white/5 text-slate-200 hover:bg-white/10"
                : "border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
        }`,
);

const formatLabel = (value) =>
    String(value || "")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const formatDate = (value) => {
    if (!value) return "—";

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? "—"
        : date.toLocaleString("en-US", {
              month: "short",
              day: "numeric",
              year: "numeric",
              hour: "numeric",
              minute: "2-digit",
          });
};

const formatMoney = (amount, currency = "USD") => {
    const number = Number(amount || 0);

    try {
        return new Intl.NumberFormat("en-US", {
            style: "currency",
            currency: currency || "USD",
        }).format(number);
    } catch {
        return `${currency || "USD"} ${number.toFixed(2)}`;
    }
};

const statusBadge = (status) => {
    const styles = {
        pending: "bg-amber-500/10 text-amber-500",
        approved: "bg-blue-500/10 text-blue-500",
        rejected: "bg-red-500/10 text-red-500",
        received: "bg-violet-500/10 text-violet-500",
        completed: "bg-emerald-500/10 text-emerald-500",
        cancelled: "bg-slate-500/10 text-slate-400",
    };

    return `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
        styles[status] || styles.pending
    }`;
};

const refundBadge = (status) => {
    const styles = {
        not_started: "bg-slate-500/10 text-slate-400",
        pending: "bg-amber-500/10 text-amber-500",
        processing: "bg-blue-500/10 text-blue-500",
        completed: "bg-emerald-500/10 text-emerald-500",
        failed: "bg-red-500/10 text-red-500",
    };

    return `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
        styles[status] || styles.not_started
    }`;
};

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

    let data = {};

    try {
        data = await response.json();
    } catch {
        data = {};
    }

    if (response.status === 401) {
        localStorage.removeItem("accessToken");
        localStorage.removeItem("user");
        router.push("/login");
        throw new Error("Session expired. Please log in again.");
    }

    if (!response.ok || data.success === false) {
        const validationMessage = data.errors
            ? Object.values(data.errors).flat().join(" ")
            : "";

        throw new Error(
            validationMessage ||
                data.message ||
                `Request failed (${response.status})`,
        );
    }

    return data;
};

const handleError = (err, fallback) => {
    error.value = err?.message || fallback;
};

const fetchReturns = async () => {
    try {
        loading.value = true;
        error.value = "";

        const data = await apiRequest("/returns");

        returns.value = Array.isArray(data.returns) ? data.returns : [];
    } catch (err) {
        handleError(err, "Failed to fetch returns");
    } finally {
        loading.value = false;
    }
};

const fetchDependencies = async () => {
    const results = await Promise.allSettled([
        apiRequest("/stores"),
        apiRequest("/orders"),
        apiRequest("/tickets"),
    ]);

    const readList = (result, key) => {
        if (result.status !== "fulfilled") return [];

        const data = result.value;
        const list = Array.isArray(data)
            ? data
            : data?.[key] || data?.data || [];

        return Array.isArray(list) ? list : [];
    };

    stores.value = readList(results[0], "stores");
    orders.value = readList(results[1], "orders");
    tickets.value = readList(results[2], "tickets");
};

const refreshAll = async () => {
    await Promise.all([fetchReturns(), fetchDependencies()]);
};

onMounted(refreshAll);

watch([search, statusFilter, typeFilter, storeFilter], () => {
    page.value = 1;
});

const stats = computed(() => ({
    total: returns.value.length,
    pending: returns.value.filter((item) => item.status === "pending").length,
    approved: returns.value.filter((item) =>
        ["approved", "received"].includes(item.status),
    ).length,
    completed: returns.value.filter((item) => item.status === "completed")
        .length,
}));

const cards = computed(() => [
    {
        label: "Total Requests",
        value: stats.value.total,
        icon: RotateCcw,
        color: "text-indigo-500",
        bg: "bg-indigo-500/10",
    },
    {
        label: "Pending Review",
        value: stats.value.pending,
        icon: Clock3,
        color: "text-amber-500",
        bg: "bg-amber-500/10",
    },
    {
        label: "Approved / Received",
        value: stats.value.approved,
        icon: Package,
        color: "text-blue-500",
        bg: "bg-blue-500/10",
    },
    {
        label: "Completed",
        value: stats.value.completed,
        icon: CheckCircle2,
        color: "text-emerald-500",
        bg: "bg-emerald-500/10",
    },
]);

const filtered = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    return returns.value.filter((item) => {
        const content = [
            item.return_number,
            item.customer_name,
            item.customer_email,
            item.order_number,
            item.ticket_number,
            item.store_name,
            item.reason,
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return (
            (!keyword || content.includes(keyword)) &&
            (statusFilter.value === "all" ||
                item.status === statusFilter.value) &&
            (typeFilter.value === "all" ||
                item.request_type === typeFilter.value) &&
            (storeFilter.value === "all" ||
                String(item.store_id) === storeFilter.value)
        );
    });
});

const perPage = 8;

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filtered.value.length / perPage)),
);

const currentPage = computed(() => Math.min(page.value, totalPages.value));

const visibleReturns = computed(() =>
    filtered.value.slice(
        (currentPage.value - 1) * perPage,
        currentPage.value * perPage,
    ),
);

const availableOrders = computed(() =>
    orders.value.filter(
        (order) => String(order.store_id) === String(form.value.store_id),
    ),
);

const availableTickets = computed(() =>
    tickets.value.filter(
        (ticket) =>
            String(ticket.store_id) === String(form.value.store_id) &&
            String(ticket.order_id) === String(form.value.order_id),
    ),
);

const selectedOrder = computed(() =>
    availableOrders.value.find(
        (order) => String(order.id) === String(form.value.order_id),
    ),
);

const changeStore = () => {
    form.value.order_id = "";
    form.value.ticket_id = "";
    form.value.customer_name = "";
    form.value.customer_email = "";
};

const changeOrder = () => {
    const order = selectedOrder.value;

    form.value.ticket_id = "";
    form.value.customer_name = order?.customer_name || "";
    form.value.customer_email = order?.customer_email || "";
};

const changeRequestType = () => {
    if (form.value.request_type === "exchange") {
        form.value.refund_amount = "0";
    }
};

const openCreate = () => {
    form.value = emptyForm();
    error.value = "";
    notice.value = "";
    showCreate.value = true;
};

const closeCreate = () => {
    showCreate.value = false;
    error.value = "";
};

const fetchReturnDetails = async (id) => {
    const data = await apiRequest(`/returns/${id}`);

    if (!data.return_request) {
        throw new Error("Return request details not found");
    }

    selectedReturn.value = data.return_request;
    notes.value = data.return_request.admin_notes || "";

    return data.return_request;
};

const openDetails = async (id) => {
    try {
        showDetails.value = true;
        detailsLoading.value = true;
        selectedReturn.value = null;
        error.value = "";

        await fetchReturnDetails(id);
    } catch (err) {
        handleError(err, "Failed to load return details");
        showDetails.value = false;
    } finally {
        detailsLoading.value = false;
    }
};

const closeDetails = () => {
    showDetails.value = false;
    selectedReturn.value = null;
    error.value = "";
};

const createReturn = async () => {
    if (
        !form.value.store_id ||
        !form.value.order_id ||
        !form.value.customer_name.trim() ||
        !form.value.reason.trim()
    ) {
        error.value = "Please complete all required fields";
        return;
    }

    try {
        saving.value = true;
        error.value = "";
        notice.value = "";

        const payload = {
            ...form.value,
            store_id: Number(form.value.store_id),
            order_id: Number(form.value.order_id),
            ticket_id: form.value.ticket_id
                ? Number(form.value.ticket_id)
                : null,
            customer_name: form.value.customer_name.trim(),
            customer_email: form.value.customer_email.trim(),
            reason: form.value.reason.trim(),
            description: form.value.description.trim(),
            refund_amount:
                form.value.request_type === "exchange"
                    ? 0
                    : Number(form.value.refund_amount),
        };

        const data = await apiRequest("/returns", {
            method: "POST",
            body: JSON.stringify(payload),
        });

        if (data.success) {
            closeCreate();
            form.value = emptyForm();
            notice.value = "Return request created successfully";
            await fetchReturns();
        }
    } catch (err) {
        handleError(err, "Failed to create return");
    } finally {
        saving.value = false;
    }
};

const updateStatus = async (status) => {
    if (!selectedReturn.value) return;

    const returnId = selectedReturn.value.id;

    try {
        updating.value = true;
        error.value = "";
        notice.value = "";

        const data = await apiRequest(`/returns/${returnId}/status`, {
            method: "PATCH",
            body: JSON.stringify({
                status,
                admin_notes: notes.value,
            }),
        });

        if (data.success) {
            await Promise.all([fetchReturnDetails(returnId), fetchReturns()]);

            notice.value = "Return status updated successfully";
        }
    } catch (err) {
        handleError(err, "Failed to update return status");
    } finally {
        updating.value = false;
    }
};

const saveNotes = async () => {
    if (!selectedReturn.value) return;
    await updateStatus(selectedReturn.value.status);
};

const updateRefund = async (refundStatus) => {
    if (!selectedReturn.value) return;

    const returnId = selectedReturn.value.id;

    try {
        updating.value = true;
        error.value = "";
        notice.value = "";

        const data = await apiRequest(`/returns/${returnId}/refund`, {
            method: "PATCH",
            body: JSON.stringify({
                refund_status: refundStatus,
            }),
        });

        if (data.success) {
            await Promise.all([fetchReturnDetails(returnId), fetchReturns()]);

            notice.value =
                "Refund tracking updated. No actual payment was executed.";
        }
    } catch (err) {
        handleError(err, "Failed to update refund tracking");
    } finally {
        updating.value = false;
    }
};

const detailsCards = computed(() => {
    const item = selectedReturn.value;

    if (!item) return [];

    return [
        {
            icon: User,
            label: "Customer",
            value: item.customer_name,
        },
        {
            icon: Mail,
            label: "Email",
            value: item.customer_email || "—",
        },
        {
            icon: Store,
            label: "Store",
            value: item.store_name || "—",
        },
        {
            icon: Package,
            label: "Order",
            value: `#${item.order_number || "—"}`,
        },
        {
            icon: FileText,
            label: "Support Ticket",
            value: item.ticket_number || "Not linked",
        },
        {
            icon: CalendarDays,
            label: "Created",
            value: formatDate(item.created_at),
        },
    ];
});
</script>

<template>
    <div
        class="min-h-screen space-y-6 p-4 md:p-6"
        :class="isDark ? 'text-white' : 'text-slate-900'"
    >
        <!-- Header -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <p
                    class="mb-2 flex items-center gap-2 text-xs font-semibold text-indigo-500"
                >
                    <RotateCcw :size="15" />
                    AFTER-SALES OPERATIONS
                </p>

                <h1 class="text-2xl font-bold tracking-tight md:text-3xl">
                    Returns &amp; Refunds
                </h1>

                <p class="mt-1 text-sm" :class="muted">
                    Review returns, manage approvals and track refund progress.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    :class="secondaryButton"
                    @click="refreshAll"
                >
                    <RefreshCw :size="16" />
                    Refresh
                </button>

                <button
                    type="button"
                    :class="primaryButton"
                    @click="openCreate"
                >
                    <Plus :size="17" />
                    New Return
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="card in cards"
                :key="card.label"
                class="rounded-2xl border p-5"
                :class="panel"
            >
                <div class="flex items-center justify-between">
                    <div class="rounded-xl p-3" :class="card.bg">
                        <component
                            :is="card.icon"
                            :size="21"
                            :class="card.color"
                        />
                    </div>

                    <span class="text-xs" :class="muted"> Overview </span>
                </div>

                <p class="mt-5 text-sm" :class="muted">
                    {{ card.label }}
                </p>

                <h2 class="mt-1 text-3xl font-bold">
                    {{ card.value }}
                </h2>
            </div>
        </div>

        <!-- Returns Table -->
        <div class="overflow-hidden rounded-2xl border" :class="panel">
            <div class="border-b border-slate-500/10 p-5">
                <h2 class="mb-4 flex items-center gap-2 font-semibold">
                    <Filter :size="17" class="text-indigo-500" />
                    All Return Requests
                </h2>

                <div
                    class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4"
                >
                    <div class="relative">
                        <Search
                            :size="17"
                            class="absolute left-3 top-1/2 -translate-y-1/2"
                            :class="muted"
                        />

                        <input
                            v-model="search"
                            :class="[inputClass, 'pl-10']"
                            placeholder="Search returns..."
                        />
                    </div>

                    <select v-model="statusFilter" :class="inputClass">
                        <option value="all">All Statuses</option>

                        <option
                            v-for="status in returnStatuses"
                            :key="status"
                            :value="status"
                        >
                            {{ formatLabel(status) }}
                        </option>
                    </select>

                    <select v-model="typeFilter" :class="inputClass">
                        <option value="all">All Request Types</option>

                        <option
                            v-for="type in requestTypes"
                            :key="type"
                            :value="type"
                        >
                            {{ formatLabel(type) }}
                        </option>
                    </select>

                    <select v-model="storeFilter" :class="inputClass">
                        <option value="all">All Stores</option>

                        <option
                            v-for="store in stores"
                            :key="store.id"
                            :value="String(store.id)"
                        >
                            {{
                                store.store_name ||
                                store.name ||
                                `Store #${store.id}`
                            }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Success Notice -->
            <div
                v-if="notice"
                class="mx-5 mt-5 flex items-center justify-between rounded-xl bg-emerald-500/10 p-3 text-sm text-emerald-500"
            >
                <span>{{ notice }}</span>

                <button type="button" @click="notice = ''">
                    <X :size="15" />
                </button>
            </div>

            <!-- Error -->
            <div
                v-if="error && !showCreate && !showDetails"
                class="mx-5 mt-5 rounded-xl bg-red-500/10 p-3 text-sm text-red-500"
            >
                {{ error }}
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-sm">
                    <thead
                        :class="
                            isDark
                                ? 'bg-white/[0.03] text-slate-400'
                                : 'bg-slate-50 text-slate-500'
                        "
                    >
                        <tr>
                            <th
                                v-for="heading in [
                                    'Return',
                                    'Customer',
                                    'Store / Order',
                                    'Type',
                                    'Amount',
                                    'Status',
                                    'Refund',
                                    'Action',
                                ]"
                                :key="heading"
                                class="px-5 py-4 text-xs font-semibold uppercase tracking-wider"
                            >
                                {{ heading }}
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-if="loading">
                            <td colspan="8" class="py-16 text-center">
                                <Loader2
                                    :size="28"
                                    class="mx-auto animate-spin text-indigo-500"
                                />

                                <p class="mt-3 text-sm" :class="muted">
                                    Loading returns...
                                </p>
                            </td>
                        </tr>

                        <tr v-else-if="visibleReturns.length === 0">
                            <td colspan="8" class="py-16 text-center">
                                <RotateCcw
                                    :size="35"
                                    class="mx-auto"
                                    :class="muted"
                                />

                                <p class="mt-3 font-semibold">
                                    No return requests found
                                </p>

                                <p class="mt-1 text-sm" :class="muted">
                                    Create a return or adjust your filters.
                                </p>
                            </td>
                        </tr>

                        <tr
                            v-for="item in !loading ? visibleReturns : []"
                            v-else
                            :key="item.id"
                            class="border-t"
                            :class="
                                isDark
                                    ? 'border-white/5 hover:bg-white/[0.03]'
                                    : 'border-slate-100 hover:bg-slate-50'
                            "
                        >
                            <td class="px-5 py-4">
                                <p class="font-semibold">
                                    {{ item.return_number }}
                                </p>

                                <p class="mt-1 text-xs" :class="muted">
                                    {{ formatDate(item.created_at) }}
                                </p>

                                <span
                                    v-if="item.is_demo"
                                    class="mt-2 inline-flex rounded-md bg-violet-500/10 px-2 py-0.5 text-[10px] font-semibold text-violet-500"
                                >
                                    DEMO
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <p class="font-medium">
                                    {{ item.customer_name }}
                                </p>

                                <p class="mt-1 text-xs" :class="muted">
                                    {{ item.customer_email || "—" }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p>{{ item.store_name }}</p>

                                <p class="mt-1 text-xs" :class="muted">
                                    Order #{{ item.order_number }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                {{ formatLabel(item.request_type) }}
                            </td>

                            <td class="px-5 py-4 font-semibold">
                                {{
                                    formatMoney(
                                        item.refund_amount,
                                        item.currency,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <span :class="statusBadge(item.status)">
                                    {{ formatLabel(item.status) }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <span :class="refundBadge(item.refund_status)">
                                    {{ formatLabel(item.refund_status) }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-500/10 px-3 py-2 text-xs font-semibold text-indigo-500 hover:bg-indigo-500/20"
                                    @click="openDetails(item.id)"
                                >
                                    <Eye :size="15" />
                                    View
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                class="flex flex-col items-center justify-between gap-3 border-t border-slate-500/10 px-5 py-4 sm:flex-row"
            >
                <p class="text-xs" :class="muted">
                    Showing
                    {{ filtered.length ? (currentPage - 1) * perPage + 1 : 0 }}
                    -
                    {{ Math.min(currentPage * perPage, filtered.length) }}
                    of {{ filtered.length }}
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="currentPage <= 1"
                        :class="secondaryButton"
                        @click="page = Math.max(1, page - 1)"
                    >
                        <ChevronLeft :size="16" />
                    </button>

                    <span class="px-2 text-xs" :class="muted">
                        Page {{ currentPage }} of {{ totalPages }}
                    </span>

                    <button
                        type="button"
                        :disabled="currentPage >= totalPages"
                        :class="secondaryButton"
                        @click="page = Math.min(totalPages, page + 1)"
                    >
                        <ChevronRight :size="16" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Create Return Modal -->
        <div
            v-if="showCreate"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
        >
            <div
                class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl border p-5 shadow-2xl md:p-7"
                :class="panel"
            >
                <div class="mb-6 flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-bold">Create Return Request</h2>

                        <p class="mt-1 text-sm" :class="muted">
                            Link a customer return to an existing order.
                        </p>
                    </div>

                    <button
                        type="button"
                        :class="secondaryButton"
                        @click="closeCreate"
                    >
                        <X :size="17" />
                    </button>
                </div>

                <div
                    v-if="error"
                    class="mb-4 rounded-xl bg-red-500/10 p-3 text-sm text-red-500"
                >
                    {{ error }}
                </div>

                <form class="space-y-4" @submit.prevent="createReturn">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <!-- Store -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Store *
                            </label>

                            <select
                                v-model="form.store_id"
                                required
                                :class="inputClass"
                                @change="changeStore"
                            >
                                <option value="">Select Store</option>

                                <option
                                    v-for="store in stores"
                                    :key="store.id"
                                    :value="String(store.id)"
                                >
                                    {{ store.store_name || store.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Order -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Order *
                            </label>

                            <select
                                v-model="form.order_id"
                                required
                                :disabled="!form.store_id"
                                :class="inputClass"
                                @change="changeOrder"
                            >
                                <option value="">Select Order</option>

                                <option
                                    v-for="order in availableOrders"
                                    :key="order.id"
                                    :value="String(order.id)"
                                >
                                    #{{ order.order_number }} —
                                    {{ order.customer_name }}
                                </option>
                            </select>
                        </div>

                        <!-- Support Ticket -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Support Ticket (Optional)
                            </label>

                            <select
                                v-model="form.ticket_id"
                                :disabled="!form.order_id"
                                :class="inputClass"
                            >
                                <option value="">No linked ticket</option>

                                <option
                                    v-for="ticket in availableTickets"
                                    :key="ticket.id"
                                    :value="String(ticket.id)"
                                >
                                    {{ ticket.ticket_number }} —
                                    {{ ticket.subject }}
                                </option>
                            </select>
                        </div>

                        <!-- Request Type -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Request Type
                            </label>

                            <select
                                v-model="form.request_type"
                                :class="inputClass"
                                @change="changeRequestType"
                            >
                                <option
                                    v-for="type in requestTypes"
                                    :key="type"
                                    :value="type"
                                >
                                    {{ formatLabel(type) }}
                                </option>
                            </select>
                        </div>

                        <!-- Customer Name -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Customer Name *
                            </label>

                            <input
                                v-model="form.customer_name"
                                required
                                maxlength="150"
                                :class="inputClass"
                            />
                        </div>

                        <!-- Customer Email -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Customer Email
                            </label>

                            <input
                                v-model="form.customer_email"
                                type="email"
                                maxlength="255"
                                :class="inputClass"
                            />
                        </div>

                        <!-- Reason -->
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-sm font-medium">
                                Reason *
                            </label>

                            <input
                                v-model="form.reason"
                                required
                                maxlength="100"
                                :class="inputClass"
                                placeholder="Wrong product received"
                            />
                        </div>

                        <!-- Description -->
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-sm font-medium">
                                Description
                            </label>

                            <textarea
                                v-model="form.description"
                                rows="3"
                                maxlength="10000"
                                :class="inputClass"
                                placeholder="Describe the return request..."
                            ></textarea>
                        </div>

                        <!-- Refund Amount -->
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-sm font-medium">
                                Refund Amount
                            </label>

                            <input
                                v-model="form.refund_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                :disabled="form.request_type === 'exchange'"
                                :max="selectedOrder?.total_amount || undefined"
                                :class="inputClass"
                            />

                            <p
                                v-if="selectedOrder"
                                class="mt-2 text-xs"
                                :class="muted"
                            >
                                Order total:
                                {{
                                    formatMoney(
                                        selectedOrder.total_amount,
                                        selectedOrder.currency,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-3">
                        <button
                            type="button"
                            :class="secondaryButton"
                            @click="closeCreate"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="saving"
                            :class="primaryButton"
                        >
                            <Loader2
                                v-if="saving"
                                :size="16"
                                class="animate-spin"
                            />
                            <Plus v-else :size="16" />
                            Create Return
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Return Details Modal -->
        <div
            v-if="showDetails"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
        >
            <div
                class="max-h-[94vh] w-full max-w-3xl overflow-y-auto rounded-2xl border p-5 shadow-2xl md:p-7"
                :class="panel"
            >
                <div class="mb-6 flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-bold">
                            Return Request Details
                        </h2>

                        <p class="mt-1 text-xs" :class="muted">
                            {{ selectedReturn?.return_number || "Loading..." }}
                        </p>
                    </div>

                    <button
                        type="button"
                        :class="secondaryButton"
                        @click="closeDetails"
                    >
                        <X :size="17" />
                    </button>
                </div>

                <div
                    v-if="detailsLoading"
                    class="flex min-h-[300px] items-center justify-center"
                >
                    <Loader2 :size="30" class="animate-spin text-indigo-500" />
                </div>

                <div v-else-if="selectedReturn" class="space-y-6">
                    <div
                        v-if="error"
                        class="rounded-xl bg-red-500/10 p-3 text-sm text-red-500"
                    >
                        {{ error }}
                    </div>

                    <div
                        v-if="selectedReturn.is_demo"
                        class="rounded-xl bg-violet-500/10 p-3 text-sm text-violet-500"
                    >
                        Demo return — no real refund will be executed.
                    </div>

                    <!-- Status Badges -->
                    <div class="flex flex-wrap gap-2">
                        <span :class="statusBadge(selectedReturn.status)">
                            {{ formatLabel(selectedReturn.status) }}
                        </span>

                        <span
                            :class="refundBadge(selectedReturn.refund_status)"
                        >
                            {{ formatLabel(selectedReturn.refund_status) }}
                        </span>
                    </div>

                    <!-- Information Cards -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="item in detailsCards"
                            :key="item.label"
                            class="rounded-xl border p-4"
                            :class="panel"
                        >
                            <div class="flex items-center gap-2">
                                <component
                                    :is="item.icon"
                                    :size="16"
                                    class="text-indigo-500"
                                />

                                <span class="text-xs" :class="muted">
                                    {{ item.label }}
                                </span>
                            </div>

                            <p class="mt-2 break-words text-sm font-semibold">
                                {{ item.value }}
                            </p>
                        </div>
                    </div>

                    <!-- Reason -->
                    <div>
                        <p class="mb-2 text-xs" :class="muted">Reason</p>

                        <h3 class="font-semibold">
                            {{ selectedReturn.reason }}
                        </h3>

                        <p class="mt-2 text-sm" :class="muted">
                            {{ selectedReturn.description || "No description" }}
                        </p>
                    </div>

                    <!-- Refund Amount -->
                    <div class="rounded-xl border p-4" :class="panel">
                        <p class="text-xs" :class="muted">
                            Requested Refund Amount
                        </p>

                        <p class="mt-2 text-2xl font-bold">
                            {{
                                formatMoney(
                                    selectedReturn.refund_amount,
                                    selectedReturn.currency,
                                )
                            }}
                        </p>

                        <p class="mt-1 text-xs" :class="muted">
                            Request Type:
                            {{ formatLabel(selectedReturn.request_type) }}
                        </p>
                    </div>

                    <!-- Return Workflow -->
                    <div class="border-t border-slate-500/10 pt-5">
                        <h3 class="mb-3 font-semibold">Return Workflow</h3>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="nextStatus in returnTransitions[
                                    selectedReturn.status
                                ] || []"
                                :key="nextStatus"
                                type="button"
                                :disabled="updating"
                                :class="
                                    ['rejected', 'cancelled'].includes(
                                        nextStatus,
                                    )
                                        ? 'rounded-xl bg-red-500/10 px-4 py-2.5 text-sm font-semibold text-red-500 hover:bg-red-500/20 disabled:opacity-50'
                                        : primaryButton
                                "
                                @click="updateStatus(nextStatus)"
                            >
                                <CheckCircle2
                                    v-if="nextStatus === 'approved'"
                                    :size="16"
                                />

                                <XCircle
                                    v-if="nextStatus === 'rejected'"
                                    :size="16"
                                />

                                <Loader2
                                    v-if="updating"
                                    :size="15"
                                    class="animate-spin"
                                />

                                <span v-else>
                                    {{ formatLabel(nextStatus) }}
                                </span>
                            </button>

                            <p
                                v-if="
                                    !(
                                        returnTransitions[
                                            selectedReturn.status
                                        ] || []
                                    ).length
                                "
                                class="text-sm"
                                :class="muted"
                            >
                                No further return status transitions available.
                            </p>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Store Owner Notes
                        </label>

                        <textarea
                            v-model="notes"
                            rows="3"
                            maxlength="10000"
                            :class="inputClass"
                            placeholder="Add internal notes..."
                        ></textarea>

                        <button
                            type="button"
                            :disabled="updating"
                            :class="[secondaryButton, 'mt-3']"
                            @click="saveNotes"
                        >
                            <Loader2
                                v-if="updating"
                                :size="15"
                                class="animate-spin"
                            />
                            Save Notes
                        </button>
                    </div>

                    <!-- Refund Tracking -->
                    <div class="border-t border-slate-500/10 pt-5">
                        <h3 class="mb-2 flex items-center gap-2 font-semibold">
                            <Wallet :size="18" class="text-indigo-500" />
                            Refund Tracking
                        </h3>

                        <p class="mb-4 text-xs" :class="muted">
                            Manual status tracking only. This does not transfer
                            money to the customer.
                        </p>

                        <div
                            v-if="
                                ['approved', 'received', 'completed'].includes(
                                    selectedReturn.status,
                                ) &&
                                selectedReturn.request_type !== 'exchange' &&
                                Number(selectedReturn.refund_amount) > 0
                            "
                        >
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="nextStatus in refundTransitions[
                                        selectedReturn.refund_status
                                    ] || []"
                                    :key="nextStatus"
                                    type="button"
                                    :disabled="updating"
                                    :class="secondaryButton"
                                    @click="updateRefund(nextStatus)"
                                >
                                    <Loader2
                                        v-if="updating"
                                        :size="15"
                                        class="animate-spin"
                                    />

                                    <Wallet v-else :size="15" />

                                    Mark {{ formatLabel(nextStatus) }}
                                </button>

                                <p
                                    v-if="
                                        selectedReturn.refund_status ===
                                        'completed'
                                    "
                                    class="text-sm text-emerald-500"
                                >
                                    Refund marked completed in tracking.
                                </p>
                            </div>
                        </div>

                        <p v-else class="text-sm" :class="muted">
                            Refund tracking becomes available after approval for
                            eligible requests with a positive refund amount.
                        </p>
                    </div>
                </div>

                <div v-else class="py-12 text-center" :class="muted">
                    Return request not found.
                </div>
            </div>
        </div>
    </div>
</template>
