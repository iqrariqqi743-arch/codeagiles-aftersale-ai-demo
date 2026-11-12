<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";
import { useTheme } from "@/composables/useTheme";
import {
    ArrowLeft,
    RefreshCw,
    MessageSquare,
    User,
    Bot,
    Sparkles,
    BrainCircuit,
    ShieldCheck,
    Send,
    Loader2,
    AlertCircle,
    CheckCircle2,
    ShoppingBag,
    Activity,
    Lightbulb,
    Tag,
    Clock3,
} from "lucide-vue-next";

const route = useRoute();
const router = useRouter();
const { isDark } = useTheme();
const api = axios.create({ baseURL: import.meta.env.VITE_API_URL || "/api" });
api.interceptors.request.use((config) => {
    const token = localStorage.getItem("accessToken");
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});
const ticketId = computed(() => route.params.id);
const ticket = ref(null);
const messages = ref([]);
const loading = ref(true);
const refreshing = ref(false);
const statusLoading = ref(false);
const aiLoading = ref(false);
const kbLoading = ref(false);
const analysisLoading = ref(false);
const applying = ref(false);
const approving = ref(false);
const sending = ref(false);
const analysis = ref(null);
const analysisError = ref("");
const analysisApplied = ref(false);
const reply = ref("");
const approvalType = ref("general_support");
const approvalId = ref(null);
const approvedMessage = ref("");
const approvedType = ref("");
const approvedAt = ref(0);
const replySources = ref([]);
const informationStatus = ref("");
const notice = ref({ type: "", text: "" });
const newMessages = ref(0);
const conversationRef = ref(null);
const statuses = [
    { value: "open", label: "Open" },
    { value: "in_progress", label: "In Progress" },
    { value: "waiting_customer", label: "Waiting Customer" },
    { value: "resolved", label: "Resolved" },
    { value: "closed", label: "Closed" },
];
const categories = [
    "general",
    "delivery",
    "damaged_product",
    "wrong_product",
    "return",
    "refund",
    "other",
];
const priorities = ["low", "medium", "high", "urgent"];
const formatLabel = (value) =>
    String(value || "N/A")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (char) => char.toUpperCase());
const formatDate = (value) => {
    if (!value) return "N/A";
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? value : d.toLocaleString();
};
const errorText = (err, fallback = "Request failed") =>
    err?.response?.data?.message ||
    (err?.response?.data?.errors &&
        Object.values(err.response.data.errors).flat().join(" ")) ||
    err?.message ||
    fallback;
const inform = (type, text) => {
    notice.value = { type, text };
};
const resetApproval = () => {
    approvalId.value = null;
    approvedMessage.value = "";
    approvedType.value = "";
    approvedAt.value = 0;
};

const selectDraftApprovalType = (draft) => {
    const verificationStatus = ticket.value?.order_verification?.status || "";

    // A verified order must not use verification_request.
    if (verificationStatus === "verified") {
        approvalType.value = "order_specific";
        return;
    }

    // Detect when a draft asks the customer to verify their order.
    const asksForVerification =
        /\b(?:provide|share|send|confirm|verify)\b[\s\S]{0,120}\b(?:order number|order id|registered email|billing email|identity|ownership)\b/i.test(
            draft,
        ) || /\bverification (?:is )?required\b/i.test(draft);

    approvalType.value = asksForVerification
        ? "verification_request"
        : "general_support";
};

const approved = computed(
    () =>
        Boolean(approvalId.value) &&
        approvedMessage.value === reply.value.trim() &&
        approvedType.value === approvalType.value &&
        Date.now() - approvedAt.value < 14 * 60 * 1000,
);
const card = computed(() =>
    isDark.value
        ? "border-white/10 bg-[#111c2e] text-slate-100"
        : "border-slate-200 bg-white text-slate-900",
);
const muted = computed(() =>
    isDark.value ? "text-slate-400" : "text-slate-500",
);
const inputClass = computed(
    () =>
        `w-full rounded-xl border px-4 py-3 text-sm outline-none transition focus:border-cyan-500 ${isDark.value ? "border-white/10 bg-[#0b1220] text-white" : "border-slate-200 bg-white text-slate-900"}`,
);
const badge = (value) =>
    ({
        low: "bg-slate-500/10 text-slate-500",
        medium: "bg-blue-500/10 text-blue-500",
        high: "bg-orange-500/10 text-orange-500",
        urgent: "bg-red-500/10 text-red-500",
        positive: "bg-emerald-500/10 text-emerald-500",
        neutral: "bg-blue-500/10 text-blue-500",
        negative: "bg-red-500/10 text-red-500",
        verified: "bg-emerald-500/10 text-emerald-500",
    })[String(value || "").toLowerCase()] || "bg-slate-500/10 text-slate-500";
