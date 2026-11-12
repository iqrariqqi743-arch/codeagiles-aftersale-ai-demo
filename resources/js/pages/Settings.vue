<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { RouterLink, useRouter } from "vue-router";
import { useTheme } from "../composables/useTheme";
import {
    ArrowLeft,
    UserRound,
    Mail,
    ShieldCheck,
    CalendarDays,
    LockKeyhole,
    Eye,
    EyeOff,
    Save,
    RefreshCw,
    CheckCircle2,
    AlertCircle,
    KeyRound,
    UserCircle2,
} from "lucide-vue-next";

// ==========================================
// ROUTER & THEME
// ==========================================

const router = useRouter();
const { isDark } = useTheme();

// ==========================================
// STATES
// ==========================================

const user = ref(null);

const profile = reactive({
    name: "",
    email: "",
});

const password = reactive({
    currentPassword: "",
    newPassword: "",
    confirmPassword: "",
});

const loading = ref(true);
const savingProfile = ref(false);
const savingPassword = ref(false);

const pageError = ref("");
const profileMessage = ref(null);
const passwordMessage = ref(null);

const showPasswords = reactive({
    currentPassword: false,
    newPassword: false,
    confirmPassword: false,
});

// ==========================================
// THEME CLASSES
// ==========================================

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
        ? "bg-[#101a2b] border-white/10 text-white placeholder:text-slate-500 focus:border-cyan-500"
        : "bg-slate-50 border-slate-200 text-slate-900 placeholder:text-slate-400 focus:border-cyan-500",
);

const secondaryClass = computed(() =>
    isDark.value
        ? "border-white/10 text-slate-300 hover:bg-white/5"
        : "border-slate-200 text-slate-700 hover:bg-slate-50",
);

const labelClass = computed(
    () => `mb-2 block text-sm font-medium ${titleClass.value}`,
);

const inputBase =
    "w-full rounded-xl border px-4 py-3 text-sm outline-none transition focus:ring-2 focus:ring-cyan-500/10";

// ==========================================
// HELPERS
// ==========================================

const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const getInitials = (name = "") => {
    const parts = String(name).trim().split(/\s+/).filter(Boolean);

    return (
        parts
            .slice(0, 2)
            .map((part) => part.charAt(0))
            .join("")
            .toUpperCase() || "U"
    );
};

const formatDate = (date) => {
    if (!date) return "Not available";

    const parsed = new Date(date);

    if (Number.isNaN(parsed.getTime())) {
        return "Not available";
    }

    return parsed.toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
};

const getErrorMessage = (error, fallback) => {
    return error?.message || fallback;
};

// ==========================================
// AUTH HANDLING
// ==========================================

const redirectToLogin = () => {
    localStorage.removeItem("accessToken");
    localStorage.removeItem("user");

    router.replace("/login");
};

// ==========================================
// API REQUEST
// ==========================================

async function apiRequest(path, options = {}) {
    const token = localStorage.getItem("accessToken");

    if (!token) {
        redirectToLogin();
        throw new Error("Please log in again.");
    }

    let response;

    try {
        response = await fetch(`/api${path}`, {
            ...options,
            headers: {
                Accept: "application/json",
                Authorization: `Bearer ${token}`,
                ...(options.body ? { "Content-Type": "application/json" } : {}),
                ...(options.headers || {}),
            },
        });
    } catch (error) {
        throw new Error("Network error. Please check your connection.");
    }

    // Only redirect for expired authentication on profile requests.
    // A wrong current password must not log the user out.
    if (response.status === 401 && path !== "/settings/change-password") {
        redirectToLogin();
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
            ? Object.values(data.errors).flat()[0]
            : null;

        throw new Error(
            validationMessage ||
                data?.message ||
                `Request failed (HTTP ${response.status})`,
        );
    }

    return data;
}

// ==========================================
// FETCH PROFILE
// ==========================================

const fetchProfile = async () => {
    try {
        loading.value = true;
        pageError.value = "";

        const response = await apiRequest("/settings/profile");

        if (!response?.user) {
            throw new Error("User information not found.");
        }

        user.value = response.user;

        profile.name = response.user.name || "";
        profile.email = response.user.email || "";

        localStorage.setItem("user", JSON.stringify(response.user));
    } catch (error) {
        console.error("Profile fetch error:", error);

        pageError.value = getErrorMessage(error, "Failed to load profile.");
    } finally {
        loading.value = false;
    }
};

