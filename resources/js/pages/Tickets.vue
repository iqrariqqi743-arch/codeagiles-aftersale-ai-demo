<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import { useTheme } from "@/composables/useTheme";
import {
    Search,
    Plus,
    RefreshCw,
    MessageSquare,
    Clock3,
    CheckCircle2,
    AlertTriangle,
    Eye,
    X,
    Send,
    Loader2,
    Store,
    Ticket,
    ChevronLeft,
    ChevronRight,
    Inbox,
    User,
    Mail,
    ShoppingBag,
    CalendarDays,
    Filter,
    Sparkles,
    Bot,
    ShieldCheck,
    Pencil,
    BrainCircuit,
    Activity,
    ClipboardList,
    Lightbulb,
} from "lucide-vue-next";

const router = useRouter();
const { isDark } = useTheme();

const api = axios.create({ baseURL: import.meta.env.VITE_API_URL || "/api" });

api.interceptors.request.use((config) => {
    const token = localStorage.getItem("accessToken");
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

const statuses = [
    { value: "open", label: "Open" },
    { value: "in_progress", label: "In Progress" },
    { value: "waiting_customer", label: "Waiting Customer" },
    { value: "resolved", label: "Resolved" },
    { value: "closed", label: "Closed" },
];

const priorities = ["low", "medium", "high", "urgent"];
const categories = [
    "general",
    "delivery",
    "damaged_product",
    "wrong_product",
    "return",
    "refund",
    "other",
];

const blankForm = () => ({
    store_id: "",
    order_id: "",
    customer_name: "",
    customer_email: "",
    subject: "",
    description: "",
    category: "general",
    priority: "medium",
});

const tickets = ref([]);
const stores = ref([]);
const orders = ref([]);
const loading = ref(false);
const saving = ref(false);
const detailsLoading = ref(false);
const replyLoading = ref(false);
const approvalLoading = ref(false);
const statusLoading = ref(false);
const aiLoading = ref(false);
const analysisLoading = ref(false);

const error = ref("");
const notice = ref("");
const emailStatus = ref(null);
const search = ref("");
const statusFilter = ref("all");
const priorityFilter = ref("all");
const storeFilter = ref("all");
const page = ref(1);
const perPage = 8;

const showCreate = ref(false);
const showDetails = ref(false);
const form = ref(blankForm());
const selectedTicket = ref(null);
const messages = ref([]);
const reply = ref("");
const approvalId = ref(null);
const approvedMessage = ref("");
const approvalType = ref("general_support");
const aiSuggestion = ref("");
const aiError = ref("");
const analysis = ref(null);
const analysisError = ref("");
let detailRequestId = 0;
let aiRequestId = 0;
let analysisRequestId = 0;
let pollingTimer = null;
let pollingBusy = false;
const POLLING_INTERVAL = 15000;
const lastSyncAt = ref(null);
const newMessageNotice = ref("");

const panel = computed(() =>
    isDark.value ? "bg-[#111827] border-white/10" : "bg-white border-slate-200",
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

const secondaryButton = computed(
    () =>
        `inline-flex items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-medium transition ${
            isDark.value
                ? "border-white/10 bg-white/5 text-slate-200 hover:bg-white/10"
                : "border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
        }`,
);

const primaryButton =
    "inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50";

const formatLabel = (value = "") =>
    String(value || "")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const formatDate = (value) => {
    if (!value) return "—";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "—";
    return date.toLocaleString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
        hour: "numeric",
        minute: "2-digit",
    });
};

const errorMessage = (err, fallback) =>
    err?.response?.data?.message || err?.message || fallback;

function handleError(err, fallback) {
    if (err?.response?.status === 401) {
        localStorage.removeItem("accessToken");
        localStorage.removeItem("user");
        router.push("/login");
        return;
    }
    error.value = errorMessage(err, fallback);
}

function normalizeList(data, key) {
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.[key])) return data[key];
    if (Array.isArray(data?.[key]?.data)) return data[key].data;
    if (Array.isArray(data?.data)) return data.data;
    if (Array.isArray(data?.data?.data)) return data.data.data;
    return [];
}

async function fetchTickets() {
    loading.value = true;
    try {
        const { data } = await api.get("/tickets");
        tickets.value = normalizeList(data, "tickets");
    } catch (err) {
        handleError(err, "Unable to load tickets");
    } finally {
        loading.value = false;
    }
}

async function fetchStores() {
    try {
        const { data } = await api.get("/stores");
        stores.value = normalizeList(data, "stores");
    } catch (err) {
        handleError(err, "Unable to load stores");
    }
}

async function fetchOrders() {
    try {
        const { data } = await api.get("/orders");
        orders.value = normalizeList(data, "orders");
    } catch (err) {
        console.error("Orders fetch failed:", err);
        orders.value = [];
    }
}

async function refreshAll() {
    await Promise.all([fetchTickets(), fetchStores(), fetchOrders()]);
}

onMounted(() => {
    refreshAll();
    pollingTimer = window.setInterval(pollTicketConversation, POLLING_INTERVAL);
});

onUnmounted(() => {
    if (pollingTimer !== null) window.clearInterval(pollingTimer);
    pollingTimer = null;
    detailRequestId++;
});

