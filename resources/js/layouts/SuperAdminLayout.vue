<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { RouterLink, RouterView, useRoute, useRouter } from "vue-router";
import {
    LayoutDashboard,
    Store,
    Users,
    CreditCard,
    Settings,
    LogOut,
    Menu,
    Layers3,
    X,
    ShieldCheck,
    ChevronRight,
    Bell,
    Sun,
    Moon,
} from "lucide-vue-next";

import { useTheme } from "../composables/useTheme";

const route = useRoute();
const router = useRouter();

const { isDark, toggleTheme } = useTheme();

const user = ref(null);
const loading = ref(true);
const sidebarOpen = ref(false);

const menuItems = [
    {
        name: "Dashboard",
        href: "/super-admin",
        icon: LayoutDashboard,
    },
    {
        name: "Merchants",
        href: "/super-admin/merchants",
        icon: Store,
    },
    {
        name: "Users",
        href: "/super-admin/users",
        icon: Users,
    },
    {
        name: "Plans",
        href: "/super-admin/plans",
        icon: Layers3,
    },
    {
        name: "Subscriptions",
        href: "/super-admin/subscriptions",
        icon: CreditCard,
    },
    {
        name: "Settings",
        href: "/super-admin/settings",
        icon: Settings,
    },
];

const isActive = (href) => {
    if (href === "/super-admin") {
        return route.path === href;
    }

    return route.path === href || route.path.startsWith(href + "/");
};

const initial = computed(
    () => user.value?.name?.charAt(0)?.toUpperCase() || "A",
);

watch(
    () => route.path,
    () => {
        sidebarOpen.value = false;
    },
);

const checkAuth = async () => {
    try {
        loading.value = true;

        const token = localStorage.getItem("accessToken");

        if (!token) {
            router.replace("/login");
            return;
        }

        const response = await fetch("/api/auth/me", {
            headers: {
                Accept: "application/json",
                Authorization: `Bearer ${token}`,
            },
        });

        if (response.status === 401) {
            localStorage.removeItem("accessToken");
            localStorage.removeItem("user");
            router.replace("/login");
            return;
        }

        if (!response.ok) {
            throw new Error("Failed to verify authentication.");
        }

        const result = await response.json();

        if (!result.success || !result.user) {
            throw new Error("Unauthorized.");
        }

        const currentUser = result.user;

        const role = String(currentUser?.role?.name || currentUser?.role || "")
            .toLowerCase()
            .replace(/[\s-]/g, "_");

        if (role !== "super_admin") {
            router.replace("/dashboard");
            return;
        }

        user.value = currentUser;
    } catch (error) {
        console.error("Super Admin Auth Error:", error);
        router.replace("/login");
    } finally {
        loading.value = false;
    }
};

const handleLogout = () => {
    localStorage.removeItem("accessToken");
    localStorage.removeItem("user");
    router.replace("/login");
};

onMounted(checkAuth);
</script>