let generation = 0;
let timer = null;
let pollBusy = false;
let operationBusy = false;
const scrollToBottom = async () => {
    await nextTick();
    if (conversationRef.value)
        conversationRef.value.scrollTop = conversationRef.value.scrollHeight;
};
async function fetchTicket(initial = false) {
    const current = ticketId.value;
    const request = generation;
    if (initial) loading.value = true;
    else refreshing.value = true;
    try {
        const { data } = await api.get(`/tickets/${current}`);
        if (request !== generation || current !== ticketId.value) return;
        if (!data?.ticket) throw new Error(data?.message || "Ticket not found");
        const incoming = Array.isArray(data.messages) ? data.messages : [];
        const previous = new Set(messages.value.map((m) => String(m.id)));
        const added = !initial
            ? incoming.filter(
                  (m) =>
                      m.sender_type === "customer" &&
                      !previous.has(String(m.id)),
              )
            : [];
        ticket.value = data.ticket;
        messages.value = incoming;
        if (added.length) {
            newMessages.value += added.length;
            resetApproval();
            analysis.value = null;
            analysisApplied.value = false;
            inform(
                "info",
                `${added.length} new customer message(s) received. Review before replying.`,
            );
            scrollToBottom();
        } else if (initial) scrollToBottom();
    } catch (err) {
        if (request === generation) inform("error", errorText(err));
    } finally {
        if (request === generation) {
            loading.value = false;
            refreshing.value = false;
        }
    }
}
async function poll() {
    if (
        pollBusy ||
        operationBusy ||
        document.hidden ||
        !ticket.value ||
        loading.value
    )
        return;
    pollBusy = true;
    try {
        await fetchTicket(false);
    } finally {
        pollBusy = false;
    }
}
function init() {
    generation++;
    ticket.value = null;
    messages.value = [];
    reply.value = "";
    analysis.value = null;
    analysisError.value = "";
    analysisApplied.value = false;
    replySources.value = [];
    informationStatus.value = "";
    newMessages.value = 0;
    resetApproval();
    notice.value = { type: "", text: "" };
    fetchTicket(true);
}
onMounted(() => {
    init();
    timer = window.setInterval(poll, 10000);
    document.addEventListener("visibilitychange", poll);
});
onUnmounted(() => {
    generation++;
    if (timer) clearInterval(timer);
    document.removeEventListener("visibilitychange", poll);
});
watch(ticketId, init);
function onReplyEdit() {
    resetApproval();
}
async function changeStatus(event) {
    const value = event.target.value;
    const old = ticket.value?.status;
    if (!ticket.value || value === old) return;
    statusLoading.value = true;
    operationBusy = true;
    try {
        const { data } = await api.patch(`/tickets/${ticketId.value}/status`, {
            status: value,
        });
        if (data?.success === false) throw new Error(data.message);
        ticket.value = {
            ...ticket.value,
            ...(data.ticket || {}),
            status: value,
        };
        inform("success", "Ticket status updated.");
    } catch (err) {
        inform("error", errorText(err));
    } finally {
        statusLoading.value = false;
        operationBusy = false;
    }
}
async function analyzeTicket() {
    if (analysisLoading.value) return;
    analysisLoading.value = true;
    analysisError.value = "";
    analysisApplied.value = false;
    operationBusy = true;
    const request = generation;
    try {
        const { data } = await api.post(
            `/ai/tickets/${ticketId.value}/analyze`,
        );
        if (request !== generation) return;
        if (!data?.analysis || data.success === false)
            throw new Error(data.message || "AI analysis unavailable");
        const a = data.analysis;
        analysis.value = {
            sentiment: a.sentiment,
            urgency: a.urgency || a.priority,
            summary: a.summary,
            suggested_category: a.suggested_category || a.category,
            suggested_priority: a.suggested_priority || a.priority,
            recommended_action: a.recommended_action || a.suggested_action,
            confidence: a.confidence,
        };
        inform("success", "AI analysis completed.");
    } catch (err) {
        if (request === generation) analysisError.value = errorText(err);
    } finally {
        analysisLoading.value = false;
        operationBusy = false;
    }
}
async function applySuggestions() {
    if (!analysis.value || applying.value) return;
    const category = String(analysis.value.suggested_category || "")
        .toLowerCase()
        .trim()
        .replaceAll(" ", "_");
    const priority = String(analysis.value.suggested_priority || "")
        .toLowerCase()
        .trim();
    if (!categories.includes(category) || !priorities.includes(priority))
        return inform("error", "AI returned an invalid category or priority.");
    if (
        !window.confirm(
            `Apply AI suggestions?\nCategory: ${formatLabel(category)}\nPriority: ${formatLabel(priority)}`,
        )
    )
        return;
    applying.value = true;
    operationBusy = true;
    try {
        const { data } = await api.patch(
            `/tickets/${ticketId.value}/apply-ai-suggestions`,
            { category, priority },
        );
        if (data?.success === false) throw new Error(data.message);
        ticket.value = {
            ...ticket.value,
            ...(data.ticket || {}),
            category,
            priority,
        };
        analysisApplied.value = true;
        inform("success", "AI suggestions applied.");
    } catch (err) {
        inform(
            "error",
            errorText(
                err,
                "Apply suggestions failed. Confirm the Laravel route exists.",
            ),
        );
    } finally {
        applying.value = false;
        operationBusy = false;
    }
}
async function generateAI() {
    if (aiLoading.value || kbLoading.value) return;
    aiLoading.value = true;
    operationBusy = true;
    resetApproval();
    const request = generation;
    try {
        const { data } = await api.post(
            `/tickets/${ticketId.value}/generate-ai-reply`,
        );
        if (request !== generation) return;
        if (data?.success === false || data?.policy_reviewed !== true)
            throw new Error(data?.message || "AI policy review not completed.");
        const draft = String(data?.suggested_reply || "").trim();
        if (!draft) throw new Error("AI did not return a reply.");
        reply.value = draft;
        replySources.value = [];
        informationStatus.value = "";
        selectDraftApprovalType(draft);
        inform("success", "AI draft ready. Review before approving.");
    } catch (err) {
        if (request === generation) inform("error", errorText(err));
    } finally {
        aiLoading.value = false;
        operationBusy = false;
    }
}

