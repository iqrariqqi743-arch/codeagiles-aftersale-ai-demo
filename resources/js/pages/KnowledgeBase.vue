<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import { useTheme } from "@/composables/useTheme";
import {
    BookOpen,
    Plus,
    Search,
    Pencil,
    Trash2,
    X,
    Save,
    Loader2,
    RefreshCw,
    FileText,
    Package,
    Truck,
    RotateCcw,
    CreditCard,
    CircleHelp,
    CheckCircle2,
    AlertCircle,
    Sparkles,
    Store,
    Power,
    ShieldCheck,
} from "lucide-vue-next";

const router = useRouter();
const { isDark } = useTheme();

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || "/api",
    headers: { Accept: "application/json" },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem("accessToken");
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

const categories = [
    { value: "faq", label: "FAQ", icon: CircleHelp },
    { value: "shipping", label: "Shipping", icon: Truck },
    { value: "return", label: "Returns", icon: RotateCcw },
    { value: "refund", label: "Refunds", icon: CreditCard },
    { value: "product", label: "Products", icon: Package },
    { value: "general", label: "General", icon: FileText },
];

const emptyForm = () => ({
    title: "",
    content: "",
    category: "faq",
    status: "active",
});

const stores = ref([]);
const selectedStoreId = ref("");
const articles = ref([]);
const storesLoading = ref(false);
const loading = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const updatingStatusId = ref(null);
const search = ref("");
const categoryFilter = ref("all");
const statusFilter = ref("all");
const modalOpen = ref(false);
const editingId = ref(null);
const form = ref(emptyForm());
const error = ref("");
const success = ref("");
const question = ref("");
const aiAnswer = ref(null);
const testingAI = ref(false);

let articlesRequestId = 0;
let aiRequestId = 0;

const panel = computed(() =>
    isDark.value ? "bg-[#111827] border-white/10" : "bg-white border-slate-200",
);

const muted = computed(() =>
    isDark.value ? "text-slate-400" : "text-slate-500",
);

const inputClass = computed(
    () =>
        `w-full rounded-xl border px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 ${
            isDark.value
                ? "border-white/10 bg-[#0b1220] text-white placeholder:text-slate-500"
                : "border-slate-200 bg-white text-slate-900 placeholder:text-slate-400"
        }`,
);

const secondaryButton = computed(
    () =>
        `inline-flex items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-medium transition disabled:opacity-50 ${
            isDark.value
                ? "border-white/10 bg-white/5 text-slate-200 hover:bg-white/10"
                : "border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
        }`,
);

const primaryButton =
    "inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50";

function normalizeList(data, key) {
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.[key])) return data[key];
    if (Array.isArray(data?.[key]?.data)) return data[key].data;
    if (Array.isArray(data?.data)) return data.data;
    if (Array.isArray(data?.data?.data)) return data.data.data;
    return [];
}

function getErrorMessage(err, fallback) {
    const errors = err?.response?.data?.errors;
    if (errors) {
        const first = Object.values(errors).flat().find(Boolean);
        if (first) return String(first);
    }
    return err?.response?.data?.message || err?.message || fallback;
}

function handleError(err, fallback) {
    if (err?.response?.status === 401) {
        localStorage.removeItem("accessToken");
        localStorage.removeItem("user");
        router.push("/login");
        return;
    }
    error.value = getErrorMessage(err, fallback);
}

function clearMessages() {
    error.value = "";
    success.value = "";
}

function getCategory(value) {
    return categories.find((c) => c.value === value) || categories[5];
}

function getStoreName(store) {
    return store?.store_name || store?.name || `Store #${store?.id}`;
}

function formatDate(value) {
    if (!value) return "—";
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? "—"
        : date.toLocaleDateString("en-US", {
              month: "short",
              day: "numeric",
              year: "numeric",
          });
}

const selectedStore = computed(() =>
    stores.value.find((s) => String(s.id) === String(selectedStoreId.value)),
);

const filteredArticles = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    return articles.value.filter((article) => {
        const matchesSearch =
            !keyword ||
            String(article.title || "")
                .toLowerCase()
                .includes(keyword) ||
            String(article.content || "")
                .toLowerCase()
                .includes(keyword);

        return (
            matchesSearch &&
            (categoryFilter.value === "all" ||
                article.category === categoryFilter.value) &&
            (statusFilter.value === "all" ||
                article.status === statusFilter.value)
        );
    });
});

