<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import {
    Users,
    UserPlus,
    Search,
    RefreshCw,
    Pencil,
    Trash2,
    X,
    ShieldCheck,
    Shield,
    CheckCircle2,
    Ban,
    LoaderCircle,
    AlertCircle,
    ChevronLeft,
    ChevronRight,
    Eye,
    EyeOff,
    Save,
} from "lucide-vue-next";

import { useTheme } from "../composables/useTheme";

const router = useRouter();
const { isDark } = useTheme();

// ==========================================
// STATE
// ==========================================

const users = ref([]);
const stats = ref({
    total_users: 0,
    total_owners: 0,
    total_super_admins: 0,
    total_active: 0,
    total_inactive: 0,
});

const loading = ref(true);
const refreshing = ref(false);
const error = ref("");
const success = ref("");

const search = ref("");
const roleFilter = ref("");
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
const editingUser = ref(null);
const showPassword = ref(false);
const saving = ref(false);
const formError = ref("");
const validationErrors = ref({});

const actionLoading = ref(null);
const deleteUser = ref(null);
const currentUserId = ref(null);

const initialForm = () => ({
    name: "",
    email: "",
    password: "",
    role: "owner",
});

const form = ref(initialForm());

let searchTimer = null;
let successTimer = null;
let requestId = 0;

// ==========================================
// THEME CLASSES
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

const formatRole = (role) => (role === "super_admin" ? "Super Admin" : "Owner");

const getErrorMessage = (result, fallback) => {
    if (result?.errors) {
        const firstError = Object.values(result.errors).flat()[0];
        if (firstError) return firstError;
    }

    return result?.message || fallback;
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

    let result = {};

    try {
        result = await response.json();
    } catch {
        throw new Error("Invalid server response.");
    }

    if (!response.ok || result.success === false) {
        const err = new Error(getErrorMessage(result, "Something went wrong."));

        err.status = response.status;
        err.errors = result.errors || {};
        throw err;
    }

    return result;
};

const showSuccess = (message) => {
    clearTimeout(successTimer);
    success.value = message;

    successTimer = setTimeout(() => {
        success.value = "";
    }, 4000);
};

const loadCurrentUser = () => {
    try {
        const saved = JSON.parse(localStorage.getItem("user") || "null");
        currentUserId.value = saved?.id ? String(saved.id) : null;
    } catch {
        currentUserId.value = null;
    }
};

const isSelf = (user) =>
    currentUserId.value !== null && currentUserId.value === String(user.id);

// ==========================================
// FETCH USERS
// ==========================================

