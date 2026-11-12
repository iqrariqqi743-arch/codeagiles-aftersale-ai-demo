<script setup>
import { ref, onMounted } from "vue";
import { RouterView, useRouter } from "vue-router";

import Sidebar from "../components/dashboard/Sidebar.vue";
import Header from "../components/dashboard/Header.vue";
import { useTheme } from "../composables/useTheme";

const router = useRouter();

const { isDark, initializeTheme } = useTheme();

const user = ref(null);
const loading = ref(true);
const sidebarOpen = ref(false);

async function checkAuth() {
    loading.value = true;

    try {
        const token = localStorage.getItem("accessToken");

        if (!token) {
            await router.replace("/login");
            return;
        }

        const response = await fetch("/api/auth/me", {
            method: "GET",
            headers: {
                Accept: "application/json",
                Authorization: `Bearer ${token}`,
            },
        });

        if (!response.ok) {
            throw new Error("Unauthorized");
        }

        const data = await response.json();

        if (!data.success || !data.user) {
            throw new Error("Invalid user");
        }

        const role = String(data.user.role || "")
            .toLowerCase()
            .replace(/[\s-]/g, "_");

        if (role !== "owner") {
            if (role === "super_admin" || role === "superadmin") {
                await router.replace("/super-admin");
            } else {
                await router.replace("/login");
            }

            return;
        }

        user.value = data.user;
    } catch (error) {
        console.error("Authentication error:", error);

        localStorage.removeItem("accessToken");
        localStorage.removeItem("user");

        await router.replace("/login");
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    initializeTheme();
    checkAuth();
});
</script>

<template>
    <!-- Loading -->
    <div
        v-if="loading"
        class="flex min-h-screen items-center justify-center"
        :class="isDark ? 'bg-[#0f1726]' : 'bg-[#f8fafc]'"
    >
        <div class="flex flex-col items-center gap-4">
            <div
                class="h-12 w-12 animate-spin rounded-full border-4 border-cyan-500/20 border-t-cyan-400"
            ></div>

            <p
                class="text-sm"
                :class="isDark ? 'text-slate-400' : 'text-slate-600'"
            >
                Loading your workspace...
            </p>
        </div>
    </div>

    <!-- Owner Dashboard -->
    <div
        v-else-if="user"
        class="min-h-screen transition-colors duration-200"
        :class="
            isDark ? 'bg-[#0f1726] text-white' : 'bg-[#f8fafc] text-slate-900'
        "
    >
        <!-- Original Sidebar -->
        <Sidebar v-model:open="sidebarOpen" />

        <!-- Main Content -->
        <div class="flex min-h-screen flex-col lg:ml-[265px]">
            <!-- Original Header -->
            <Header :user="user" @open-sidebar="sidebarOpen = true" />

            <!-- Dynamic Pages -->
            <main class="flex-1 p-4 md:p-8">
                <RouterView />
            </main>

            <!-- Footer -->
            <footer
                class="flex flex-col justify-between gap-2 border-t px-4 py-5 text-xs sm:flex-row md:px-8"
                :class="
                    isDark
                        ? 'border-white/10 text-slate-400'
                        : 'border-slate-200 text-slate-500'
                "
            >
                <p>© CodeAgiles AfterSale AI</p>
                <p>Built for modern commerce</p>
            </footer>
        </div>
    </div>
</template>