const stats = computed(() => {
    const total = articles.value.length;
    const active = articles.value.filter((a) => a.status === "active").length;

    return {
        total,
        active,
        inactive: total - active,
        categories: new Set(articles.value.map((a) => a.category)).size,
    };
});

const canTestAI = computed(
    () =>
        !!selectedStoreId.value &&
        !testingAI.value &&
        !loading.value &&
        question.value.trim().length > 0 &&
        stats.value.active > 0,
);

// LOAD ALL USER STORES
async function fetchStores() {
    storesLoading.value = true;

    try {
        const { data } = await api.get("/stores");
        const list = normalizeList(data, "stores");
        stores.value = list;

        const exists = list.some(
            (s) => String(s.id) === String(selectedStoreId.value),
        );

        if (!exists) {
            selectedStoreId.value = list.length ? String(list[0].id) : "";
        }
    } catch (err) {
        handleError(err, "Unable to load stores.");
    } finally {
        storesLoading.value = false;
    }
}

// LOAD ARTICLES ONLY FOR SELECTED STORE
async function fetchArticles(storeId = selectedStoreId.value) {
    const requestId = ++articlesRequestId;

    if (!storeId) {
        articles.value = [];
        loading.value = false;
        return;
    }

    loading.value = true;

    try {
        const { data } = await api.get(
            `/ai/knowledge/${encodeURIComponent(storeId)}`,
        );

        if (
            requestId !== articlesRequestId ||
            String(storeId) !== String(selectedStoreId.value)
        )
            return;

        if (data?.success === false) {
            throw new Error(data.message || "Unable to load articles.");
        }

        articles.value = normalizeList(data, "articles");
    } catch (err) {
        if (requestId !== articlesRequestId) return;
        articles.value = [];
        handleError(err, "Unable to load Knowledge Base articles.");
    } finally {
        if (requestId === articlesRequestId) loading.value = false;
    }
}

async function refreshAll() {
    clearMessages();
    await fetchStores();
    await fetchArticles();
}

function onStoreChange() {
    clearMessages();
    aiAnswer.value = null;
    question.value = "";
    articles.value = [];
    search.value = "";
    categoryFilter.value = "all";
    statusFilter.value = "all";
    modalOpen.value = false;
    editingId.value = null;
    aiRequestId++;
    fetchArticles();
}

function openAddModal() {
    clearMessages();
    editingId.value = null;
    form.value = emptyForm();
    modalOpen.value = true;
}

function openEditModal(article) {
    clearMessages();
    editingId.value = article.id;
    form.value = {
        title: article.title || "",
        content: article.content || "",
        category: article.category || "faq",
        status: article.status || "active",
    };
    modalOpen.value = true;
}

function closeModal() {
    if (saving.value) return;
    modalOpen.value = false;
    editingId.value = null;
    form.value = emptyForm();
}

// CREATE AND UPDATE
async function saveArticle() {
    clearMessages();

    const storeId = selectedStoreId.value;
    const title = form.value.title.trim();
    const content = form.value.content.trim();

    if (!storeId) {
        error.value = "Please select a store.";
        return;
    }

    if (!title || !content) {
        error.value = "Title and content are required.";
        return;
    }

    if (title.length > 255) {
        error.value = "Title cannot exceed 255 characters.";
        return;
    }

    saving.value = true;

    const wasEditing = Boolean(editingId.value);

    try {
        const payload = {
            title,
            content,
            category: form.value.category,
            status: form.value.status,
        };

        const response = wasEditing
            ? await api.put(`/knowledge-base/${editingId.value}`, payload)
            : await api.post(
                  `/ai/knowledge/${encodeURIComponent(storeId)}`,
                  payload,
              );

        if (response.data?.success === false) {
            throw new Error(response.data.message || "Unable to save article.");
        }

        modalOpen.value = false;
        editingId.value = null;
        form.value = emptyForm();

        await fetchArticles(storeId);

        success.value = wasEditing
            ? "Knowledge Base article updated successfully."
            : "Knowledge Base article created successfully.";
    } catch (err) {
        handleError(err, "Unable to save article.");
    } finally {
        saving.value = false;
    }
}