<template>
    <div
        class="min-h-screen transition-colors duration-300"
        :class="
            isDark ? 'bg-[#0b1220] text-white' : 'bg-[#f8fafc] text-slate-900'
        "
    >
        <!-- LOADING -->
        <div
            v-if="loading"
            class="min-h-screen flex flex-col items-center justify-center gap-4"
        >
            <div
                class="w-12 h-12 border-4 border-cyan-500/20 border-t-cyan-400 rounded-full animate-spin"
            ></div>
            <p class="text-sm opacity-70">Loading Super Admin Workspace...</p>
        </div>

        <template v-else-if="user">
            <!-- MOBILE OVERLAY -->
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 bg-black/60 z-40 lg:hidden"
                @click="sidebarOpen = false"
            ></div>

            <!-- SIDEBAR -->
            <aside
                class="fixed top-0 left-0 z-50 h-screen w-[265px] border-r flex flex-col transition-transform duration-300 lg:translate-x-0"
                :class="[
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                    isDark
                        ? 'bg-[#101a2c] border-white/10'
                        : 'bg-white border-slate-200',
                ]"
            >
                <!-- LOGO -->
                <div
                    class="h-[80px] px-6 flex items-center justify-between border-b"
                    :class="isDark ? 'border-white/10' : 'border-slate-200'"
                >
                    <RouterLink
                        to="/super-admin"
                        class="flex items-center gap-3"
                    >
                        <div
                            class="w-11 h-11 rounded-xl bg-cyan-500 flex items-center justify-center text-white"
                        >
                            <ShieldCheck :size="25" />
                        </div>

                        <div>
                            <h2 class="text-lg font-bold">CodeAgiles</h2>
                            <p class="text-xs opacity-60">Super Admin Panel</p>
                        </div>
                    </RouterLink>

                    <button
                        type="button"
                        aria-label="Close sidebar"
                        class="lg:hidden opacity-70"
                        @click="sidebarOpen = false"
                    >
                        <X :size="22" />
                    </button>
                </div>

                <!-- NAVIGATION -->
                <nav class="flex-1 overflow-y-auto px-4 py-6">
                    <p
                        class="text-xs uppercase tracking-widest opacity-60 px-3 mb-4"
                    >
                        Main Menu
                    </p>

                    <div class="space-y-2">
                        <RouterLink
                            v-for="item in menuItems"
                            :key="item.href"
                            :to="item.href"
                            class="flex items-center justify-between px-4 py-3 rounded-xl transition-all duration-200"
                            :class="
                                isActive(item.href)
                                    ? 'bg-cyan-500 text-white shadow-lg shadow-cyan-500/20'
                                    : isDark
                                      ? 'text-slate-400 hover:bg-cyan-500/10 hover:text-cyan-400'
                                      : 'text-slate-500 hover:bg-cyan-500/10 hover:text-cyan-600'
                            "
                        >
                            <div class="flex items-center gap-3">
                                <component :is="item.icon" :size="19" />
                                <span class="text-sm font-medium">{{
                                    item.name
                                }}</span>
                            </div>

                            <ChevronRight
                                v-if="isActive(item.href)"
                                :size="16"
                            />
                        </RouterLink>
                    </div>
                </nav>

                <!-- SIDEBAR FOOTER -->
                <div
                    class="p-4 border-t"
                    :class="isDark ? 'border-white/10' : 'border-slate-200'"
                >
                    <div class="flex items-center gap-3 px-3 py-3 mb-3">
                        <div
                            class="w-10 h-10 rounded-full bg-cyan-500/15 text-cyan-500 flex items-center justify-center font-bold"
                        >
                            {{ initial }}
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold truncate">
                                {{ user.name }}
                            </p>
                            <p class="text-xs opacity-60 truncate">
                                {{ user.email }}
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-red-500 hover:bg-red-500/10 transition-colors"
                        @click="handleLogout"
                    >
                        <LogOut :size="19" />
                        <span class="text-sm font-medium">Logout</span>
                    </button>
                </div>
            </aside>

            <!-- MAIN WRAPPER -->
            <div class="lg:ml-[265px] min-h-screen flex flex-col">
                <!-- HEADER -->
                <header
                    class="h-[80px] border-b sticky top-0 z-30 flex items-center justify-between px-4 md:px-8"
                    :class="
                        isDark
                            ? 'bg-[#0b1220] border-white/10'
                            : 'bg-white border-slate-200'
                    "
                >
                    <div class="flex items-center gap-4">
                        <button
                            type="button"
                            aria-label="Open sidebar"
                            class="lg:hidden opacity-70"
                            @click="sidebarOpen = true"
                        >
                            <Menu :size="25" />
                        </button>

                        <div>
                            <h1 class="text-lg md:text-xl font-bold">
                                Super Admin
                            </h1>
                            <p class="text-xs opacity-60 hidden sm:block">
                                Manage your entire platform
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <!-- THEME TOGGLE -->
                        <button
                            type="button"
                            class="w-10 h-10 rounded-xl flex items-center justify-center hover:bg-cyan-500/10 hover:text-cyan-500 transition-all"
                            :aria-label="isDark ? 'Light Mode' : 'Dark Mode'"
                            @click="toggleTheme"
                        >
                            <Sun v-if="isDark" :size="21" />
                            <Moon v-else :size="21" />
                        </button>

                        <!-- NOTIFICATION ICON -->
                        <button
                            type="button"
                            aria-label="Notifications"
                            class="opacity-70 hover:text-cyan-500"
                        >
                            <Bell :size="21" />
                        </button>

                        <!-- ADMIN PROFILE -->
                        <div
                            class="hidden sm:flex items-center gap-3 border-l pl-4"
                            :class="
                                isDark ? 'border-white/10' : 'border-slate-200'
                            "
                        >
                            <div class="text-right">
                                <p class="text-sm font-semibold">
                                    {{ user.name }}
                                </p>
                                <p class="text-xs opacity-60">Administrator</p>
                            </div>

                            <div
                                class="w-10 h-10 rounded-full bg-cyan-500 text-white flex items-center justify-center font-bold"
                            >
                                {{ initial }}
                            </div>
                        </div>
                    </div>
                </header>

                <!-- DYNAMIC PAGE -->
                <main class="flex-1 p-4 md:p-8">
                    <RouterView />
                </main>

                <!-- FOOTER -->
                <footer
                    class="border-t px-4 md:px-8 py-5 flex flex-col sm:flex-row justify-between gap-2 text-xs opacity-60"
                    :class="isDark ? 'border-white/10' : 'border-slate-200'"
                >
                    <p>© CodeAgiles AfterSale AI</p>
                    <p>Super Admin Management System</p>
                </footer>
            </div>
        </template>
    </div>
</template>