async function pollTicketConversation() {
    if (
        pollingBusy ||
        document.hidden ||
        !showDetails.value ||
        !selectedTicket.value ||
        detailsLoading.value ||
        replyLoading.value ||
        approvalLoading.value
    )
        return;

    pollingBusy = true;
    const ticketId = selectedTicket.value.id;
    const requestId = detailRequestId;

    try {
        const { data } = await api.get(`/tickets/${ticketId}`);
        if (
            requestId !== detailRequestId ||
            !showDetails.value ||
            selectedTicket.value?.id !== ticketId
        )
            return;

        const incoming = Array.isArray(data?.messages) ? data.messages : [];
        const previousIds = new Set(
            messages.value.map((message) => String(message.id)),
        );
        const newlyReceived = incoming.filter(
            (message) =>
                message.sender_type === "customer" &&
                !previousIds.has(String(message.id)),
        );

        messages.value = incoming;
        if (data?.ticket)
            selectedTicket.value = { ...selectedTicket.value, ...data.ticket };
        lastSyncAt.value = new Date();

        if (newlyReceived.length) {
            resetApproval();
            resetAI();
            newMessageNotice.value = `${newlyReceived.length} new customer message(s) received. Review before replying.`;
            // Preserve typed reply, but require fresh approval for the latest conversation.
            fetchTickets();
        }
    } catch (err) {
        // Background polling errors should not interrupt an in-progress draft.
        console.error("Conversation refresh failed:", err);
    } finally {
        pollingBusy = false;
    }
}

const stats = computed(() => ({
    total: tickets.value.length,
    open: tickets.value.filter((t) => t.status === "open").length,
    inProgress: tickets.value.filter((t) => t.status === "in_progress").length,
    resolved: tickets.value.filter((t) =>
        ["resolved", "closed"].includes(t.status),
    ).length,
}));

const statCards = computed(() => [
    {
        label: "Total Tickets",
        value: stats.value.total,
        icon: Ticket,
        color: "text-indigo-500",
        bg: "bg-indigo-500/10",
    },
    {
        label: "Open Tickets",
        value: stats.value.open,
        icon: MessageSquare,
        color: "text-blue-500",
        bg: "bg-blue-500/10",
    },
    {
        label: "In Progress",
        value: stats.value.inProgress,
        icon: Clock3,
        color: "text-amber-500",
        bg: "bg-amber-500/10",
    },
    {
        label: "Resolved / Closed",
        value: stats.value.resolved,
        icon: CheckCircle2,
        color: "text-emerald-500",
        bg: "bg-emerald-500/10",
    },
]);

const filteredTickets = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    return tickets.value.filter((ticket) => {
        const searchable = [
            ticket.ticket_number,
            ticket.customer_name,
            ticket.customer_email,
            ticket.subject,
            ticket.order_number,
            ticket.store_name,
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return (
            (!keyword || searchable.includes(keyword)) &&
            (statusFilter.value === "all" ||
                ticket.status === statusFilter.value) &&
            (priorityFilter.value === "all" ||
                ticket.priority === priorityFilter.value) &&
            (storeFilter.value === "all" ||
                String(ticket.store_id) === storeFilter.value)
        );
    });
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredTickets.value.length / perPage)),
);

const currentPage = computed(() => Math.min(page.value, totalPages.value));

const paginatedTickets = computed(() =>
    filteredTickets.value.slice(
        (currentPage.value - 1) * perPage,
        currentPage.value * perPage,
    ),
);

const availableOrders = computed(() =>
    orders.value.filter(
        (order) => String(order.store_id) === String(form.value.store_id),
    ),
);

watch([search, statusFilter, priorityFilter, storeFilter], () => {
    page.value = 1;
});

watch(
    () => form.value.store_id,
    () => {
        form.value.order_id = "";
    },
);

function statusBadge(status) {
    return (
        {
            open: "bg-blue-500/10 text-blue-500",
            in_progress: "bg-amber-500/10 text-amber-500",
            waiting_customer: "bg-purple-500/10 text-purple-500",
            resolved: "bg-emerald-500/10 text-emerald-500",
            closed: "bg-slate-500/10 text-slate-400",
        }[status] || "bg-slate-500/10 text-slate-400"
    );
}

function priorityBadge(priority) {
    return (
        {
            low: "bg-slate-500/10 text-slate-400",
            medium: "bg-blue-500/10 text-blue-500",
            high: "bg-orange-500/10 text-orange-500",
            urgent: "bg-red-500/10 text-red-500",
        }[priority] || "bg-slate-500/10 text-slate-400"
    );
}

function openCreate() {
    form.value = blankForm();
    error.value = "";
    notice.value = "";
    showCreate.value = true;
}

async function createTicket() {
    if (
        !form.value.store_id ||
        !form.value.customer_name.trim() ||
        !form.value.subject.trim() ||
        !form.value.description.trim()
    ) {
        error.value = "Please complete all required fields";
        return;
    }

    saving.value = true;
    error.value = "";

    try {
        const payload = {
            ...form.value,
            store_id: Number(form.value.store_id),
            customer_name: form.value.customer_name.trim(),
            customer_email: form.value.customer_email.trim(),
            subject: form.value.subject.trim(),
            description: form.value.description.trim(),
        };

        if (payload.order_id) {
            payload.order_id = Number(payload.order_id);
        } else {
            delete payload.order_id;
        }

        const { data } = await api.post("/tickets", payload);

        if (!data?.success) {
            throw new Error(data?.message || "Ticket creation failed");
        }

        showCreate.value = false;
        form.value = blankForm();
        notice.value = "Ticket created successfully";
        await fetchTickets();
    } catch (err) {
        handleError(err, "Failed to create ticket");
    } finally {
        saving.value = false;
    }
}

function resetApproval() {
    approvalId.value = null;
    approvedMessage.value = "";
}

function resetAI() {
    aiRequestId++;
    analysisRequestId++;
    aiLoading.value = false;
    analysisLoading.value = false;
    aiSuggestion.value = "";
    aiError.value = "";
    analysis.value = null;
    analysisError.value = "";
}

function closeDetails() {
    detailRequestId++;
    showDetails.value = false;
    selectedTicket.value = null;
    messages.value = [];
    reply.value = "";
    resetApproval();
    resetAI();
    emailStatus.value = null;
    newMessageNotice.value = "";
}