async function generateKB() {
    if (kbLoading.value || aiLoading.value) return;

    kbLoading.value = true;
    operationBusy = true;
    resetApproval();

    const request = generation;

    try {
        const { data } = await api.post(
            `/ai/tickets/${ticketId.value}/generate-reply`,
        );

        if (request !== generation) return;

        if (data?.success === false || !String(data?.reply || "").trim()) {
            throw new Error(data?.message || "KB reply not returned.");
        }

        const draft = String(data.reply).trim();

        reply.value = draft;
        replySources.value = Array.isArray(data.sources) ? data.sources : [];

        informationStatus.value = data.information_status || "not_available";

        // Automatically select the appropriate approval type.
        selectDraftApprovalType(draft);

        inform(
            "success",
            "Knowledge Base draft ready. Review approval type before approving.",
        );
    } catch (err) {
        if (request === generation) {
            inform("error", errorText(err, "Knowledge Base API unavailable."));
        }
    } finally {
        kbLoading.value = false;
        operationBusy = false;
    }
}

async function approveReply() {
    const message = reply.value.trim();
    if (!message || approving.value) return;
    approving.value = true;
    operationBusy = true;
    resetApproval();
    try {
        const { data } = await api.post(
            `/tickets/${ticketId.value}/approve-reply`,
            { message, approval_type: approvalType.value },
        );
        if (data?.success === false) throw new Error(data.message);
        const id =
            data?.approval?.id ||
            data?.reply_approval?.id ||
            data?.ticket_reply_approval?.id ||
            data?.approval_id;
        if (!id) throw new Error("Approval ID missing from backend response.");
        approvalId.value = id;
        approvedMessage.value = message;
        approvedType.value = approvalType.value;
        approvedAt.value = Date.now();
        inform("success", "Reply approved. You can send it now.");
    } catch (err) {
        resetApproval();
        inform("error", errorText(err));
    } finally {
        approving.value = false;
        operationBusy = false;
    }
}
async function sendReply() {
    if (!approved.value || sending.value)
        return inform("error", "Approve this exact reply before sending.");
    sending.value = true;
    operationBusy = true;
    const current = ticketId.value;
    try {
        const { data } = await api.post(`/tickets/${current}/messages`, {
            message: reply.value.trim(),
            approval_id: approvalId.value,
        });
        if (data?.success === false) throw new Error(data.message);
        resetApproval();
        reply.value = "";
        replySources.value = [];
        informationStatus.value = "";
        const email = data.email_notification || {};
        if (email.sent === true || email.status === "sent")
            inform("success", "Reply saved and email accepted by SMTP.");
        else
            inform(
                "info",
                data.message ||
                    "Reply saved; email delivery status needs checking. Do not resend without verifying.",
            );
        await fetchTicket(false);
        scrollToBottom();
    } catch (err) {
        resetApproval();
        inform(
            "error",
            `${errorText(err)} Check the conversation before retrying to avoid duplicate emails.`,
        );
        await fetchTicket(false);
    } finally {
        sending.value = false;
        operationBusy = false;
    }
}
</script>