// DELETE
async function deleteArticle(article) {
    if (!window.confirm(`Are you sure you want to delete "${article.title}"?`))
        return;

    clearMessages();
    deletingId.value = article.id;

    try {
        const { data } = await api.delete(`/knowledge-base/${article.id}`);

        if (data?.success === false) {
            throw new Error(data.message || "Unable to delete article.");
        }

        await fetchArticles();
        success.value = "Article deleted successfully.";
    } catch (err) {
        handleError(err, "Unable to delete article.");
    } finally {
        deletingId.value = null;
    }
}

// ACTIVE / INACTIVE
async function toggleStatus(article) {
    clearMessages();
    updatingStatusId.value = article.id;

    const newStatus = article.status === "active" ? "inactive" : "active";

    try {
        const { data } = await api.put(`/knowledge-base/${article.id}`, {
            title: article.title,
            content: article.content,
            category: article.category,
            status: newStatus,
        });

        if (data?.success === false) {
            throw new Error(data.message || "Unable to update status.");
        }

        await fetchArticles();
        success.value = `Article marked as ${newStatus}.`;
    } catch (err) {
        handleError(err, "Unable to update status.");
    } finally {
        updatingStatusId.value = null;
    }
}

// GEMINI AI PLAYGROUND
async function testKnowledgeBase() {
    clearMessages();

    const storeId = selectedStoreId.value;
    const customerQuestion = question.value.trim();

    if (!storeId) {
        error.value = "Please select a store first.";
        return;
    }

    if (!customerQuestion) {
        error.value = "Please enter a question.";
        return;
    }

    if (stats.value.active === 0) {
        error.value = "Please add at least one active article.";
        return;
    }

    const requestId = ++aiRequestId;
    testingAI.value = true;
    aiAnswer.value = null;

    try {
        const { data } = await api.post("/knowledge-base/test", {
            question: customerQuestion,
            store_id: Number(storeId),
        });

        if (
            requestId !== aiRequestId ||
            String(storeId) !== String(selectedStoreId.value)
        )
            return;

        if (data?.success === false) {
            throw new Error(data.message || "AI test failed.");
        }

        aiAnswer.value = {
            answer: data?.answer || data?.data?.answer || "No answer returned.",
            information_status:
                data?.information_status ||
                data?.data?.information_status ||
                "not_available",
            sources: data?.sources || data?.data?.sources || [],
        };
    } catch (err) {
        if (requestId === aiRequestId) {
            handleError(err, "Unable to test Knowledge Base.");
        }
    } finally {
        if (requestId === aiRequestId) testingAI.value = false;
    }
}

watch(question, () => {
    aiAnswer.value = null;
});

onMounted(refreshAll);
</script>

