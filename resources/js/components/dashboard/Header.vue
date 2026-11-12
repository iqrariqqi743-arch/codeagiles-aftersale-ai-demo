<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";

import { useRoute, useRouter } from "vue-router";

import {
    Menu,
    ChevronDown,
    LogOut,
    Settings,
    Sun,
    Moon,
    Sparkles,
} from "lucide-vue-next";

import { useTheme } from "../../composables/useTheme";

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["open-sidebar"]);

const route = useRoute();
const router = useRouter();

const { isDark, toggleTheme } = useTheme();

const profileOpen = ref(false);
const profileRef = ref(null);
const loggingOut = ref(false);

const pageTitles = {
    "/dashboard": "Dashboard Overview",
    "/dashboard/stores": "My Stores",
    "/dashboard/orders": "Orders",
    "/dashboard/returns": "Returns",
    "/dashboard/tickets": "Support Tickets",
    "/dashboard/ai": "AI Assistant",
    "/dashboard/ai-settings": "AI Assistant",
    "/dashboard/knowledge-base": "Knowledge Base",
    "/dashboard/analytics": "Analytics",
    "/dashboard/settings": "Settings",
    "/dashboard/help": "Help & Support",
};

const title = computed(() => {
    const pathname = route.path;

    if (pageTitles[pathname]) {
        return pageTitles[pathname];
    }

    const matchedPage = Object.entries(pageTitles).find(
        ([path]) => path !== "/dashboard" && pathname.startsWith(path + "/"),
    );

    return matchedPage?.[1] || "Dashboard";
});

const userInitial = computed(() => {
    return props.user?.name?.charAt(0)?.toUpperCase() || "U";
});

function openSidebar() {
    emit("open-sidebar");
}

function toggleProfile() {
    profileOpen.value = !profileOpen.value;
}

function handleClickOutside(event) {
    if (profileRef.value && !profileRef.value.contains(event.target)) {
        profileOpen.value = false;
    }
}

function handleEscape(event) {
    if (event.key === "Escape") {
        profileOpen.value = false;
    }
}

function goToSettings() {
    profileOpen.value = false;
    router.push("/dashboard/settings");
}

async function handleLogout() {
    if (loggingOut.value) return;

    loggingOut.value = true;
    profileOpen.value = false;

    const token = localStorage.getItem("accessToken");

    try {
        if (token) {
            const response = await fetch("/api/auth/logout", {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    Authorization: `Bearer ${token}`,
                },
            });

            if (!response.ok) {
                console.warn("Logout API returned:", response.status);
            }
        }
    } catch (error) {
        console.error("Logout request failed:", error);
    } finally {
        localStorage.removeItem("accessToken");
        localStorage.removeItem("user");

        loggingOut.value = false;

        await router.replace("/login");
    }
}

watch(
    () => route.path,
    () => {
        profileOpen.value = false;
    },
);

onMounted(() => {
    document.addEventListener("mousedown", handleClickOutside);
    document.addEventListener("keydown", handleEscape);
});