const fetchUsers = async (isRefresh = false) => {
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

        if (roleFilter.value) {
            params.set("role", roleFilter.value);
        }

        if (statusFilter.value) {
            params.set("status", statusFilter.value);
        }

        const result = await apiRequest(
            `/api/super-admin/users?${params.toString()}`,
        );

        if (currentRequest !== requestId) return;

        users.value = result.data?.data || [];

        pagination.value = {
            current_page: result.data?.current_page || 1,
            last_page: result.data?.last_page || 1,
            total: result.data?.total || 0,
            from: result.data?.from ?? null,
            to: result.data?.to ?? null,
        };

        stats.value = {
            total_users: Number(result.stats?.total_users) || 0,
            total_owners: Number(result.stats?.total_owners) || 0,
            total_super_admins: Number(result.stats?.total_super_admins) || 0,
            total_active: Number(result.stats?.total_active) || 0,
            total_inactive: Number(result.stats?.total_inactive) || 0,
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
// FILTERS
// ==========================================

watch(search, () => {
    clearTimeout(searchTimer);

    searchTimer = setTimeout(() => {
        page.value = 1;
        fetchUsers();
    }, 350);
});

watch([roleFilter, statusFilter], () => {
    clearTimeout(searchTimer);
    page.value = 1;
    fetchUsers();
});

const changePage = (newPage) => {
    if (newPage < 1 || newPage > pagination.value.last_page || loading.value) {
        return;
    }

    page.value = newPage;
    fetchUsers();
};

// ==========================================
// ADD / EDIT MODAL
// ==========================================

const openAddModal = () => {
    editingUser.value = null;
    form.value = initialForm();
    formError.value = "";
    validationErrors.value = {};
    showPassword.value = false;
    showModal.value = true;
};

const openEditModal = (user) => {
    editingUser.value = user;

    form.value = {
        name: user.name || "",
        email: user.email || "",
        password: "",
        role: user.role || "owner",
    };

    formError.value = "";
    validationErrors.value = {};
    showPassword.value = false;
    showModal.value = true;
};

const closeModal = () => {
    if (saving.value) return;

    showModal.value = false;
    editingUser.value = null;
    form.value = initialForm();
    formError.value = "";
    validationErrors.value = {};
};

// ==========================================
// CREATE / UPDATE USER
// ==========================================

const handleSubmit = async () => {
    formError.value = "";
    validationErrors.value = {};

    if (!form.value.name.trim()) {
        formError.value = "Name is required.";
        return;
    }

    if (!form.value.email.trim()) {
        formError.value = "Email is required.";
        return;
    }

    if (!editingUser.value && form.value.password.length < 8) {
        formError.value = "Password must be at least 8 characters.";
        return;
    }

    if (
        editingUser.value &&
        form.value.password &&
        form.value.password.length < 8
    ) {
        formError.value = "Password must be at least 8 characters.";
        return;
    }

    saving.value = true;

    try {
        const payload = {
            name: form.value.name.trim(),
            email: form.value.email.trim(),
            role: form.value.role,
        };

        if (form.value.password) {
            payload.password = form.value.password;
        }

        const isEditing = Boolean(editingUser.value);

        const url = isEditing
            ? `/api/super-admin/users/${editingUser.value.id}`
            : "/api/super-admin/users";

        await apiRequest(url, {
            method: isEditing ? "PUT" : "POST",
            body: JSON.stringify(payload),
        });

        closeModalAfterSave();

        showSuccess(
            isEditing
                ? "User updated successfully."
                : "User created successfully.",
        );

        page.value = 1;
        await fetchUsers(true);
    } catch (err) {
        if (handleAuthError(err.status)) return;

        formError.value = err.message;
        validationErrors.value = err.errors || {};
    } finally {
        saving.value = false;
    }
};

const closeModalAfterSave = () => {
    showModal.value = false;
    editingUser.value = null;
    form.value = initialForm();
    showPassword.value = false;
};

// ==========================================
// ACTIVATE / DEACTIVATE
// ==========================================

const handleStatusChange = async (user) => {
    const newStatus = user.status === "active" ? "inactive" : "active";

    if (isSelf(user) && newStatus === "inactive") {
        error.value = "You cannot deactivate your own account.";
        return;
    }

    actionLoading.value = user.id;
    error.value = "";

    try {
        await apiRequest(`/api/super-admin/users/${user.id}/status`, {
            method: "PATCH",
            body: JSON.stringify({
                status: newStatus,
            }),
        });

        showSuccess(
            newStatus === "active"
                ? "User activated successfully."
                : "User deactivated successfully.",
        );

        await fetchUsers(true);
    } catch (err) {
        if (!handleAuthError(err.status)) {
            error.value = err.message;
        }
    } finally {
        actionLoading.value = null;
    }
};

// ==========================================
// DELETE USER
// ==========================================

const openDeleteModal = (user) => {
    if (isSelf(user)) {
        error.value = "You cannot delete your own account.";
        return;
    }

    deleteUser.value = user;
};

const handleDelete = async () => {
    if (!deleteUser.value) return;

    const user = deleteUser.value;

    if (isSelf(user)) {
        error.value = "You cannot delete your own account.";
        deleteUser.value = null;
        return;
    }

    actionLoading.value = user.id;
    error.value = "";

    try {
        await apiRequest(`/api/super-admin/users/${user.id}`, {
            method: "DELETE",
        });

        deleteUser.value = null;
        showSuccess("User deleted successfully.");

        if (users.value.length === 1 && page.value > 1) {
            page.value -= 1;
        }

        await fetchUsers(true);
    } catch (err) {
        deleteUser.value = null;

        if (!handleAuthError(err.status)) {
            error.value = err.message;
        }
    } finally {
        actionLoading.value = null;
    }
};

// ==========================================
// STATS
// ==========================================

const summaryCards = computed(() => [
    {
        title: "Total Users",
        value: stats.value.total_users,
        icon: Users,
        color: "text-cyan-500",
        bg: "bg-cyan-500/10",
    },
    {
        title: "Active Users",
        value: stats.value.total_active,
        icon: CheckCircle2,
        color: "text-emerald-500",
        bg: "bg-emerald-500/10",
    },
    {
        title: "Inactive Users",
        value: stats.value.total_inactive,
        icon: Ban,
        color: "text-red-500",
        bg: "bg-red-500/10",
    },
]);

// ==========================================
// LIFECYCLE
// ==========================================

onMounted(() => {
    loadCurrentUser();
    fetchUsers();
});

onUnmounted(() => {
    clearTimeout(searchTimer);
    clearTimeout(successTimer);
    requestId++;
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
                    <span>Users Management</span>
                </div>

                <h1
                    class="flex items-center gap-3 text-2xl font-bold sm:text-3xl"
                >
                    <Users :size="30" class="text-cyan-500" />
                    Users Management
                </h1>

                <p class="mt-2 text-sm" :class="mutedClass">
                    Manage platform users, roles and account access.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    :disabled="refreshing"
                    @click="fetchUsers(true)"
                    class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition disabled:opacity-50"
                    :class="cardClass"
                >
                    <RefreshCw
                        :size="17"
                        :class="{ 'animate-spin': refreshing }"
                    />
                    Refresh
                </button>

                <button
                    type="button"
                    @click="openAddModal"
                    class="inline-flex items-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400"
                >
                    <UserPlus :size="18" />
                    Add User
                </button>
            </div>
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

        <!-- STATISTICS -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
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
                        placeholder="Search name, email or ID..."
                        class="w-full rounded-xl border py-3 pl-10 pr-4 text-sm outline-none focus:border-cyan-500"
                        :class="inputClass"
                    />
                </div>

                <select
                    v-model="roleFilter"
                    class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                    :class="inputClass"
                >
                    <option value="">All Roles</option>
                    <option value="owner">Merchant / Owner</option>
                    <option value="super_admin">Super Admin</option>
                </select>

                <select
                    v-model="statusFilter"
                    class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                    :class="inputClass"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <!-- USERS TABLE -->
        <div class="overflow-hidden rounded-2xl border" :class="cardClass">
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b p-5"
                :class="borderClass"
            >
                <div>
                    <h3 class="text-lg font-bold">Registered Users</h3>
                    <p class="mt-1 text-sm" :class="mutedClass">
                        View and manage registered accounts.
                    </p>
                </div>

                <span
                    class="rounded-full border px-3 py-1 text-xs"
                    :class="[borderClass, mutedClass]"
                >
                    {{ pagination.total }} Users
                </span>
            </div>

            <!-- LOADING -->
            <div
                v-if="loading"
                class="flex items-center justify-center gap-3 py-20"
                :class="mutedClass"
            >
                <LoaderCircle :size="24" class="animate-spin text-cyan-500" />
                Loading users...
            </div>

            <!-- EMPTY -->
            <div v-else-if="users.length === 0" class="py-20 text-center">
                <Users :size="45" class="mx-auto mb-4 opacity-50" />

                <p class="font-medium">No users found</p>

                <p class="mt-2 text-sm" :class="mutedClass">
                    Try changing your search or filters.
                </p>
            </div>

            <!-- TABLE -->
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left">
                    <thead class="border-b" :class="borderClass">
                        <tr
                            class="text-xs uppercase tracking-wider"
                            :class="mutedClass"
                        >
                            <th class="px-5 py-4 font-semibold">User</th>
                            <th class="px-5 py-4 font-semibold">Role</th>
                            <th class="px-5 py-4 font-semibold">Status</th>
                            <th class="px-5 py-4 font-semibold">Joined</th>
                            <th class="px-5 py-4 text-right font-semibold">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="border-b transition last:border-b-0 hover:bg-cyan-500/5"
                            :class="borderClass"
                        >
                            <!-- USER -->
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan-500/10 font-bold text-cyan-500"
                                    >
                                        {{
                                            user.name
                                                ?.charAt(0)
                                                ?.toUpperCase() || "U"
                                        }}
                                    </div>

                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-semibold">
                                                {{ user.name }}
                                            </p>

                                            <span
                                                v-if="isSelf(user)"
                                                class="rounded bg-cyan-500/10 px-2 py-0.5 text-[10px] font-semibold text-cyan-500"
                                            >
                                                YOU
                                            </span>
                                        </div>

                                        <p
                                            class="mt-1 text-xs"
                                            :class="mutedClass"
                                        >
                                            {{ user.email }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs"
                                            :class="mutedClass"
                                        >
                                            ID: #{{ user.id }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- ROLE -->
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold"
                                    :class="
                                        user.role === 'super_admin'
                                            ? 'bg-purple-500/10 text-purple-500'
                                            : 'bg-cyan-500/10 text-cyan-500'
                                    "
                                >
                                    <ShieldCheck
                                        v-if="user.role === 'super_admin'"
                                        :size="14"
                                    />

                                    <Shield v-else :size="14" />

                                    {{ formatRole(user.role) }}
                                </span>
                            </td>

                            <!-- STATUS -->
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold"
                                    :class="
                                        user.status === 'active'
                                            ? 'bg-emerald-500/10 text-emerald-500'
                                            : 'bg-red-500/10 text-red-500'
                                    "
                                >
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :class="
                                            user.status === 'active'
                                                ? 'bg-emerald-500'
                                                : 'bg-red-500'
                                        "
                                    />

                                    {{
                                        user.status === "active"
                                            ? "Active"
                                            : "Inactive"
                                    }}
                                </span>
                            </td>

                            <!-- DATE -->
                            <td class="px-5 py-4 text-sm" :class="mutedClass">
                                {{ formatDate(user.created_at) }}
                            </td>

                            <!-- ACTIONS -->
                            <td class="px-5 py-4">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <!-- EDIT -->
                                    <button
                                        type="button"
                                        title="Edit User"
                                        class="rounded-lg p-2 text-cyan-500 transition hover:bg-cyan-500/10"
                                        @click="openEditModal(user)"
                                    >
                                        <Pencil :size="17" />
                                    </button>

                                    <!-- STATUS -->
                                    <button
                                        type="button"
                                        :title="
                                            user.status === 'active'
                                                ? 'Deactivate User'
                                                : 'Activate User'
                                        "
                                        :disabled="
                                            actionLoading === user.id ||
                                            (isSelf(user) &&
                                                user.status === 'active')
                                        "
                                        class="rounded-lg p-2 transition disabled:cursor-not-allowed disabled:opacity-40"
                                        :class="
                                            user.status === 'active'
                                                ? 'text-orange-500 hover:bg-orange-500/10'
                                                : 'text-emerald-500 hover:bg-emerald-500/10'
                                        "
                                        @click="handleStatusChange(user)"
                                    >
                                        <LoaderCircle
                                            v-if="actionLoading === user.id"
                                            :size="17"
                                            class="animate-spin"
                                        />

                                        <Ban
                                            v-else-if="user.status === 'active'"
                                            :size="17"
                                        />

                                        <CheckCircle2 v-else :size="17" />
                                    </button>

                                    <!-- DELETE -->
                                    <button
                                        type="button"
                                        title="Delete User"
                                        :disabled="isSelf(user)"
                                        class="rounded-lg p-2 text-red-500 transition hover:bg-red-500/10 disabled:cursor-not-allowed disabled:opacity-40"
                                        @click="openDeleteModal(user)"
                                    >
                                        <Trash2 :size="17" />
                                    </button>
                                </div>
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
                    • {{ pagination.total }} total users
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

        <!-- ADD / EDIT MODAL -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4"
            @click.self="closeModal"
        >
            <div
                role="dialog"
                aria-modal="true"
                :aria-label="editingUser ? 'Edit User' : 'Add New User'"
                class="w-full max-w-lg overflow-hidden rounded-2xl border shadow-2xl"
                :class="cardClass"
            >
                <!-- MODAL HEADER -->
                <div
                    class="flex items-center justify-between border-b p-5"
                    :class="borderClass"
                >
                    <div>
                        <h2 class="text-xl font-bold">
                            {{ editingUser ? "Edit User" : "Add New User" }}
                        </h2>

                        <p class="mt-1 text-sm" :class="mutedClass">
                            {{
                                editingUser
                                    ? "Update account information."
                                    : "Create a new platform account."
                            }}
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

                <!-- FORM -->
                <form @submit.prevent="handleSubmit">
                    <div class="max-h-[65vh] space-y-4 overflow-y-auto p-5">
                        <div
                            v-if="formError"
                            class="flex items-center gap-2 rounded-lg bg-red-500/10 p-3 text-sm text-red-500"
                        >
                            <AlertCircle :size="18" />
                            {{ formError }}
                        </div>

                        <!-- NAME -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Full Name
                            </label>

                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Enter full name"
                                required
                                class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                                :class="inputClass"
                            />

                            <p
                                v-if="validationErrors.name"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ validationErrors.name[0] }}
                            </p>
                        </div>

                        <!-- EMAIL -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Email Address
                            </label>

                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="Enter email address"
                                required
                                class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                                :class="inputClass"
                            />

                            <p
                                v-if="validationErrors.email"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ validationErrors.email[0] }}
                            </p>
                        </div>

                        <!-- PASSWORD -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Password

                                <span
                                    v-if="editingUser"
                                    class="ml-2 text-xs font-normal"
                                    :class="mutedClass"
                                >
                                    (Leave blank to keep current)
                                </span>
                            </label>

                            <div class="relative">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    :placeholder="
                                        editingUser
                                            ? 'Optional new password'
                                            : 'Minimum 8 characters'
                                    "
                                    :required="!editingUser"
                                    minlength="8"
                                    class="w-full rounded-xl border px-4 py-3 pr-12 text-sm outline-none focus:border-cyan-500"
                                    :class="inputClass"
                                />

                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2"
                                    :class="mutedClass"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" :size="19" />
                                    <Eye v-else :size="19" />
                                </button>
                            </div>

                            <p
                                v-if="validationErrors.password"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ validationErrors.password[0] }}
                            </p>
                        </div>

                        <!-- ROLE -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                User Role
                            </label>

                            <select
                                v-model="form.role"
                                :disabled="editingUser && isSelf(editingUser)"
                                class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500 disabled:opacity-50"
                                :class="inputClass"
                            >
                                <option value="owner">Merchant / Owner</option>

                                <option value="super_admin">Super Admin</option>
                            </select>

                            <p
                                v-if="editingUser && isSelf(editingUser)"
                                class="mt-2 text-xs"
                                :class="mutedClass"
                            >
                                You cannot change your own role.
                            </p>
                        </div>
                    </div>

                    <!-- FOOTER -->
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

                            {{
                                saving
                                    ? "Saving..."
                                    : editingUser
                                      ? "Update User"
                                      : "Create User"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- DELETE CONFIRMATION MODAL -->
        <div
            v-if="deleteUser"
            class="fixed inset-0 z-[110] flex items-center justify-center bg-black/70 p-4"
        >
            <div
                role="alertdialog"
                aria-modal="true"
                aria-label="Delete User Confirmation"
                class="w-full max-w-md rounded-2xl border p-6 shadow-2xl"
                :class="cardClass"
            >
                <div
                    class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-red-500/10"
                >
                    <Trash2 :size="26" class="text-red-500" />
                </div>

                <h2 class="text-xl font-bold">Delete User?</h2>

                <p class="mt-3 text-sm leading-6" :class="mutedClass">
                    Are you sure you want to permanently delete
                    <strong>{{ deleteUser.name }}</strong
                    >?
                </p>

                <p class="mt-2 text-sm text-red-500">
                    This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        :disabled="actionLoading === deleteUser.id"
                        @click="deleteUser = null"
                        class="rounded-xl border px-4 py-2.5 text-sm font-semibold"
                        :class="cardClass"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        :disabled="actionLoading === deleteUser.id"
                        @click="handleDelete"
                        class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-50"
                    >
                        <LoaderCircle
                            v-if="actionLoading === deleteUser.id"
                            :size="17"
                            class="animate-spin"
                        />

                        <Trash2 v-else :size="17" />

                        Delete User
                    </button>
                </div>
            </div>
        </div>
    </main>
</template>