<template>
    <div
        class="min-h-screen space-y-6 p-4 md:p-6"
        :class="isDark ? 'text-white' : 'text-slate-900'"
    >
        <!-- HEADER -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600 text-white"
                >
                    <BookOpen :size="24" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold md:text-3xl">
                        Knowledge Base
                    </h1>
                    <p class="mt-1 text-sm" :class="muted">
                        Manage store policies, FAQs and AI support knowledge.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button
                    type="button"
                    :class="secondaryButton"
                    :disabled="loading || storesLoading"
                    @click="refreshAll"
                >
                    <RefreshCw
                        :size="17"
                        :class="loading ? 'animate-spin' : ''"
                    />
                    Refresh
                </button>

                <button
                    type="button"
                    :class="primaryButton"
                    :disabled="!selectedStoreId || loading"
                    @click="openAddModal"
                >
                    <Plus :size="18" />
                    Add Article
                </button>
            </div>
        </div>

        <!-- ALERTS -->
        <div
            v-if="error"
            class="flex items-center justify-between gap-3 rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-500"
        >
            <div class="flex items-center gap-2">
                <AlertCircle :size="18" />
                {{ error }}
            </div>
            <button @click="error = ''"><X :size="17" /></button>
        </div>

        <div
            v-if="success"
            class="flex items-center justify-between gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-500"
        >
            <div class="flex items-center gap-2">
                <CheckCircle2 :size="18" />
                {{ success }}
            </div>
            <button @click="success = ''"><X :size="17" /></button>
        </div>

        <!-- MULTI-STORE SELECTOR -->
        <div class="rounded-2xl border p-5" :class="panel">
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-500"
                    >
                        <Store :size="21" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold">Select Store</h2>
                        <p class="mt-1 text-sm" :class="muted">
                            Each store has its own Knowledge Base.
                        </p>
                    </div>
                </div>

                <div class="w-full md:w-72">
                    <select
                        v-model="selectedStoreId"
                        :class="inputClass"
                        :disabled="storesLoading || stores.length === 0"
                        @change="onStoreChange"
                    >
                        <option value="" disabled>Select a store</option>
                        <option
                            v-for="store in stores"
                            :key="store.id"
                            :value="String(store.id)"
                        >
                            {{ getStoreName(store) }}
                        </option>
                    </select>
                </div>
            </div>

            <p v-if="storesLoading" class="mt-3 text-sm" :class="muted">
                Loading stores...
            </p>

            <p
                v-else-if="stores.length === 0"
                class="mt-3 text-sm text-amber-500"
            >
                No stores found. Add a store first.
            </p>

            <p v-else class="mt-3 text-xs" :class="muted">
                Selected: {{ getStoreName(selectedStore) }}. Articles and AI
                answers are scoped to this store.
            </p>
        </div>

        <!-- STATISTICS -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="stat in [
                    {
                        label: 'All Articles',
                        value: stats.total,
                        icon: BookOpen,
                        color: 'text-indigo-500',
                    },
                    {
                        label: 'Published',
                        value: stats.active,
                        icon: CheckCircle2,
                        color: 'text-emerald-500',
                    },
                    {
                        label: 'Disabled',
                        value: stats.inactive,
                        icon: Power,
                        color: 'text-amber-500',
                    },
                    {
                        label: 'Categories',
                        value: stats.categories,
                        icon: FileText,
                        color: 'text-purple-500',
                    },
                ]"
                :key="stat.label"
                class="rounded-2xl border p-5"
                :class="panel"
            >
                <div class="flex items-center justify-between">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500/10"
                    >
                        <component
                            :is="stat.icon"
                            :size="21"
                            :class="stat.color"
                        />
                    </div>
                    <span class="text-xs" :class="muted">{{ stat.label }}</span>
                </div>
                <p class="mt-5 text-3xl font-bold">{{ stat.value }}</p>
                <p class="mt-1 text-sm" :class="muted">
                    {{ stat.label }} for selected store
                </p>
            </div>
        </div>

        <!-- ARTICLES -->
        <div class="overflow-hidden rounded-2xl border" :class="panel">
            <div
                class="border-b p-5"
                :class="isDark ? 'border-white/10' : 'border-slate-200'"
            >
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <h2 class="text-lg font-semibold">
                            Knowledge Articles
                        </h2>
                        <p class="mt-1 text-sm" :class="muted">
                            {{ filteredArticles.length }} articles found
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="relative">
                            <Search
                                :size="17"
                                class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search articles..."
                                :class="[inputClass, 'pl-10']"
                            />
                        </div>

                        <select v-model="categoryFilter" :class="inputClass">
                            <option value="all">All Categories</option>
                            <option
                                v-for="category in categories"
                                :key="category.value"
                                :value="category.value"
                            >
                                {{ category.label }}
                            </option>
                        </select>

                        <select v-model="statusFilter" :class="inputClass">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="flex flex-col items-center py-20">
                <Loader2 :size="34" class="animate-spin text-indigo-500" />
                <p class="mt-4 text-sm" :class="muted">
                    Loading Knowledge Base...
                </p>
            </div>

            <div
                v-else-if="filteredArticles.length === 0"
                class="flex flex-col items-center px-6 py-20 text-center"
            >
                <BookOpen :size="36" class="text-indigo-500" />
                <h3 class="mt-5 text-lg font-semibold">No articles found</h3>
                <p class="mt-2 text-sm" :class="muted">
                    Create an article for the selected store.
                </p>
                <button
                    v-if="selectedStoreId"
                    :class="[primaryButton, 'mt-5']"
                    @click="openAddModal"
                >
                    <Plus :size="17" />
                    Add First Article
                </button>
            </div>

            <div
                v-else
                class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-2 xl:grid-cols-3"
            >
                <div
                    v-for="article in filteredArticles"
                    :key="article.id"
                    class="flex flex-col rounded-2xl border p-5"
                    :class="
                        isDark
                            ? 'border-white/10 bg-[#0b1220]'
                            : 'border-slate-200 bg-slate-50/60'
                    "
                >
                    <div class="flex items-start justify-between">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-500"
                        >
                            <component
                                :is="getCategory(article.category).icon"
                                :size="21"
                            />
                        </div>

                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="
                                article.status === 'active'
                                    ? 'bg-emerald-500/10 text-emerald-500'
                                    : 'bg-amber-500/10 text-amber-500'
                            "
                        >
                            {{
                                article.status === "active"
                                    ? "Active"
                                    : "Inactive"
                            }}
                        </span>
                    </div>

                    <div class="mt-4 flex-1">
                        <p
                            class="text-xs font-semibold uppercase text-indigo-500"
                        >
                            {{ getCategory(article.category).label }}
                        </p>
                        <h3 class="mt-2 break-words text-base font-semibold">
                            {{ article.title }}
                        </h3>
                        <p
                            class="mt-3 line-clamp-4 whitespace-pre-line break-words text-sm leading-6"
                            :class="muted"
                        >
                            {{ article.content }}
                        </p>
                    </div>

                    <div
                        class="mt-5 border-t pt-4"
                        :class="isDark ? 'border-white/10' : 'border-slate-200'"
                    >
                        <div
                            class="mb-4 flex justify-between text-xs"
                            :class="muted"
                        >
                            <span
                                >Created
                                {{ formatDate(article.created_at) }}</span
                            >
                            <span>#{{ article.id }}</span>
                        </div>

                        <div class="flex flex-wrap justify-between gap-2">
                            <button
                                class="inline-flex items-center gap-1 text-xs font-medium text-indigo-500"
                                @click="openEditModal(article)"
                            >
                                <Pencil :size="15" /> Edit
                            </button>

                            <button
                                class="inline-flex items-center gap-1 text-xs font-medium text-amber-500 disabled:opacity-50"
                                :disabled="updatingStatusId === article.id"
                                @click="toggleStatus(article)"
                            >
                                <Power :size="15" />
                                {{
                                    article.status === "active"
                                        ? "Disable"
                                        : "Enable"
                                }}
                            </button>

                            <button
                                class="inline-flex items-center gap-1 text-xs font-medium text-red-500 disabled:opacity-50"
                                :disabled="deletingId === article.id"
                                @click="deleteArticle(article)"
                            >
                                <Trash2 :size="15" /> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI PLAYGROUND -->
        <div class="overflow-hidden rounded-2xl border" :class="panel">
            <div
                class="border-b p-5 md:p-6"
                :class="isDark ? 'border-white/10' : 'border-slate-200'"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-purple-500/10 text-purple-500"
                    >
                        <Sparkles :size="23" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold">
                            AI Knowledge Playground
                        </h2>
                        <p class="mt-1 text-sm" :class="muted">
                            Test AI answers using active articles from the
                            selected store.
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-5 p-5 md:p-6">
                <div>
                    <label class="mb-2 block text-sm font-semibold">
                        Ask a Question
                    </label>
                    <textarea
                        v-model="question"
                        rows="4"
                        maxlength="5000"
                        :class="inputClass"
                        placeholder="Example: What is your return policy?"
                    ></textarea>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-xs" :class="muted">
                        <ShieldCheck :size="17" class="text-emerald-500" />
                        Private test only. No customer message is sent.
                    </p>

                    <button
                        :class="primaryButton"
                        :disabled="!canTestAI"
                        @click="testKnowledgeBase"
                    >
                        <Loader2
                            v-if="testingAI"
                            :size="17"
                            class="animate-spin"
                        />
                        <Sparkles v-else :size="17" />
                        {{
                            testingAI
                                ? "Generating Answer..."
                                : "Test AI Knowledge"
                        }}
                    </button>
                </div>

                <div
                    v-if="stats.active === 0 && !loading"
                    class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-sm text-amber-500"
                >
                    Add at least one active article before testing AI.
                </div>

                <div
                    v-if="aiAnswer"
                    class="overflow-hidden rounded-2xl border"
                    :class="panel"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b p-5"
                        :class="isDark ? 'border-white/10' : 'border-slate-200'"
                    >
                        <h3 class="flex items-center gap-2 font-semibold">
                            <Sparkles :size="18" class="text-purple-500" />
                            AI Generated Answer
                        </h3>

                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="
                                aiAnswer.information_status === 'available'
                                    ? 'bg-emerald-500/10 text-emerald-500'
                                    : aiAnswer.information_status ===
                                        'partially_available'
                                      ? 'bg-amber-500/10 text-amber-500'
                                      : 'bg-red-500/10 text-red-500'
                            "
                        >
                            {{
                                aiAnswer.information_status.replaceAll("_", " ")
                            }}
                        </span>
                    </div>

                    <div class="space-y-5 p-5">
                        <p
                            class="whitespace-pre-wrap break-words text-sm leading-7"
                        >
                            {{ aiAnswer.answer }}
                        </p>

                        <div
                            v-if="aiAnswer.sources.length"
                            class="border-t pt-4"
                            :class="
                                isDark ? 'border-white/10' : 'border-slate-200'
                            "
                        >
                            <h4 class="mb-3 text-sm font-semibold">
                                Knowledge Sources
                            </h4>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="(source, index) in aiAnswer.sources"
                                    :key="source.id || index"
                                    class="rounded-lg bg-indigo-500/10 px-3 py-2 text-xs text-indigo-500"
                                >
                                    {{
                                        typeof source === "object"
                                            ? source.title ||
                                              `Article #${source.id}`
                                            : `Article #${source}`
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CREATE / EDIT MODAL -->
        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="closeModal"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border shadow-2xl"
                :class="panel"
            >
                <div
                    class="flex items-center justify-between border-b p-5"
                    :class="isDark ? 'border-white/10' : 'border-slate-200'"
                >
                    <div>
                        <h2 class="text-lg font-semibold">
                            {{
                                editingId
                                    ? "Edit Knowledge Article"
                                    : "Create Knowledge Article"
                            }}
                        </h2>
                        <p class="mt-1 text-sm" :class="muted">
                            Store: {{ getStoreName(selectedStore) }}
                        </p>
                    </div>
                    <button :disabled="saving" @click="closeModal">
                        <X :size="20" />
                    </button>
                </div>

                <form
                    class="flex min-h-0 flex-1 flex-col"
                    @submit.prevent="saveArticle"
                >
                    <div class="space-y-5 overflow-y-auto p-5">
                        <div>
                            <label class="mb-2 block text-sm font-semibold">
                                Article Title *
                            </label>
                            <input
                                v-model="form.title"
                                type="text"
                                maxlength="255"
                                required
                                :class="inputClass"
                                placeholder="Example: Return and Exchange Policy"
                            />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold">
                                Category *
                            </label>
                            <select
                                v-model="form.category"
                                required
                                :class="inputClass"
                            >
                                <option
                                    v-for="category in categories"
                                    :key="category.value"
                                    :value="category.value"
                                >
                                    {{ category.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold">
                                Article Content *
                            </label>
                            <textarea
                                v-model="form.content"
                                rows="9"
                                required
                                :class="inputClass"
                                placeholder="Write your store policy here..."
                            ></textarea>
                            <p class="mt-2 text-xs" :class="muted">
                                AI uses active articles to answer customer
                                questions.
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold"
                                >Status</label
                            >
                            <select v-model="form.status" :class="inputClass">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div
                        class="flex justify-end gap-3 border-t p-5"
                        :class="isDark ? 'border-white/10' : 'border-slate-200'"
                    >
                        <button
                            type="button"
                            :class="secondaryButton"
                            :disabled="saving"
                            @click="closeModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :class="primaryButton"
                            :disabled="saving"
                        >
                            <Loader2
                                v-if="saving"
                                :size="17"
                                class="animate-spin"
                            />
                            <Save v-else :size="17" />
                            {{
                                saving
                                    ? "Saving..."
                                    : editingId
                                      ? "Update Article"
                                      : "Create Article"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
