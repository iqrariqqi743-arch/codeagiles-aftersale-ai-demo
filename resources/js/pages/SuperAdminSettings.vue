<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import {
    Settings,
    Save,
    RefreshCw,
    ShieldCheck,
    Globe,
    Mail,
    Phone,
    FileText,
    Wrench,
    UserPlus,
    AlertCircle,
    CheckCircle2,
    LoaderCircle,
    Info,
} from "lucide-vue-next";

import { useTheme } from "../composables/useTheme";

const router = useRouter();
const { isDark } = useTheme();

const loading = ref(true);
const saving = ref(false);
const error = ref("");
const success = ref("");

const form = ref({
    platform_name: "CodeAgiles AfterSale AI",
    support_email: "",
    support_phone: "",
    platform_description: "",
    maintenance_mode: false,
    allow_registration: true,
});

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

const toBoolean = (value) =>
    value === true || value === 1 || value === "1" || value === "true";

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

const apiRequest = async (method = "GET", body = null) => {
    const token = getToken();

    if (!token) {
        router.replace("/login");
        throw new Error("Authentication required.");
    }

    const response = await fetch("/api/super-admin/settings", {
        method,
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${token}`,
            ...(body ? { "Content-Type": "application/json" } : {}),
        },
        ...(body ? { body: JSON.stringify(body) } : {}),
    });

    const result = await response.json();

    if (!response.ok || result.success === false) {
        const err = new Error(result.message || "Something went wrong.");
        err.status = response.status;
        err.errors = result.errors;
        throw err;
    }

    return result;
};

const applySettings = (settings = {}) => {
    form.value = {
        platform_name: settings.platform_name ?? "CodeAgiles AfterSale AI",
        support_email: settings.support_email ?? "",
        support_phone: settings.support_phone ?? "",
        platform_description: settings.platform_description ?? "",
        maintenance_mode: toBoolean(settings.maintenance_mode),
        allow_registration: toBoolean(settings.allow_registration),
    };
};

const fetchSettings = async () => {
    loading.value = true;
    error.value = "";
    success.value = "";

    try {
        const result = await apiRequest();
        applySettings(result.settings);
    } catch (err) {
        if (!handleAuthError(err.status)) {
            error.value = err.message;
        }
    } finally {
        loading.value = false;
    }
};

const saveSettings = async () => {
    if (saving.value || loading.value) return;

    error.value = "";
    success.value = "";

    if (!form.value.platform_name.trim()) {
        error.value = "Platform name is required.";
        return;
    }

    saving.value = true;

    try {
        const result = await apiRequest("PUT", {
            platform_name: form.value.platform_name.trim(),
            support_email: form.value.support_email.trim() || null,
            support_phone: form.value.support_phone.trim() || null,
            platform_description:
                form.value.platform_description.trim() || null,
            maintenance_mode: form.value.maintenance_mode,
            allow_registration: form.value.allow_registration,
        });

        applySettings(result.settings);

        success.value = "Platform settings saved successfully.";
    } catch (err) {
        if (!handleAuthError(err.status)) {
            if (err.errors) {
                error.value = Object.values(err.errors).flat().join(" ");
            } else {
                error.value = err.message;
            }
        }
    } finally {
        saving.value = false;
    }
};

onMounted(fetchSettings);
</script>

<template>
    <main class="w-full pb-10">
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
                    <span>Settings</span>
                </div>

                <h1
                    class="flex items-center gap-3 text-2xl font-bold sm:text-3xl"
                >
                    <Settings :size="30" class="text-cyan-500" />
                    Platform Settings
                </h1>

                <p class="mt-2 text-sm" :class="mutedClass">
                    Manage CodeAgiles AfterSale AI platform configuration.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    :disabled="loading || saving"
                    @click="fetchSettings"
                    class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition disabled:opacity-50"
                    :class="cardClass"
                >
                    <RefreshCw :size="17" />
                    Refresh
                </button>

                <button
                    type="button"
                    :disabled="loading || saving"
                    @click="saveSettings"
                    class="inline-flex items-center gap-2 rounded-xl bg-cyan-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400 disabled:opacity-50"
                >
                    <LoaderCircle
                        v-if="saving"
                        :size="17"
                        class="animate-spin"
                    />
                    <Save v-else :size="17" />
                    {{ saving ? "Saving..." : "Save Changes" }}
                </button>
            </div>
        </div>

        <!-- ALERTS -->
        <div
            v-if="error"
            class="mb-6 flex items-center gap-3 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-500"
        >
            <AlertCircle :size="20" />
            {{ error }}
        </div>

        <div
            v-if="success"
            class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-500"
        >
            <CheckCircle2 :size="20" />
            {{ success }}
        </div>

        <!-- LOADER -->
        <div
            v-if="loading"
            class="flex items-center justify-center gap-3 rounded-2xl border py-24"
            :class="cardClass"
        >
            <LoaderCircle :size="25" class="animate-spin text-cyan-500" />
            Loading platform settings...
        </div>

        <div v-else class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <!-- MAIN SETTINGS -->
            <div class="space-y-6 xl:col-span-2">
                <!-- GENERAL -->
                <section class="rounded-2xl border p-6" :class="cardClass">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="rounded-xl bg-cyan-500/10 p-3">
                            <Globe :size="22" class="text-cyan-500" />
                        </div>

                        <div>
                            <h2 class="text-lg font-bold">General Settings</h2>
                            <p class="text-sm" :class="mutedClass">
                                Basic platform information.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label class="mb-2 block text-sm font-semibold">
                                Platform Name
                            </label>

                            <input
                                v-model="form.platform_name"
                                type="text"
                                maxlength="150"
                                placeholder="Enter platform name"
                                class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                                :class="inputClass"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-2 flex items-center gap-2 text-sm font-semibold"
                            >
                                <FileText :size="16" class="text-cyan-500" />
                                Platform Description
                            </label>

                            <textarea
                                v-model="form.platform_description"
                                rows="4"
                                maxlength="1000"
                                placeholder="Describe your platform..."
                                class="w-full resize-none rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                                :class="inputClass"
                            ></textarea>
                        </div>
                    </div>
                </section>

                <!-- CONTACT -->
                <section class="rounded-2xl border p-6" :class="cardClass">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="rounded-xl bg-cyan-500/10 p-3">
                            <Mail :size="22" class="text-cyan-500" />
                        </div>

                        <div>
                            <h2 class="text-lg font-bold">
                                Support Information
                            </h2>
                            <p class="text-sm" :class="mutedClass">
                                Contact information for merchant support.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label
                                class="mb-2 flex items-center gap-2 text-sm font-semibold"
                            >
                                <Mail :size="16" class="text-cyan-500" />
                                Support Email
                            </label>

                            <input
                                v-model="form.support_email"
                                type="email"
                                placeholder="support@example.com"
                                class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                                :class="inputClass"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-2 flex items-center gap-2 text-sm font-semibold"
                            >
                                <Phone :size="16" class="text-cyan-500" />
                                Support Phone
                            </label>

                            <input
                                v-model="form.support_phone"
                                type="tel"
                                maxlength="30"
                                placeholder="+92 300 1234567"
                                class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:border-cyan-500"
                                :class="inputClass"
                            />
                        </div>
                    </div>
                </section>

                <!-- PLATFORM CONTROLS -->
                <section class="rounded-2xl border p-6" :class="cardClass">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="rounded-xl bg-cyan-500/10 p-3">
                            <Wrench :size="22" class="text-cyan-500" />
                        </div>

                        <div>
                            <h2 class="text-lg font-bold">Platform Controls</h2>
                            <p class="text-sm" :class="mutedClass">
                                Configure platform access preferences.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <!-- MAINTENANCE -->
                        <div
                            class="flex items-center justify-between gap-4 rounded-xl border p-4"
                            :class="borderClass"
                        >
                            <div class="flex items-start gap-3">
                                <Wrench
                                    :size="20"
                                    class="mt-1 shrink-0 text-orange-500"
                                />

                                <div>
                                    <h3 class="font-semibold">
                                        Maintenance Mode
                                    </h3>
                                    <p class="mt-1 text-sm" :class="mutedClass">
                                        Save a maintenance preference for the
                                        platform.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="form.maintenance_mode"
                                @click="
                                    form.maintenance_mode =
                                        !form.maintenance_mode
                                "
                                class="relative h-7 w-12 shrink-0 rounded-full transition"
                                :class="
                                    form.maintenance_mode
                                        ? 'bg-cyan-500'
                                        : 'bg-slate-500'
                                "
                            >
                                <span
                                    class="absolute top-1 h-5 w-5 rounded-full bg-white transition-all"
                                    :class="
                                        form.maintenance_mode
                                            ? 'left-6'
                                            : 'left-1'
                                    "
                                ></span>
                            </button>
                        </div>

                        <!-- REGISTRATION -->
                        <div
                            class="flex items-center justify-between gap-4 rounded-xl border p-4"
                            :class="borderClass"
                        >
                            <div class="flex items-start gap-3">
                                <UserPlus
                                    :size="20"
                                    class="mt-1 shrink-0 text-emerald-500"
                                />

                                <div>
                                    <h3 class="font-semibold">
                                        Allow Registration
                                    </h3>
                                    <p class="mt-1 text-sm" :class="mutedClass">
                                        Save the merchant registration
                                        preference.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="form.allow_registration"
                                @click="
                                    form.allow_registration =
                                        !form.allow_registration
                                "
                                class="relative h-7 w-12 shrink-0 rounded-full transition"
                                :class="
                                    form.allow_registration
                                        ? 'bg-cyan-500'
                                        : 'bg-slate-500'
                                "
                            >
                                <span
                                    class="absolute top-1 h-5 w-5 rounded-full bg-white transition-all"
                                    :class="
                                        form.allow_registration
                                            ? 'left-6'
                                            : 'left-1'
                                    "
                                ></span>
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            <!-- RIGHT SIDEBAR -->
            <div class="space-y-6">
                <section class="rounded-2xl border p-6" :class="cardClass">
                    <div class="mb-5 flex items-center gap-3">
                        <ShieldCheck :size="22" class="text-cyan-500" />
                        <h2 class="text-lg font-bold">Platform Overview</h2>
                    </div>

                    <div class="space-y-4 text-sm">
                        <div class="flex justify-between gap-3">
                            <span :class="mutedClass">Platform</span>
                            <span class="text-right font-semibold">
                                {{ form.platform_name }}
                            </span>
                        </div>

                        <div class="border-t" :class="borderClass"></div>

                        <div class="flex justify-between gap-3">
                            <span :class="mutedClass">Maintenance</span>
                            <span
                                class="font-semibold"
                                :class="
                                    form.maintenance_mode
                                        ? 'text-orange-500'
                                        : 'text-emerald-500'
                                "
                            >
                                {{
                                    form.maintenance_mode
                                        ? "Enabled"
                                        : "Disabled"
                                }}
                            </span>
                        </div>

                        <div class="border-t" :class="borderClass"></div>

                        <div class="flex justify-between gap-3">
                            <span :class="mutedClass">Registration</span>
                            <span
                                class="font-semibold"
                                :class="
                                    form.allow_registration
                                        ? 'text-emerald-500'
                                        : 'text-red-500'
                                "
                            >
                                {{
                                    form.allow_registration
                                        ? "Allowed"
                                        : "Disabled"
                                }}
                            </span>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-cyan-500/20 bg-cyan-500/5 p-6"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <Info :size="20" class="text-cyan-500" />
                        <h3 class="font-bold">Important Information</h3>
                    </div>

                    <p class="text-sm leading-7" :class="mutedClass">
                        Settings are saved in your MySQL database. Maintenance
                        mode and registration controls are configuration
                        preferences only until their backend enforcement logic
                        is implemented.
                    </p>
                </section>

                <button
                    type="button"
                    :disabled="saving || loading"
                    @click="saveSettings"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-500 px-5 py-3 font-semibold text-slate-950 transition hover:bg-cyan-400 disabled:opacity-50"
                >
                    <LoaderCircle
                        v-if="saving"
                        :size="18"
                        class="animate-spin"
                    />
                    <Save v-else :size="18" />
                    {{
                        saving ? "Saving Settings..." : "Save Platform Settings"
                    }}
                </button>
            </div>
        </div>
    </main>
</template>