// ==========================================
// UPDATE PROFILE
// ==========================================

const handleUpdateProfile = async () => {
    profileMessage.value = null;

    const name = profile.name.trim();
    const email = profile.email.trim().toLowerCase();

    if (!name) {
        profileMessage.value = {
            type: "error",
            text: "Please enter your name.",
        };
        return;
    }

    if (!emailRegex.test(email)) {
        profileMessage.value = {
            type: "error",
            text: "Please enter a valid email address.",
        };
        return;
    }

    try {
        savingProfile.value = true;

        const response = await apiRequest("/settings/profile", {
            method: "PUT",
            body: JSON.stringify({
                name,
                email,
            }),
        });

        const updatedUser = response.user;

        if (!updatedUser) {
            throw new Error("Updated user information not found.");
        }

        user.value = updatedUser;

        profile.name = updatedUser.name || "";
        profile.email = updatedUser.email || "";

        localStorage.setItem("user", JSON.stringify(updatedUser));

        profileMessage.value = {
            type: "success",
            text: response.message || "Profile updated successfully.",
        };
    } catch (error) {
        console.error("Profile update error:", error);

        profileMessage.value = {
            type: "error",
            text: getErrorMessage(error, "Failed to update profile."),
        };
    } finally {
        savingProfile.value = false;
    }
};

// ==========================================
// CHANGE PASSWORD
// ==========================================

const handleChangePassword = async () => {
    passwordMessage.value = null;

    const currentPassword = password.currentPassword;
    const newPassword = password.newPassword;
    const confirmPassword = password.confirmPassword;

    if (!currentPassword) {
        passwordMessage.value = {
            type: "error",
            text: "Please enter your current password.",
        };
        return;
    }

    if (newPassword.length < 8) {
        passwordMessage.value = {
            type: "error",
            text: "New password must contain at least 8 characters.",
        };
        return;
    }

    if (currentPassword === newPassword) {
        passwordMessage.value = {
            type: "error",
            text: "New password must be different from your current password.",
        };
        return;
    }

    if (newPassword !== confirmPassword) {
        passwordMessage.value = {
            type: "error",
            text: "New password and confirm password do not match.",
        };
        return;
    }

    try {
        savingPassword.value = true;

        const response = await apiRequest("/settings/change-password", {
            method: "PUT",
            body: JSON.stringify({
                currentPassword,
                newPassword,
            }),
        });

        password.currentPassword = "";
        password.newPassword = "";
        password.confirmPassword = "";

        showPasswords.currentPassword = false;
        showPasswords.newPassword = false;
        showPasswords.confirmPassword = false;

        passwordMessage.value = {
            type: "success",
            text: response.message || "Password changed successfully.",
        };
    } catch (error) {
        console.error("Change password error:", error);

        passwordMessage.value = {
            type: "error",
            text: getErrorMessage(error, "Failed to change password."),
        };
    } finally {
        savingPassword.value = false;
    }
};

// ==========================================
// PASSWORD VISIBILITY
// ==========================================

const togglePassword = (field) => {
    showPasswords[field] = !showPasswords[field];
};

const passwordFields = [
    {
        key: "currentPassword",
        label: "Current Password",
        placeholder: "Enter current password",
        autocomplete: "current-password",
    },
    {
        key: "newPassword",
        label: "New Password",
        placeholder: "Enter new password",
        autocomplete: "new-password",
    },
    {
        key: "confirmPassword",
        label: "Confirm New Password",
        placeholder: "Confirm new password",
        autocomplete: "new-password",
    },
];

// ==========================================
// LIFECYCLE
// ==========================================

onMounted(() => {
    fetchProfile();
});
</script>