async function openTicketDetails(id) {
    const requestId = ++detailRequestId;

    showDetails.value = true;
    detailsLoading.value = true;
    selectedTicket.value = null;
    messages.value = [];
    reply.value = "";
    resetApproval();
    approvalType.value = "general_support";
    resetAI();
    error.value = "";
    notice.value = "";
    emailStatus.value = null;
    newMessageNotice.value = "";
    lastSyncAt.value = null;

    try {
        const { data } = await api.get(`/tickets/${id}`);

        if (requestId !== detailRequestId) return;

        selectedTicket.value = data?.ticket || null;
        messages.value = Array.isArray(data?.messages) ? data.messages : [];
    } catch (err) {
        if (requestId !== detailRequestId) return;
        handleError(err, "Unable to load ticket details");
        showDetails.value = false;
    } finally {
        if (requestId === detailRequestId) {
            detailsLoading.value = false;
        }
    }
}

async function updateStatus(status) {
    if (!selectedTicket.value || status === selectedTicket.value.status) return;

    statusLoading.value = true;
    error.value = "";

    try {
        const { data } = await api.patch(
            `/tickets/${selectedTicket.value.id}/status`,
            { status },
        );

        if (!data?.success) {
            throw new Error(data?.message || "Status update failed");
        }

        selectedTicket.value = {
            ...selectedTicket.value,
            ...data.ticket,
            status,
        };

        notice.value = "Ticket status updated";
        await fetchTickets();
    } catch (err) {
        handleError(err, "Failed to update status");
    } finally {
        statusLoading.value = false;
    }
}

async function analyzeTicket() {
    if (!selectedTicket.value || analysisLoading.value) return;
    const ticketId = selectedTicket.value.id;
    const requestId = ++analysisRequestId;
    analysisLoading.value = true;
    analysisError.value = "";
    analysis.value = null;

    try {
        const { data } = await api.post(`/ai/tickets/${ticketId}/analyze`);
        if (
            requestId !== analysisRequestId ||
            selectedTicket.value?.id !== ticketId
        )
            return;
        if (!data?.success || !data?.analysis)
            throw new Error(data?.message || "AI analysis failed");
        const result = data.analysis;
        analysis.value = {
            sentiment: result.sentiment,
            urgency: result.priority,
            summary: result.summary,
            suggested_category: result.category,
            suggested_priority: result.priority,
            recommended_action: result.suggested_action,
            confidence: result.confidence,
        };
    } catch (err) {
        if (requestId !== analysisRequestId) return;
        analysisError.value = errorMessage(err, "AI analysis failed");
    } finally {
        if (requestId === analysisRequestId) analysisLoading.value = false;
    }
}

async function generateAIReply() {
    if (!selectedTicket.value || aiLoading.value) return;

    const id = selectedTicket.value.id;
    const requestId = ++aiRequestId;
    aiLoading.value = true;
    aiError.value = "";
    aiSuggestion.value = "";

    try {
        const { data } = await api.post(`/tickets/${id}/generate-ai-reply`);

        if (requestId !== aiRequestId) return;

        const suggestion = String(data?.suggested_reply || "").trim();
        if (data?.policy_reviewed !== true) {
            throw new Error("AI policy review was not completed.");
        }

        if (!data?.success || !suggestion) {
            throw new Error(data?.message || "No AI suggestion returned");
        }

        aiSuggestion.value = suggestion;
    } catch (err) {
        if (requestId !== aiRequestId) return;

        aiError.value =
            err?.response?.status === 404
                ? "Gemini AI backend is not connected yet."
                : errorMessage(err, "AI reply generation failed");
    } finally {
        if (requestId === aiRequestId) {
            aiLoading.value = false;
        }
    }
}

function useAIDraft() {
    if (!aiSuggestion.value.trim()) return;
    reply.value = aiSuggestion.value;
    aiSuggestion.value = "";
    resetApproval();
    notice.value = "AI draft added. Review and approve before sending.";
}

function onReplyChange() {
    resetApproval();
    error.value = "";
}

const isReplyApproved = computed(
    () =>
        approvalId.value !== null &&
        reply.value.trim() === approvedMessage.value,
);

async function approveReply() {
    const message = reply.value.trim();

    if (!selectedTicket.value || !message) {
        error.value = "Write a reply before approving.";
        return;
    }

    if (approvalLoading.value || replyLoading.value) return;

    approvalLoading.value = true;
    error.value = "";

    try {
        const { data } = await api.post(
            `/tickets/${selectedTicket.value.id}/approve-reply`,
            {
                message,
                approval_type: approvalType.value,
            },
        );

        if (!data?.success) {
            throw new Error(data?.message || "Approval failed");
        }

        const approval =
            data.approval || data.reply_approval || data.ticket_reply_approval;

        const id = approval?.id ?? data.approval_id;

        if (!id) {
            throw new Error(
                "Approval saved, but backend did not return an approval ID.",
            );
        }

        approvalId.value = id;
        approvedMessage.value = message;
        notice.value = "Reply approved. You can now send it.";
    } catch (err) {
        resetApproval();
        handleError(err, "Unable to approve reply");
    } finally {
        approvalLoading.value = false;
    }
}