<template>
    <div
        class="min-h-screen space-y-6 pb-10"
        :class="isDark ? 'text-white' : 'text-slate-900'"
    >
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <button
                    class="mb-4 inline-flex items-center gap-2 text-sm hover:text-cyan-500"
                    :class="muted"
                    @click="router.push('/tickets')"
                >
                    <ArrowLeft :size="17" /> Back to Tickets
                </button>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold sm:text-3xl">
                        {{ ticket?.subject || "Ticket Details" }}
                    </h1>
                    <span
                        v-if="ticket"
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="badge(ticket.priority)"
                        >{{ formatLabel(ticket.priority) }}</span
                    >
                </div>
                <p class="mt-2 text-sm" :class="muted">
                    Ticket #{{ ticket?.ticket_number || ticketId }}
                </p>
            </div>
            <button
                class="inline-flex items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm font-semibold"
                :class="card"
                :disabled="refreshing"
                @click="fetchTicket(false)"
            >
                <RefreshCw
                    :size="17"
                    :class="refreshing ? 'animate-spin' : ''"
                />
                Refresh
            </button>
        </div>
        <div
            v-if="notice.text"
            class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm"
            :class="
                notice.type === 'error'
                    ? 'border-red-500/20 bg-red-500/10 text-red-500'
                    : notice.type === 'success'
                      ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-500'
                      : 'border-cyan-500/20 bg-cyan-500/10 text-cyan-500'
            "
        >
            <AlertCircle :size="18" /><span class="flex-1">{{
                notice.text
            }}</span
            ><button @click="notice.text = ''">✕</button>
        </div>
        <div
            v-if="loading"
            class="flex min-h-[55vh] flex-col items-center justify-center gap-4"
        >
            <Loader2 :size="38" class="animate-spin text-cyan-500" /> Loading
            ticket details...
        </div>
        <div v-else-if="!ticket" class="py-20 text-center">
            <AlertCircle :size="40" class="mx-auto text-red-500" />
            <h2 class="mt-4 text-xl font-bold">Ticket not found</h2>
        </div>
        <div
            v-else
            class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_330px]"
        >
            <div class="min-w-0 space-y-6">
                <section
                    class="overflow-hidden rounded-2xl border"
                    :class="card"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-500/10 px-5 py-4"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="rounded-xl bg-cyan-500/10 p-3 text-cyan-500"
                            >
                                <MessageSquare :size="20" />
                            </div>
                            <div>
                                <h2 class="font-semibold">Conversation</h2>
                                <p class="text-xs" :class="muted">
                                    {{ messages.length }} messages ·
                                    auto-refresh every 10s
                                </p>
                            </div>
                        </div>
                        <span
                            v-if="newMessages"
                            class="rounded-full bg-cyan-500/10 px-3 py-1 text-xs text-cyan-500"
                            >{{ newMessages }} new</span
                        >
                    </div>
                    <div
                        ref="conversationRef"
                        class="max-h-[600px] space-y-5 overflow-y-auto p-5"
                    >
                        <p
                            v-if="!messages.length"
                            class="py-10 text-center text-sm"
                            :class="muted"
                        >
                            No messages found.
                        </p>
                        <div
                            v-for="(item, index) in messages"
                            :key="item.id || index"
                            class="flex gap-3"
                            :class="
                                item.sender_type === 'customer'
                                    ? 'justify-start'
                                    : 'justify-end'
                            "
                        >
                            <div
                                v-if="item.sender_type === 'customer'"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-blue-500"
                            >
                                <User :size="18" />
                            </div>
                            <div class="max-w-[85%] min-w-0">
                                <div
                                    class="mb-1 flex flex-wrap items-center gap-2"
                                    :class="
                                        item.sender_type !== 'customer'
                                            ? 'justify-end'
                                            : ''
                                    "
                                >
                                    <span
                                        class="text-xs font-semibold"
                                        :class="muted"
                                        >{{
                                            item.sender_type === "customer"
                                                ? "Customer"
                                                : "Store Owner"
                                        }}</span
                                    ><span class="text-[10px]" :class="muted">{{
                                        formatDate(item.created_at)
                                    }}</span>
                                </div>
                                <div
                                    class="whitespace-pre-wrap break-words rounded-2xl px-4 py-3 text-sm leading-relaxed"
                                    :class="
                                        item.sender_type === 'customer'
                                            ? isDark
                                                ? 'rounded-tl-sm bg-[#1c2b41] text-slate-200'
                                                : 'rounded-tl-sm bg-slate-100 text-slate-800'
                                            : 'rounded-tr-sm bg-gradient-to-br from-cyan-600 to-blue-600 text-white'
                                    "
                                >
                                    {{ item.message }}
                                </div>
                            </div>
                            <div
                                v-if="item.sender_type !== 'customer'"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-cyan-500/10 text-cyan-500"
                            >
                                <User :size="18" />
                            </div>
                        </div>
                    </div>
                </section>
                <section class="rounded-2xl border p-5" :class="card">
                    <div class="mb-5 flex items-center gap-3">
                        <div
                            class="rounded-xl bg-purple-500/10 p-3 text-purple-500"
                        >
                            <Bot :size="21" />
                        </div>
                        <div>
                            <h2 class="font-semibold">Reply Assistant</h2>
                            <p class="text-xs" :class="muted">
                                Generate, review and approve before sending.
                            </p>
                        </div>
                    </div>
                    <div class="mb-5 flex flex-wrap gap-3">
                        <button
                            class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            :disabled="aiLoading || kbLoading || sending"
                            @click="generateAI"
                        >
                            <Loader2
                                v-if="aiLoading"
                                :size="17"
                                class="animate-spin"
                            /><Sparkles v-else :size="17" />{{
                                aiLoading
                                    ? "Generating..."
                                    : "Generate AI Reply"
                            }}</button
                        ><button
                            class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-600 to-teal-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            :disabled="kbLoading || aiLoading || sending"
                            @click="generateKB"
                        >
                            <Loader2
                                v-if="kbLoading"
                                :size="17"
                                class="animate-spin"
                            /><BrainCircuit v-else :size="17" />{{
                                kbLoading
                                    ? "Generating KB..."
                                    : "Generate KB Reply"
                            }}
                        </button>
                    </div>
                    <textarea
                        v-model="reply"
                        rows="8"
                        maxlength="10000"
                        :disabled="
                            sending || approving || aiLoading || kbLoading
                        "
                        :class="inputClass"
                        class="resize-y leading-relaxed"
                        placeholder="Generate an AI reply or write your own response..."
                        @input="onReplyEdit"
                    ></textarea>
                    <div
                        v-if="informationStatus"
                        class="mt-4 rounded-xl border border-slate-500/10 p-4"
                    >
                        <p class="text-xs font-semibold">
                            Knowledge Base Information:
                            {{ formatLabel(informationStatus) }}
                        </p>
                        <div
                            v-if="replySources.length"
                            class="mt-3 flex flex-wrap gap-2"
                        >
                            <span
                                v-for="(source, index) in replySources"
                                :key="source.id || index"
                                class="rounded-lg bg-cyan-500/10 px-3 py-2 text-xs text-cyan-500"
                                >{{
                                    source.title ||
                                    source.name ||
                                    "Knowledge Base Source"
                                }}</span
                            >
                        </div>
                    </div>
                    <div
                        class="mt-4 flex flex-wrap items-center justify-between gap-3"
                    >
                        <span class="text-xs" :class="muted"
                            >{{ reply.length }} / 10000 characters</span
                        ><span
                            v-if="approved"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-3 py-1 text-xs text-emerald-500"
                            ><ShieldCheck :size="14" /> Approved</span
                        >
                    </div>
                    <div class="mt-4">
                        <label
                            class="mb-2 block text-xs font-semibold"
                            :class="muted"
                            >Approval Type</label
                        ><select
                            v-model="approvalType"
                            :class="inputClass"
                            :disabled="approving || sending"
                            @change="resetApproval"
                        >
                            <option value="general_support">
                                General Support
                            </option>
                            <option value="verification_request">
                                Verification Request
                            </option>
                            <option value="order_specific">
                                Order Specific
                            </option>
                        </select>
                        <p class="mt-2 text-xs" :class="muted">
                            Use Order Specific only after verifying the
                            customer's order and the exact proposed action.
                        </p>
                    </div>
                    <div
                        class="mt-5 flex flex-wrap justify-end gap-3 border-t border-slate-500/10 pt-5"
                    >
                        <button
                            class="inline-flex items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-5 py-3 text-sm font-semibold text-emerald-500 disabled:opacity-50"
                            :disabled="
                                !reply.trim() ||
                                approved ||
                                approving ||
                                sending ||
                                aiLoading ||
                                kbLoading
                            "
                            @click="approveReply"
                        >
                            <Loader2
                                v-if="approving"
                                :size="17"
                                class="animate-spin"
                            /><CheckCircle2 v-else :size="17" />{{
                                approved ? "Reply Approved" : "Approve Reply"
                            }}</button
                        ><button
                            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            :disabled="!approved || sending || approving"
                            @click="sendReply"
                        >
                            <Loader2
                                v-if="sending"
                                :size="17"
                                class="animate-spin"
                            /><Send v-else :size="17" />{{
                                sending ? "Sending..." : "Send Reply"
                            }}
                        </button>
                    </div>
                    <p
                        class="mt-4 flex items-start gap-2 text-xs"
                        :class="muted"
                    >
                        <ShieldCheck
                            :size="15"
                            class="shrink-0 text-emerald-500"
                        />
                        AI never sends messages automatically. Approve the exact
                        draft before sending.
                    </p>
                </section>
            </div>
            <aside class="min-w-0 space-y-6">
                <section
                    class="overflow-hidden rounded-2xl border"
                    :class="card"
                >
                    <div
                        class="border-b border-slate-500/10 bg-gradient-to-r from-purple-500/10 to-blue-500/10 p-5"
                    >
                        <div class="flex items-center gap-3">
                            <BrainCircuit :size="25" class="text-purple-500" />
                            <div>
                                <h3 class="font-semibold">
                                    AI Ticket Analysis
                                </h3>
                                <p class="text-xs" :class="muted">
                                    Understand the customer's issue
                                </p>
                            </div>
                        </div>
                        <button
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            :disabled="analysisLoading"
                            @click="analyzeTicket"
                        >
                            <Loader2
                                v-if="analysisLoading"
                                :size="17"
                                class="animate-spin"
                            /><Sparkles v-else :size="17" />{{
                                analysisLoading
                                    ? "Analyzing..."
                                    : analysis
                                      ? "Analyze Again"
                                      : "Analyze Ticket"
                            }}
                        </button>
                    </div>
                    <div class="space-y-4 p-5">
                        <p
                            v-if="analysisError"
                            class="rounded-lg bg-red-500/10 p-3 text-xs text-red-500"
                        >
                            {{ analysisError }}
                        </p>
                        <p
                            v-if="
                                !analysis && !analysisLoading && !analysisError
                            "
                            class="py-5 text-center text-sm"
                            :class="muted"
                        >
                            No analysis yet. Click Analyze Ticket.
                        </p>
                        <template v-if="analysis"
                            ><div>
                                <p class="mb-2 text-xs" :class="muted">
                                    Customer Sentiment
                                </p>
                                <span
                                    class="rounded-full px-3 py-1.5 text-xs font-semibold"
                                    :class="badge(analysis.sentiment)"
                                    >{{ formatLabel(analysis.sentiment) }}</span
                                >
                            </div>
                            <div>
                                <p class="mb-2 text-xs" :class="muted">
                                    Urgency Level
                                </p>
                                <span
                                    class="rounded-full px-3 py-1.5 text-xs font-semibold"
                                    :class="badge(analysis.urgency)"
                                    >{{ formatLabel(analysis.urgency) }}</span
                                >
                            </div>
                            <div class="border-t border-slate-500/10 pt-4">
                                <p
                                    class="mb-2 flex items-center gap-2 text-xs font-semibold"
                                >
                                    <Activity
                                        :size="15"
                                        class="text-purple-500"
                                    />
                                    AI Summary
                                </p>
                                <p class="text-sm leading-6" :class="muted">
                                    {{ analysis.summary || "N/A" }}
                                </p>
                            </div>
                            <div class="border-t border-slate-500/10 pt-4">
                                <p
                                    class="mb-2 flex items-center gap-2 text-xs font-semibold"
                                >
                                    <Tag :size="15" class="text-cyan-500" />
                                    Suggested Category
                                </p>
                                <span
                                    class="rounded-full bg-cyan-500/10 px-3 py-1.5 text-xs text-cyan-500"
                                    >{{
                                        formatLabel(analysis.suggested_category)
                                    }}</span
                                >
                            </div>
                            <div class="border-t border-slate-500/10 pt-4">
                                <p class="mb-2 text-xs font-semibold">
                                    Suggested Priority
                                </p>
                                <span
                                    class="rounded-full px-3 py-1.5 text-xs"
                                    :class="badge(analysis.suggested_priority)"
                                    >{{
                                        formatLabel(analysis.suggested_priority)
                                    }}</span
                                >
                            </div>
                            <div class="border-t border-slate-500/10 pt-4">
                                <p
                                    class="mb-2 flex items-center gap-2 text-xs font-semibold"
                                >
                                    <Lightbulb
                                        :size="15"
                                        class="text-amber-500"
                                    />
                                    Recommended Action
                                </p>
                                <p class="text-sm leading-6" :class="muted">
                                    {{ analysis.recommended_action || "N/A" }}
                                </p>
                            </div>
                            <button
                                v-if="!analysisApplied"
                                class="w-full rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                                :disabled="applying"
                                @click="applySuggestions"
                            >
                                {{
                                    applying
                                        ? "Applying..."
                                        : "Apply Suggestions"
                                }}
                            </button>
                            <p
                                v-else
                                class="rounded-xl bg-emerald-500/10 p-3 text-sm text-emerald-500"
                            >
                                AI suggestions applied.
                            </p>
                            <p class="text-xs" :class="muted">
                                AI recommendations are not applied
                                automatically.
                            </p></template
                        >
                    </div>
                </section>
                <section class="rounded-2xl border p-5" :class="card">
                    <h3 class="mb-5 font-semibold">Customer Information</h3>
                    <div class="flex items-center gap-3">
                        <div
                            class="rounded-full bg-cyan-500/10 p-3 text-cyan-500"
                        >
                            <User :size="23" />
                        </div>
                        <div class="min-w-0">
                            <h4 class="truncate font-semibold">
                                {{ ticket.customer_name || "N/A" }}
                            </h4>
                            <p class="break-all text-xs" :class="muted">
                                {{
                                    ticket.customer_email || "No email provided"
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-5 border-t border-slate-500/10 pt-4">
                        <p class="text-xs" :class="muted">Store</p>
                        <p class="mt-1 text-sm font-medium">
                            {{
                                ticket.store_name || ticket.store?.name || "N/A"
                            }}
                        </p>
                    </div>
                </section>
                <section class="rounded-2xl border p-5" :class="card">
                    <h3 class="mb-4 flex items-center gap-2 font-semibold">
                        <ShoppingBag :size="19" class="text-cyan-500" /> Order
                        Information
                    </h3>
                    <p class="text-xs" :class="muted">Order Number</p>
                    <p class="mt-1 font-semibold">
                        {{
                            ticket.order_number ||
                            ticket.linked_order?.order_number ||
                            "No linked order"
                        }}
                    </p>
                    <div class="mt-5 border-t border-slate-500/10 pt-4">
                        <p class="mb-2 text-xs" :class="muted">
                            Order Verification
                        </p>
                        <span
                            class="rounded-full px-3 py-1.5 text-xs font-semibold"
                            :class="badge(ticket.order_verification?.status)"
                            >{{
                                formatLabel(
                                    ticket.order_verification?.status ||
                                        "unknown",
                                )
                            }}</span
                        >
                        <p class="mt-3 text-xs leading-5" :class="muted">
                            {{
                                ticket.order_verification?.message ||
                                "Order verification information is not available."
                            }}
                        </p>
                    </div>
                    <div
                        v-if="ticket.linked_order"
                        class="mt-4 space-y-3 border-t border-slate-500/10 pt-4 text-sm"
                    >
                        <p>
                            Customer:
                            {{ ticket.linked_order.customer_name || "N/A" }}
                        </p>
                        <p class="break-all">
                            Email:
                            {{ ticket.linked_order.customer_email || "N/A" }}
                        </p>
                        <p>
                            Products:
                            {{ ticket.linked_order.items?.length || 0 }}
                        </p>
                    </div>
                </section>
                <section class="rounded-2xl border p-5" :class="card">
                    <h3 class="mb-5 font-semibold">Ticket Details</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-2 block text-xs" :class="muted"
                                >Ticket Status</label
                            ><select
                                :value="ticket.status"
                                :class="inputClass"
                                :disabled="statusLoading"
                                @change="changeStatus"
                            >
                                <option
                                    v-for="s in statuses"
                                    :key="s.value"
                                    :value="s.value"
                                >
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <p class="text-xs" :class="muted">Priority</p>
                            <span
                                class="mt-2 inline-flex rounded-full px-3 py-1.5 text-xs"
                                :class="badge(ticket.priority)"
                                >{{ formatLabel(ticket.priority) }}</span
                            >
                        </div>
                        <div>
                            <p class="text-xs" :class="muted">Category</p>
                            <p class="mt-1 text-sm">
                                {{ formatLabel(ticket.category || "general") }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs" :class="muted">Created At</p>
                            <p class="mt-1 text-sm">
                                {{ formatDate(ticket.created_at) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs" :class="muted">Last Updated</p>
                            <p class="mt-1 text-sm">
                                {{ formatDate(ticket.updated_at) }}
                            </p>
                        </div>
                    </div>
                </section>
                <section
                    class="rounded-2xl border border-purple-500/20 bg-gradient-to-br from-purple-500/10 to-blue-500/10 p-5"
                >
                    <h3
                        class="flex items-center gap-2 font-semibold text-purple-500"
                    >
                        <Sparkles :size="19" /> AI Powered Support
                    </h3>
                    <p class="mt-3 text-xs leading-relaxed" :class="muted">
                        Gemini uses ticket information and conversation history
                        to prepare a draft. Knowledge Base support depends on
                        your Laravel API.
                    </p>
                </section>
            </aside>
        </div>
    </div>
</template>