<template>
    <div class="mx-auto max-w-7xl space-y-7 pb-10">
        <!-- BACK -->
        <RouterLink
            to="/dashboard"
            class="inline-flex items-center gap-2 text-sm transition hover:text-cyan-500"
            :class="mutedClass"
        >
            <ArrowLeft :size="18" />
            Back to dashboard
        </RouterLink>

        <!-- HEADER -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <p
                    class="mb-2 text-xs font-semibold tracking-widest text-cyan-500"
                >
                    ACCOUNT MANAGEMENT
                </p>

                <h1 class="text-3xl font-bold" :class="titleClass">
                    Profile Settings
                </h1>

                <p class="mt-2 text-sm" :class="mutedClass">
                    Manage your personal information and account security.
                </p>
            </div>

            <button
                type="button"
                @click="fetchProfile"
                :disabled="loading"
                class="inline-flex items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm transition disabled:opacity-50"
                :class="secondaryClass"
            >
                <RefreshCw :size="17" :class="{ 'animate-spin': loading }" />
                Refresh Profile
            </button>
        </div>

        <!-- LOADING -->
        <div
            v-if="loading && !user"
            class="flex min-h-[50vh] items-center justify-center"
        >
            <div class="text-center">
                <RefreshCw
                    :size="36"
                    class="mx-auto animate-spin text-cyan-500"
                />

                <p class="mt-4 text-sm" :class="mutedClass">
                    Loading your profile...
                </p>
            </div>
        </div>

        <!-- ERROR -->
        <div
            v-else-if="pageError && !user"
            class="flex min-h-[50vh] items-center justify-center px-4"
        >
            <div
                class="w-full max-w-md rounded-2xl border p-8 text-center"
                :class="cardClass"
            >
                <AlertCircle :size="42" class="mx-auto text-red-500" />

                <h2 class="mt-4 text-lg font-semibold" :class="titleClass">
                    Profile unavailable
                </h2>

                <p class="mt-2 text-sm" :class="mutedClass">
                    {{ pageError }}
                </p>

                <button
                    type="button"
                    @click="fetchProfile"
                    class="mt-6 rounded-xl bg-cyan-500 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-400"
                >
                    Try Again
                </button>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <template v-else-if="user">
            <!-- PROFILE BANNER -->
            <div class="overflow-hidden rounded-2xl border" :class="cardClass">
                <div
                    class="h-32 bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-700"
                ></div>

                <div class="px-6 pb-7 sm:px-8">
                    <div
                        class="-mt-12 flex flex-col gap-5 sm:flex-row sm:items-end"
                    >
                        <!-- AVATAR -->
                        <div
                            class="flex h-24 w-24 shrink-0 items-center justify-center rounded-2xl border-4 bg-gradient-to-br from-cyan-400 to-blue-600 text-3xl font-bold text-white shadow-lg"
                            :class="
                                isDark ? 'border-[#172235]' : 'border-white'
                            "
                        >
                            {{ getInitials(user.name) }}
                        </div>

                        <!-- USER DETAILS -->
                        <div class="flex-1">
                            <h2 class="text-2xl font-bold" :class="titleClass">
                                {{ user.name || "User" }}
                            </h2>

                            <p class="mt-1 text-sm" :class="mutedClass">
                                {{ user.email }}
                            </p>
                        </div>

                        <!-- ROLE -->
                        <span
                            class="inline-flex w-fit items-center gap-2 rounded-full border border-cyan-500/20 bg-cyan-500/10 px-4 py-2 text-xs font-semibold capitalize text-cyan-500"
                        >
                            <ShieldCheck :size="15" />
                            {{ user.role || "User" }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- ACCOUNT INFORMATION -->
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <!-- ACCOUNT NAME -->
                <div class="rounded-2xl border p-5" :class="cardClass">
                    <div
                        class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-500"
                    >
                        <UserCircle2 :size="22" />
                    </div>

                    <p class="text-sm" :class="mutedClass">Account Name</p>

                    <h3 class="mt-2 font-semibold" :class="titleClass">
                        {{ user.name }}
                    </h3>
                </div>

                <!-- EMAIL -->
                <div class="rounded-2xl border p-5" :class="cardClass">
                    <div
                        class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500"
                    >
                        <Mail :size="22" />
                    </div>

                    <p class="text-sm" :class="mutedClass">Email Address</p>

                    <h3
                        class="mt-2 break-all font-semibold"
                        :class="titleClass"
                    >
                        {{ user.email }}
                    </h3>
                </div>

                <!-- MEMBER SINCE -->
                <div class="rounded-2xl border p-5" :class="cardClass">
                    <div
                        class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500"
                    >
                        <CalendarDays :size="22" />
                    </div>

                    <p class="text-sm" :class="mutedClass">Member Since</p>

                    <h3 class="mt-2 font-semibold" :class="titleClass">
                        {{ formatDate(user.created_at) }}
                    </h3>
                </div>
            </div>

            <!-- SETTINGS FORMS -->
            <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-2">
                <!-- UPDATE PROFILE -->
                <section class="rounded-2xl border p-6" :class="cardClass">
                    <div class="mb-6 flex items-start gap-3">
                        <div
                            class="rounded-xl bg-cyan-500/10 p-3 text-cyan-500"
                        >
                            <UserRound :size="22" />
                        </div>

                        <div>
                            <h2
                                class="text-lg font-semibold"
                                :class="titleClass"
                            >
                                Personal Information
                            </h2>

                            <p class="mt-1 text-sm" :class="mutedClass">
                                Update your name and email address.
                            </p>
                        </div>
                    </div>

                    <form
                        @submit.prevent="handleUpdateProfile"
                        class="space-y-5"
                    >
                        <!-- NAME -->
                        <div>
                            <label for="profile-name" :class="labelClass">
                                Full Name
                            </label>

                            <div class="relative">
                                <UserRound
                                    :size="18"
                                    class="absolute left-4 top-1/2 -translate-y-1/2"
                                    :class="mutedClass"
                                />

                                <input
                                    id="profile-name"
                                    v-model="profile.name"
                                    type="text"
                                    required
                                    autocomplete="name"
                                    placeholder="Enter your full name"
                                    :class="[inputBase, inputClass]"
                                    class="pl-11"
                                />
                            </div>
                        </div>

                        <!-- EMAIL -->
                        <div>
                            <label for="profile-email" :class="labelClass">
                                Email Address
                            </label>

                            <div class="relative">
                                <Mail
                                    :size="18"
                                    class="absolute left-4 top-1/2 -translate-y-1/2"
                                    :class="mutedClass"
                                />

                                <input
                                    id="profile-email"
                                    v-model="profile.email"
                                    type="email"
                                    required
                                    autocomplete="email"
                                    placeholder="Enter your email"
                                    :class="[inputBase, inputClass]"
                                    class="pl-11"
                                />
                            </div>
                        </div>

                        <!-- ROLE -->
                        <div>
                            <label :class="labelClass"> Account Role </label>

                            <div
                                class="flex items-center gap-3 rounded-xl border px-4 py-3"
                                :class="
                                    isDark
                                        ? 'border-white/10 bg-white/5'
                                        : 'border-slate-200 bg-slate-50'
                                "
                            >
                                <ShieldCheck :size="18" class="text-cyan-500" />

                                <span
                                    class="text-sm capitalize"
                                    :class="titleClass"
                                >
                                    {{ user.role || "User" }}
                                </span>

                                <span
                                    class="ml-auto text-xs"
                                    :class="mutedClass"
                                >
                                    Read only
                                </span>
                            </div>
                        </div>

                        <!-- PROFILE MESSAGE -->
                        <div
                            v-if="profileMessage"
                            role="alert"
                            class="flex items-start gap-3 rounded-xl border p-4 text-sm"
                            :class="
                                profileMessage.type === 'success'
                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-500'
                                    : 'border-red-500/20 bg-red-500/10 text-red-500'
                            "
                        >
                            <CheckCircle2
                                v-if="profileMessage.type === 'success'"
                                :size="19"
                                class="shrink-0"
                            />

                            <AlertCircle v-else :size="19" class="shrink-0" />

                            <span>{{ profileMessage.text }}</span>
                        </div>

                        <!-- SAVE PROFILE -->
                        <button
                            type="submit"
                            :disabled="savingProfile"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-500 px-5 py-3.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <RefreshCw
                                v-if="savingProfile"
                                :size="18"
                                class="animate-spin"
                            />

                            <Save v-else :size="18" />

                            {{
                                savingProfile
                                    ? "Saving Changes..."
                                    : "Save Profile Changes"
                            }}
                        </button>
                    </form>
                </section>

                <!-- CHANGE PASSWORD -->
                <section class="rounded-2xl border p-6" :class="cardClass">
                    <div class="mb-6 flex items-start gap-3">
                        <div
                            class="rounded-xl bg-purple-500/10 p-3 text-purple-500"
                        >
                            <LockKeyhole :size="22" />
                        </div>

                        <div>
                            <h2
                                class="text-lg font-semibold"
                                :class="titleClass"
                            >
                                Change Password
                            </h2>

                            <p class="mt-1 text-sm" :class="mutedClass">
                                Keep your account secure with a strong password.
                            </p>
                        </div>
                    </div>

                    <form
                        @submit.prevent="handleChangePassword"
                        class="space-y-5"
                    >
                        <!-- PASSWORD FIELDS -->
                        <div v-for="field in passwordFields" :key="field.key">
                            <label :for="field.key" :class="labelClass">
                                {{ field.label }}
                            </label>

                            <div class="relative">
                                <KeyRound
                                    :size="18"
                                    class="absolute left-4 top-1/2 -translate-y-1/2"
                                    :class="mutedClass"
                                />

                                <input
                                    :id="field.key"
                                    v-model="password[field.key]"
                                    :type="
                                        showPasswords[field.key]
                                            ? 'text'
                                            : 'password'
                                    "
                                    :autocomplete="field.autocomplete"
                                    :minlength="
                                        field.key === 'currentPassword'
                                            ? undefined
                                            : 8
                                    "
                                    required
                                    :placeholder="field.placeholder"
                                    :class="[inputBase, inputClass]"
                                    class="pl-11 pr-12"
                                />

                                <button
                                    type="button"
                                    @click="togglePassword(field.key)"
                                    :aria-label="
                                        showPasswords[field.key]
                                            ? `Hide ${field.label}`
                                            : `Show ${field.label}`
                                    "
                                    class="absolute right-4 top-1/2 -translate-y-1/2 transition hover:text-cyan-500"
                                    :class="mutedClass"
                                >
                                    <EyeOff
                                        v-if="showPasswords[field.key]"
                                        :size="18"
                                    />

                                    <Eye v-else :size="18" />
                                </button>
                            </div>
                        </div>

                        <p class="text-xs" :class="mutedClass">
                            Your new password must contain at least 8
                            characters.
                        </p>

                        <!-- PASSWORD MESSAGE -->
                        <div
                            v-if="passwordMessage"
                            role="alert"
                            class="flex items-start gap-3 rounded-xl border p-4 text-sm"
                            :class="
                                passwordMessage.type === 'success'
                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-500'
                                    : 'border-red-500/20 bg-red-500/10 text-red-500'
                            "
                        >
                            <CheckCircle2
                                v-if="passwordMessage.type === 'success'"
                                :size="19"
                                class="shrink-0"
                            />

                            <AlertCircle v-else :size="19" class="shrink-0" />

                            <span>{{ passwordMessage.text }}</span>
                        </div>

                        <!-- UPDATE PASSWORD -->
                        <button
                            type="submit"
                            :disabled="savingPassword"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-500 px-5 py-3.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <RefreshCw
                                v-if="savingPassword"
                                :size="18"
                                class="animate-spin"
                            />

                            <LockKeyhole v-else :size="18" />

                            {{
                                savingPassword
                                    ? "Updating Password..."
                                    : "Update Password"
                            }}
                        </button>
                    </form>
                </section>
            </div>

            <!-- SECURITY NOTE -->
            <div
                class="flex items-start gap-3 rounded-2xl border p-5"
                :class="cardClass"
            >
                <ShieldCheck :size="21" class="mt-0.5 shrink-0 text-cyan-500" />

                <div>
                    <h3 class="font-semibold" :class="titleClass">
                        Account Security
                    </h3>

                    <p class="mt-1 text-sm leading-6" :class="mutedClass">
                        Your profile information is managed through
                        authenticated API requests. Your password is never
                        displayed on this page.
                    </p>
                </div>
            </div>
        </template>
    </div>
</template>