async function sendReply() {
    if (!selectedTicket.value || replyLoading.value) return;

    if (!isReplyApproved.value) {
        error.value = "Please approve the exact reply before sending.";
        return;
    }

    replyLoading.value = true;
    error.value = "";
    emailStatus.value = null;

    try {
        const { data } = await api.post(
            `/tickets/${selectedTicket.value.id}/messages`,
            {
                message: reply.value.trim(),
                approval_id: approvalId.value,
            },
        );

        if (!data?.success) {
            throw new Error(data?.message || "Reply could not be saved");
        }

        const savedMessage = data.ticket_message || data.message_data;

        if (savedMessage?.id) {
            messages.value.push(savedMessage);
        } else {
            const refreshed = await api.get(
                `/tickets/${selectedTicket.value.id}`,
            );
            messages.value = refreshed.data?.messages || [];
        }

        const email = data.email_notification || {};
        const status =
            email.status || (email.sent === true ? "sent" : "unknown");

        const statusMessages = {
            sent: "Reply saved. Email accepted by the email provider.",
            failed: "Reply saved, but email delivery failed. Do not resend the reply just to retry email.",
            skipped: "Reply saved. Email notification was not sent.",
            unknown: "Reply saved, but email delivery status is unknown.",
        };

        emailStatus.value = {
            status,
            message: statusMessages[status] || statusMessages.unknown,
        };

        reply.value = "";
        resetApproval();
        aiSuggestion.value = "";
        await fetchTickets();
    } catch (err) {
        handleError(
            err,
            "Unable to confirm the result. Check conversation before retrying.",
        );
    } finally {
        replyLoading.value = false;
    }
}
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
                <div
                    class="mb-2 flex items-center gap-2 text-xs font-medium text-indigo-500"
                >
                    <MessageSquare :size="15" />
                    AFTER-SALES SUPPORT
                </div>

                <h1 class="text-2xl font-bold tracking-tight md:text-3xl">
                    Support Tickets
                </h1>

                <p class="mt-1 text-sm" :class="muted">
                    Manage customer complaints, conversations and resolutions.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button :class="secondaryButton" @click="refreshAll">
                    <RefreshCw :size="16" />
                    Refresh
                </button>

                <button :class="primaryButton" @click="openCreate">
                    <Plus :size="17" />
                    New Ticket
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="item in statCards"
                :key="item.label"
                class="rounded-2xl border p-5"
                :class="panel"
            >
                <div class="flex items-center justify-between">
                    <div class="rounded-xl p-3" :class="item.bg">
                        <component
                            :is="item.icon"
                            :size="21"
                            :class="item.color"
                        />
                    </div>

                    <span class="text-xs" :class="muted"> Live overview </span>
                </div>

                <p class="mt-5 text-sm" :class="muted">
                    {{ item.label }}
                </p>

                <h2 class="mt-1 text-3xl font-bold">
                    {{ item.value }}
                </h2>
            </div>
        </div>

        <!-- Tickets Table -->
        <div class="overflow-hidden rounded-2xl border" :class="panel">
            <div class="border-b border-slate-500/10 p-5">
                <div class="mb-4 flex items-center gap-2">
                    <Filter :size="17" class="text-indigo-500" />
                    <h2 class="font-semibold">All Support Tickets</h2>
                </div>

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
                            placeholder="Search tickets..."
                        />
                    </div>

                    <select v-model="statusFilter" :class="inputClass">
                        <option value="all">All Statuses</option>
                        <option
                            v-for="item in statuses"
                            :key="item.value"
                            :value="item.value"
                        >
                            {{ item.label }}
                        </option>
                    </select>

                    <select v-model="priorityFilter" :class="inputClass">
                        <option value="all">All Priorities</option>
                        <option
                            v-for="item in priorities"
                            :key="item"
                            :value="item"
                        >
                            {{ formatLabel(item) }}
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

            <div
                v-if="error && !showCreate && !showDetails"
                class="mx-5 mt-5 flex items-center gap-2 rounded-xl bg-red-500/10 p-3 text-sm text-red-500"
            >
                <AlertTriangle :size="17" />
                {{ error }}
            </div>

            <div
                v-if="notice && !showDetails"
                class="mx-5 mt-5 flex items-center gap-2 rounded-xl bg-emerald-500/10 p-3 text-sm text-emerald-500"
            >
                <CheckCircle2 :size="17" />
                {{ notice }}
                <button class="ml-auto" @click="notice = ''">
                    <X :size="15" />
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left text-sm">
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
                                    'Ticket',
                                    'Customer',
                                    'Store / Order',
                                    'Priority',
                                    'Status',
                                    'Created',
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
                            <td colspan="7" class="py-16 text-center">
                                <Loader2
                                    :size="28"
                                    class="mx-auto animate-spin text-indigo-500"
                                />
                                <p class="mt-3 text-sm" :class="muted">
                                    Loading tickets...
                                </p>
                            </td>
                        </tr>

                        <tr v-else-if="!paginatedTickets.length">
                            <td colspan="7" class="py-16 text-center">
                                <Inbox
                                    :size="35"
                                    class="mx-auto"
                                    :class="muted"
                                />
                                <p class="mt-3 font-semibold">
                                    No tickets found
                                </p>
                                <p class="mt-1 text-sm" :class="muted">
                                    Create a ticket or adjust your filters.
                                </p>
                            </td>
                        </tr>

                        <tr
                            v-for="ticket in paginatedTickets"
                            v-else
                            :key="ticket.id"
                            class="border-t transition"
                            :class="
                                isDark
                                    ? 'border-white/5 hover:bg-white/[0.03]'
                                    : 'border-slate-100 hover:bg-slate-50'
                            "
                        >
                            <td class="px-5 py-4">
                                <p class="max-w-[200px] truncate font-semibold">
                                    {{ ticket.subject }}
                                </p>
                                <p class="mt-1 text-xs" :class="muted">
                                    {{ ticket.ticket_number }}
                                </p>
                                <span
                                    v-if="ticket.is_demo"
                                    class="mt-2 inline-flex rounded-md bg-violet-500/10 px-2 py-0.5 text-[10px] font-semibold text-violet-500"
                                >
                                    DEMO
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <p class="font-medium">
                                    {{ ticket.customer_name }}
                                </p>
                                <p class="mt-1 text-xs" :class="muted">
                                    {{ ticket.customer_email || "No email" }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p>
                                    {{
                                        ticket.store_name ||
                                        `Store #${ticket.store_id}`
                                    }}
                                </p>
                                <p class="mt-1 text-xs" :class="muted">
                                    {{
                                        ticket.order_number
                                            ? `Order #${ticket.order_number}`
                                            : "No linked order"
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="priorityBadge(ticket.priority)"
                                >
                                    {{ formatLabel(ticket.priority) }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="statusBadge(ticket.status)"
                                >
                                    {{ formatLabel(ticket.status) }}
                                </span>
                            </td>

                            <td class="px-5 py-4" :class="muted">
                                {{ formatDate(ticket.created_at) }}
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex gap-2">
                                    <button
                                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-500/10 px-3 py-2 text-xs font-semibold text-indigo-500 hover:bg-indigo-500/20"
                                        @click="openTicketDetails(ticket.id)"
                                    >
                                        <Eye :size="15" />
                                        View
                                    </button>

                                    <button
                                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-500/10 px-3 py-2 text-xs font-semibold text-indigo-500 hover:bg-indigo-500/20"
                                        @click="
                                            router.push(
                                                `/dashboard/tickets/${ticket.id}`,
                                            )
                                        "
                                    >
                                        <Eye :size="15" />
                                        View Details
                                    </button>
                                </div>
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
                    {{
                        filteredTickets.length
                            ? (currentPage - 1) * perPage + 1
                            : 0
                    }}
                    -
                    {{
                        Math.min(currentPage * perPage, filteredTickets.length)
                    }}
                    of {{ filteredTickets.length }} tickets
                </p>

                <div class="flex items-center gap-2">
                    <button
                        :class="secondaryButton"
                        :disabled="currentPage <= 1"
                        @click="page = Math.max(1, page - 1)"
                    >
                        <ChevronLeft :size="16" />
                    </button>

                    <span class="px-2 text-xs" :class="muted">
                        Page {{ currentPage }} of {{ totalPages }}
                    </span>

                    <button
                        :class="secondaryButton"
                        :disabled="currentPage >= totalPages"
                        @click="page = Math.min(totalPages, page + 1)"
                    >
                        <ChevronRight :size="16" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Create Ticket Modal -->
        <div
            v-if="showCreate"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
        >
            <div
                class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl border p-5 shadow-2xl md:p-7"
                :class="panel"
            >
                <div class="mb-6 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold">Create Support Ticket</h2>
                        <p class="mt-1 text-sm" :class="muted">
                            Add a customer complaint for testing or manual
                            support.
                        </p>
                    </div>

                    <button
                        :class="secondaryButton"
                        @click="showCreate = false"
                    >
                        <X :size="17" />
                    </button>
                </div>

                <p
                    v-if="error"
                    class="mb-4 rounded-xl bg-red-500/10 p-3 text-sm text-red-500"
                >
                    {{ error }}
                </p>

                <form class="space-y-4" @submit.prevent="createTicket">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium"
                                >Store *</label
                            >
                            <select
                                v-model="form.store_id"
                                required
                                :class="inputClass"
                            >
                                <option value="">Select Store</option>
                                <option
                                    v-for="store in stores"
                                    :key="store.id"
                                    :value="store.id"
                                >
                                    {{
                                        store.store_name ||
                                        store.name ||
                                        `Store #${store.id}`
                                    }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Order (Optional)
                            </label>
                            <select
                                v-model="form.order_id"
                                :disabled="!form.store_id"
                                :class="inputClass"
                            >
                                <option value="">No linked order</option>
                                <option
                                    v-for="order in availableOrders"
                                    :key="order.id"
                                    :value="order.id"
                                >
                                    #{{ order.order_number }} —
                                    {{ order.customer_name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Customer Name *
                            </label>
                            <input
                                v-model="form.customer_name"
                                required
                                maxlength="150"
                                :class="inputClass"
                                placeholder="Ali Khan"
                            />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Customer Email
                            </label>
                            <input
                                v-model="form.customer_email"
                                type="email"
                                maxlength="255"
                                :class="inputClass"
                                placeholder="ali@example.com"
                            />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium"
                                >Category</label
                            >
                            <select v-model="form.category" :class="inputClass">
                                <option
                                    v-for="category in categories"
                                    :key="category"
                                    :value="category"
                                >
                                    {{ formatLabel(category) }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium"
                                >Priority</label
                            >
                            <select v-model="form.priority" :class="inputClass">
                                <option
                                    v-for="priority in priorities"
                                    :key="priority"
                                    :value="priority"
                                >
                                    {{ formatLabel(priority) }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium"
                            >Subject *</label
                        >
                        <input
                            v-model="form.subject"
                            required
                            maxlength="255"
                            :class="inputClass"
                            placeholder="Wrong product received"
                        />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">
                            Description *
                        </label>
                        <textarea
                            v-model="form.description"
                            required
                            maxlength="10000"
                            rows="4"
                            :class="inputClass"
                            placeholder="Describe the customer's issue..."
                        />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            :class="secondaryButton"
                            @click="showCreate = false"
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
                            {{ saving ? "Creating..." : "Create Ticket" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Ticket Details Modal -->
        <div
            v-if="showDetails"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-2 backdrop-blur-sm sm:p-3 lg:p-4"
        >
            <div
                class="flex h-[96dvh] max-h-[96dvh] w-full max-w-7xl min-h-0 flex-col overflow-hidden rounded-2xl border shadow-2xl"
                :class="panel"
            >
                <div
                    class="flex shrink-0 items-center justify-between border-b border-slate-500/10 p-4 sm:p-5"
                >
                    <div>
                        <h2 class="text-xl font-bold">Ticket Details</h2>
                        <p class="mt-1 text-xs" :class="muted">
                            {{ selectedTicket?.ticket_number || "Loading..." }}
                        </p>
                    </div>

                    <button :class="secondaryButton" @click="closeDetails">
                        <X :size="17" />
                    </button>
                </div>

                <div
                    v-if="detailsLoading"
                    class="flex min-h-0 flex-1 items-center justify-center"
                >
                    <Loader2 :size="30" class="animate-spin text-indigo-500" />
                </div>

                <div
                    v-else-if="selectedTicket"
                    class="grid min-h-0 flex-1 grid-cols-1 grid-rows-[minmax(0,auto)_minmax(0,1fr)] overflow-hidden lg:grid-cols-[320px_minmax(0,1fr)] lg:grid-rows-1"
                >
                    <!-- Ticket Information -->
                    <div
                        class="min-h-0 space-y-5 overflow-y-auto border-b border-slate-500/10 p-5 lg:border-r lg:border-b-0"
                        :class="isDark ? 'bg-white/[0.02]' : 'bg-slate-50/60'"
                    >
                        <div>
                            <p class="mb-2 text-xs" :class="muted">
                                Ticket Subject
                            </p>
                            <h3 class="text-lg font-semibold">
                                {{ selectedTicket.subject }}
                            </h3>
                            <span
                                v-if="selectedTicket.is_demo"
                                class="mt-2 inline-block rounded-lg bg-violet-500/10 px-2 py-1 text-xs font-medium text-violet-500"
                            >
                                Demo Ticket
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span
                                class="rounded-full px-3 py-1 text-xs font-medium"
                                :class="priorityBadge(selectedTicket.priority)"
                            >
                                {{ formatLabel(selectedTicket.priority) }}
                            </span>

                            <span
                                class="rounded-full px-3 py-1 text-xs font-medium"
                                :class="statusBadge(selectedTicket.status)"
                            >
                                {{ formatLabel(selectedTicket.status) }}
                            </span>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Update Status
                            </label>
                            <select
                                :value="selectedTicket.status"
                                :disabled="statusLoading"
                                :class="inputClass"
                                @change="updateStatus($event.target.value)"
                            >
                                <option
                                    v-for="item in statuses"
                                    :key="item.value"
                                    :value="item.value"
                                >
                                    {{ item.label }}
                                </option>
                            </select>
                        </div>

                        <div
                            class="space-y-4 border-t border-slate-500/10 pt-5"
                        >
                            <div class="flex gap-3">
                                <User
                                    :size="17"
                                    class="mt-0.5 text-indigo-500"
                                />
                                <div>
                                    <p class="text-xs" :class="muted">
                                        Customer
                                    </p>
                                    <p class="mt-1 text-sm font-medium">
                                        {{ selectedTicket.customer_name }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <Mail
                                    :size="17"
                                    class="mt-0.5 text-indigo-500"
                                />
                                <div class="min-w-0">
                                    <p class="text-xs" :class="muted">Email</p>
                                    <p
                                        class="mt-1 break-words text-sm font-medium"
                                    >
                                        {{
                                            selectedTicket.customer_email || "—"
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <Store
                                    :size="17"
                                    class="mt-0.5 text-indigo-500"
                                />
                                <div>
                                    <p class="text-xs" :class="muted">Store</p>
                                    <p class="mt-1 text-sm font-medium">
                                        {{
                                            selectedTicket.store_name ||
                                            `Store #${selectedTicket.store_id}`
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <ShoppingBag
                                    :size="17"
                                    class="mt-0.5 text-indigo-500"
                                />
                                <div>
                                    <p class="text-xs" :class="muted">Order</p>
                                    <p class="mt-1 text-sm font-medium">
                                        {{
                                            selectedTicket.order_number
                                                ? `#${selectedTicket.order_number}`
                                                : "Not linked"
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <CalendarDays
                                    :size="17"
                                    class="mt-0.5 text-indigo-500"
                                />
                                <div>
                                    <p class="text-xs" :class="muted">
                                        Created
                                    </p>
                                    <p class="mt-1 text-sm font-medium">
                                        {{
                                            formatDate(
                                                selectedTicket.created_at,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Conversation -->
                    <div class="flex min-h-0 min-w-0 flex-col overflow-hidden">
                        <div class="shrink-0 border-b border-slate-500/10 p-5">
                            <h3 class="flex items-center gap-2 font-semibold">
                                <MessageSquare
                                    :size="18"
                                    class="text-indigo-500"
                                />
                                Conversation
                            </h3>
                            <p class="mt-1 text-xs" :class="muted">
                                Customer complaint and store owner replies
                            </p>
                            <p
                                class="mt-1 flex items-center gap-1 text-xs text-emerald-500"
                            >
                                <RefreshCw :size="12" /> Auto-refresh every 15
                                seconds
                            </p>
                            <p
                                v-if="newMessageNotice"
                                class="mt-2 rounded-lg bg-amber-500/10 p-2 text-xs text-amber-600"
                                role="status"
                            >
                                {{ newMessageNotice }}
                            </p>
                        </div>

                        <div
                            class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5"
                        >
                            <p
                                v-if="!messages.length"
                                class="py-10 text-center text-sm"
                                :class="muted"
                            >
                                No messages yet.
                            </p>

                            <div
                                v-for="message in messages"
                                :key="message.id"
                                class="flex"
                                :class="
                                    message.sender_type === 'owner'
                                        ? 'justify-end'
                                        : 'justify-start'
                                "
                            >
                                <div
                                    class="max-w-[85%] rounded-2xl p-4"
                                    :class="
                                        message.sender_type === 'owner'
                                            ? 'bg-indigo-600 text-white'
                                            : isDark
                                              ? 'bg-white/10 text-slate-100'
                                              : 'bg-slate-100 text-slate-800'
                                    "
                                >
                                    <p class="mb-2 text-xs font-semibold">
                                        {{
                                            message.sender_type === "owner"
                                                ? "Store Owner"
                                                : message.sender_type === "ai"
                                                  ? "AI"
                                                  : "Customer"
                                        }}
                                    </p>

                                    <p
                                        class="whitespace-pre-wrap break-words text-sm leading-6"
                                    >
                                        {{ message.message }}
                                    </p>

                                    <p class="mt-2 text-[11px] opacity-70">
                                        {{ formatDate(message.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Reply Panel -->
                        <form
                            class="max-h-[60%] shrink-0 space-y-4 overflow-y-auto border-t border-slate-500/10 p-5"
                            @submit.prevent="sendReply"
                        >
                            <div
                                v-if="emailStatus"
                                class="flex items-start gap-3 rounded-xl border p-4 text-sm"
                                :class="
                                    emailStatus.status === 'sent'
                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-500'
                                        : 'border-amber-500/20 bg-amber-500/10 text-amber-500'
                                "
                            >
                                <CheckCircle2
                                    v-if="emailStatus.status === 'sent'"
                                    :size="19"
                                />
                                <AlertTriangle v-else :size="19" />
                                <div class="flex-1">
                                    <p class="font-semibold">
                                        Email Notification:
                                        {{ formatLabel(emailStatus.status) }}
                                    </p>
                                    <p class="mt-1 text-xs">
                                        {{ emailStatus.message }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="emailStatus = null"
                                >
                                    <X :size="15" />
                                </button>
                            </div>

                            <p
                                v-if="error"
                                class="rounded-lg bg-red-500/10 p-3 text-xs text-red-500"
                            >
                                {{ error }}
                            </p>

                            <p
                                v-if="notice"
                                class="rounded-lg bg-emerald-500/10 p-3 text-xs text-emerald-500"
                            >
                                {{ notice }}
                            </p>

                            <!-- AI Analysis -->
                            <div
                                class="rounded-xl border p-4"
                                :class="
                                    isDark
                                        ? 'border-purple-500/20 bg-purple-500/5'
                                        : 'border-purple-200 bg-purple-50/50'
                                "
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <div class="flex items-center gap-2">
                                        <BrainCircuit
                                            :size="20"
                                            class="text-purple-500"
                                        />
                                        <div>
                                            <p class="text-sm font-semibold">
                                                AI Ticket Analysis
                                            </p>
                                            <p class="text-xs" :class="muted">
                                                Understand customer sentiment,
                                                urgency and next action.
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        :disabled="
                                            analysisLoading ||
                                            aiLoading ||
                                            replyLoading
                                        "
                                        class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-xs font-semibold text-white disabled:opacity-50"
                                        @click="analyzeTicket"
                                    >
                                        <Loader2
                                            v-if="analysisLoading"
                                            :size="16"
                                            class="animate-spin"
                                        />
                                        <Activity v-else :size="16" />
                                        {{
                                            analysisLoading
                                                ? "Analyzing..."
                                                : "Analyze Ticket"
                                        }}
                                    </button>
                                </div>

                                <p
                                    v-if="analysisError"
                                    class="mt-3 rounded-lg bg-red-500/10 p-3 text-xs text-red-500"
                                >
                                    {{ analysisError }}
                                </p>

                                <div v-if="analysis" class="mt-4 space-y-3">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div
                                            class="rounded-xl border border-slate-500/10 p-3"
                                        >
                                            <p
                                                class="mb-2 text-xs"
                                                :class="muted"
                                            >
                                                Customer Sentiment
                                            </p>
                                            <span class="text-sm font-semibold">
                                                {{
                                                    formatLabel(
                                                        analysis.sentiment,
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <div
                                            class="rounded-xl border border-slate-500/10 p-3"
                                        >
                                            <p
                                                class="mb-2 text-xs"
                                                :class="muted"
                                            >
                                                Ticket Urgency
                                            </p>
                                            <span
                                                class="rounded-full px-2.5 py-1 text-xs"
                                                :class="
                                                    priorityBadge(
                                                        analysis.urgency,
                                                    )
                                                "
                                            >
                                                {{
                                                    formatLabel(
                                                        analysis.urgency,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-xl border border-slate-500/10 p-3"
                                    >
                                        <div
                                            class="mb-2 flex items-center gap-2"
                                        >
                                            <ClipboardList
                                                :size="16"
                                                class="text-indigo-500"
                                            />
                                            <p class="text-sm font-semibold">
                                                AI Summary
                                            </p>
                                        </div>
                                        <p
                                            class="text-sm leading-6"
                                            :class="muted"
                                        >
                                            {{
                                                analysis.summary ||
                                                "No summary available."
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div
                                            class="rounded-xl border border-slate-500/10 p-3"
                                        >
                                            <p class="text-xs" :class="muted">
                                                Suggested Category
                                            </p>
                                            <p
                                                class="mt-2 text-sm font-semibold"
                                            >
                                                {{
                                                    formatLabel(
                                                        analysis.suggested_category,
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-xl border border-slate-500/10 p-3"
                                        >
                                            <p class="text-xs" :class="muted">
                                                Suggested Priority
                                            </p>
                                            <p
                                                class="mt-2 text-sm font-semibold"
                                            >
                                                {{
                                                    formatLabel(
                                                        analysis.suggested_priority,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3"
                                    >
                                        <div
                                            class="mb-2 flex items-center gap-2"
                                        >
                                            <Lightbulb
                                                :size="16"
                                                class="text-emerald-500"
                                            />
                                            <p class="text-sm font-semibold">
                                                Recommended Action
                                            </p>
                                        </div>
                                        <p
                                            class="text-sm leading-6"
                                            :class="muted"
                                        >
                                            {{
                                                analysis.recommended_action ||
                                                "Not provided."
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- AI Reply -->
                            <div
                                class="rounded-xl border p-4"
                                :class="
                                    isDark
                                        ? 'border-indigo-500/20 bg-indigo-500/5'
                                        : 'border-indigo-200 bg-indigo-50/50'
                                "
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <div class="flex items-center gap-2">
                                        <Bot
                                            :size="19"
                                            class="text-indigo-500"
                                        />
                                        <div>
                                            <p class="text-sm font-semibold">
                                                Gemini AI Assistant
                                            </p>
                                            <p class="text-xs" :class="muted">
                                                Generates a draft for review.
                                                Never auto-sends.
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        :disabled="
                                            aiLoading ||
                                            replyLoading ||
                                            approvalLoading
                                        "
                                        :class="primaryButton"
                                        @click="generateAIReply"
                                    >
                                        <Loader2
                                            v-if="aiLoading"
                                            :size="16"
                                            class="animate-spin"
                                        />
                                        <Sparkles v-else :size="16" />
                                        {{
                                            aiLoading
                                                ? "Generating..."
                                                : "Generate AI Reply"
                                        }}
                                    </button>
                                </div>

                                <p
                                    v-if="aiError"
                                    class="mt-3 rounded-lg bg-red-500/10 p-3 text-xs text-red-500"
                                >
                                    {{ aiError }}
                                </p>

                                <div v-if="aiSuggestion" class="mt-4 space-y-3">
                                    <label class="text-xs font-semibold">
                                        Suggested Reply — edit before using
                                    </label>

                                    <textarea
                                        v-model="aiSuggestion"
                                        rows="5"
                                        maxlength="10000"
                                        :class="inputClass"
                                    />

                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white"
                                        @click="useAIDraft"
                                    >
                                        <Pencil :size="14" />
                                        Use This Reply
                                    </button>
                                </div>
                            </div>

                            <!-- Reply Editor -->
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-semibold"
                                        >Your Reply</label
                                    >

                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="
                                            isReplyApproved
                                                ? 'bg-emerald-500/10 text-emerald-500'
                                                : 'bg-amber-500/10 text-amber-500'
                                        "
                                    >
                                        <ShieldCheck
                                            v-if="isReplyApproved"
                                            :size="14"
                                        />
                                        <AlertTriangle v-else :size="14" />
                                        {{
                                            isReplyApproved
                                                ? "Approved"
                                                : "Approval Required"
                                        }}
                                    </span>
                                </div>

                                <textarea
                                    v-model="reply"
                                    rows="4"
                                    maxlength="10000"
                                    :class="inputClass"
                                    :disabled="approvalLoading || replyLoading"
                                    placeholder="Write a reply to this ticket..."
                                    @input="onReplyChange"
                                />

                                <div
                                    class="rounded-xl border p-3 text-xs"
                                    :class="
                                        isReplyApproved
                                            ? 'border-emerald-500/20 bg-emerald-500/5'
                                            : 'border-amber-500/20 bg-amber-500/5'
                                    "
                                >
                                    <p class="font-semibold">
                                        {{
                                            isReplyApproved
                                                ? "Reply approved"
                                                : "Review required"
                                        }}
                                    </p>
                                    <p class="mt-1 leading-5" :class="muted">
                                        {{
                                            isReplyApproved
                                                ? "This exact version is approved. Editing requires new approval."
                                                : "Review carefully and approve before sending."
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold"
                                        >Reply Approval Type</label
                                    >
                                    <select
                                        v-model="approvalType"
                                        :class="inputClass"
                                        :disabled="
                                            isReplyApproved ||
                                            approvalLoading ||
                                            replyLoading
                                        "
                                        @change="resetApproval"
                                    >
                                        <option value="general_support">
                                            General Support
                                        </option>
                                        <option value="verification_request">
                                            Request Customer Verification
                                        </option>
                                        <option value="order_specific">
                                            Verified Order Reply
                                        </option>
                                    </select>
                                    <p class="text-xs" :class="muted">
                                        Order-specific replies require a
                                        verified order. Verification requests
                                        may have a 24-hour cooldown.
                                    </p>
                                </div>

                                <div class="flex flex-wrap justify-end gap-2">
                                    <button
                                        type="button"
                                        :disabled="
                                            !reply.trim() ||
                                            approvalLoading ||
                                            replyLoading ||
                                            isReplyApproved
                                        "
                                        class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                                        @click="approveReply"
                                    >
                                        <Loader2
                                            v-if="approvalLoading"
                                            :size="16"
                                            class="animate-spin"
                                        />
                                        <ShieldCheck v-else :size="16" />
                                        {{
                                            approvalLoading
                                                ? "Approving..."
                                                : isReplyApproved
                                                  ? "Approved"
                                                  : "Approve Reply"
                                        }}
                                    </button>

                                    <button
                                        type="submit"
                                        :disabled="
                                            replyLoading ||
                                            approvalLoading ||
                                            !isReplyApproved
                                        "
                                        :class="primaryButton"
                                    >
                                        <Loader2
                                            v-if="replyLoading"
                                            :size="16"
                                            class="animate-spin"
                                        />
                                        <Send v-else :size="16" />
                                        {{
                                            replyLoading
                                                ? "Sending..."
                                                : "Send Reply"
                                        }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div v-else class="p-10 text-center" :class="muted">
                    Ticket not found.
                </div>
            </div>
        </div>
    </div>
</template>