onUnmounted(() => {
    document.removeEventListener("mousedown", handleClickOutside);
    document.removeEventListener("keydown", handleEscape);
});
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-20 items-center justify-between gap-4 border-b px-4 backdrop-blur-xl transition-colors duration-200 md:px-8"
        :class="
            isDark
                ? 'border-white/10 bg-[#0f1726]/95'
                : 'border-slate-200 bg-white/95'
        "
    >
        <!-- Left Side -->
        <div class="flex min-w-0 items-center gap-4">
            <!-- Mobile Menu -->
            <button
                type="button"
                aria-label="Open sidebar"
                class="rounded-xl p-2 lg:hidden"
                :class="
                    isDark
                        ? 'text-slate-300 hover:bg-white/5'
                        : 'text-slate-700 hover:bg-slate-100'
                "
                @click="openSidebar"
            >
                <Menu :size="24" />
            </button>

            <!-- Page Title -->
            <div class="min-w-0">
                <p class="mb-1 hidden text-xs text-slate-500 sm:block">
                    Workspace / {{ title }}
                </p>

                <h1
                    class="truncate text-lg font-semibold md:text-xl"
                    :class="isDark ? 'text-white' : 'text-slate-900'"
                >
                    {{ title }}
                </h1>
            </div>
        </div>

        <!-- Right Side -->
        <div class="flex shrink-0 items-center gap-3 md:gap-5">
            <!-- Workspace Status -->
            <div
                class="hidden items-center gap-2 rounded-full border px-3 py-1.5 sm:flex"
                :class="
                    isDark
                        ? 'border-cyan-500/20 bg-cyan-500/10'
                        : 'border-cyan-200 bg-cyan-50'
                "
            >
                <Sparkles :size="14" class="text-cyan-500" />

                <span
                    class="text-xs font-medium"
                    :class="isDark ? 'text-cyan-400' : 'text-cyan-700'"
                >
                    AfterSale AI
                </span>
            </div>

            <!-- Light / Dark Theme Toggle -->
            <button
                type="button"
                :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                :aria-label="isDark ? 'Enable light mode' : 'Enable dark mode'"
                class="flex h-10 w-10 items-center justify-center rounded-xl border transition-all duration-200"
                :class="
                    isDark
                        ? 'border-white/10 bg-white/5 text-yellow-300 hover:bg-white/10'
                        : 'border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-200'
                "
                @click="toggleTheme"
            >
                <Sun v-if="isDark" :size="20" />
                <Moon v-else :size="20" />
            </button>

            <!-- Profile Dropdown -->
            <div ref="profileRef" class="relative">
                <button
                    type="button"
                    :aria-expanded="profileOpen"
                    aria-haspopup="menu"
                    aria-label="Open account menu"
                    class="flex items-center gap-3 rounded-xl transition-colors"
                    :class="isDark ? 'hover:bg-white/5' : 'hover:bg-slate-100'"
                    @click="toggleProfile"
                >
                    <!-- Avatar -->
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 font-bold text-white shadow-md shadow-cyan-500/10"
                    >
                        {{ userInitial }}
                    </div>

                    <!-- User Info -->
                    <div class="hidden text-left md:block">
                        <p
                            class="max-w-36 truncate text-sm font-semibold"
                            :class="isDark ? 'text-white' : 'text-slate-900'"
                        >
                            {{ user?.name || "Merchant" }}
                        </p>

                        <p class="text-xs capitalize text-slate-500">
                            {{ user?.role || "Owner" }}
                        </p>
                    </div>

                    <ChevronDown
                        :size="16"
                        class="hidden transition-transform md:block"
                        :class="[
                            profileOpen ? 'rotate-180' : '',
                            isDark ? 'text-slate-400' : 'text-slate-500',
                        ]"
                    />
                </button>

                <!-- Dropdown Menu -->
                <div
                    v-if="profileOpen"
                    role="menu"
                    class="absolute right-0 top-14 z-50 w-64 rounded-2xl border p-2 shadow-2xl"
                    :class="
                        isDark
                            ? 'border-white/10 bg-[#172235] shadow-black/40'
                            : 'border-slate-200 bg-white shadow-slate-300/50'
                    "
                >
                    <!-- Account Info -->
                    <div
                        class="mb-2 border-b px-3 py-3"
                        :class="isDark ? 'border-white/10' : 'border-slate-200'"
                    >
                        <p
                            class="truncate text-sm font-semibold"
                            :class="isDark ? 'text-white' : 'text-slate-900'"
                        >
                            {{ user?.name || "Merchant" }}
                        </p>

                        <p class="mt-1 truncate text-xs text-slate-500">
                            {{ user?.email }}
                        </p>
                    </div>

                    <!-- Account Settings -->
                    <button
                        type="button"
                        role="menuitem"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-sm transition-colors"
                        :class="
                            isDark
                                ? 'text-slate-300 hover:bg-white/5'
                                : 'text-slate-700 hover:bg-slate-100'
                        "
                        @click="goToSettings"
                    >
                        <Settings :size="17" />
                        Account Settings
                    </button>

                    <!-- Logout -->
                    <button
                        type="button"
                        role="menuitem"
                        :disabled="loggingOut"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-sm text-red-500 transition-colors disabled:opacity-50"
                        :class="
                            isDark ? 'hover:bg-red-500/10' : 'hover:bg-red-50'
                        "
                        @click="handleLogout"
                    >
                        <LogOut :size="17" />
                        {{ loggingOut ? "Logging out..." : "Logout" }}
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>
