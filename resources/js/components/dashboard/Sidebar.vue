<script setup>
import { watch, onUnmounted } from "vue";
import { RouterLink, useRoute } from "vue-router";
import { useTheme } from "../../composables/useTheme";

import {
    LayoutDashboard,
    Store,
    ShoppingBag,
    RotateCcw,
    MessageSquareText,
    Bot,
    BarChart3,
    Settings,
    LifeBuoy,
    X,
    Sparkles,
    ChevronRight,
    BookOpen,
    CreditCard,
} from "lucide-vue-next";

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:open"]);

const route = useRoute();
const { isDark } = useTheme();

const menu = [
    {
        title: "WORKSPACE",
        items: [
            {
                name: "Overview",
                href: "/dashboard",
                icon: LayoutDashboard,
            },
            {
                name: "My Stores",
                href: "/dashboard/stores",
                icon: Store,
            },
            {
                name: "Orders",
                href: "/dashboard/orders",
                icon: ShoppingBag,
            },
            {
                name: "Returns",
                href: "/dashboard/returns",
                icon: RotateCcw,
            },
            {
                name: "Support Tickets",
                href: "/dashboard/tickets",
                icon: MessageSquareText,
            },
        ],
    },
    {
        title: "INTELLIGENCE",
        items: [
            {
                name: "AI Assistant",
                href: "/dashboard/ai-settings",
                icon: Bot,
            },
            {
                name: "Knowledge Base",
                href: "/dashboard/knowledge-base",
                icon: BookOpen,
            },
            {
                name: "Analytics",
                href: "/dashboard/analytics",
                icon: BarChart3,
            },
        ],
    },
    {
        title: "ACCOUNT",
        items: [
            {
                name: "Billing & Subscription",
                href: "/dashboard/billing",
                icon: CreditCard,
            },
            {
                name: "Settings",
                href: "/dashboard/settings",
                icon: Settings,
            },
        ],
    },
];

function closeSidebar() {
    emit("update:open", false);
}

function isActive(href) {
    const pathname = route.path;

    return (
        pathname === href ||
        (href !== "/dashboard" && pathname.startsWith(href + "/"))
    );
}

function handleEscape(event) {
    if (event.key === "Escape" && props.open) {
        closeSidebar();
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            window.addEventListener("keydown", handleEscape);
        } else {
            window.removeEventListener("keydown", handleEscape);
        }
    },
    { immediate: true },
);

watch(
    () => route.path,
    () => {
        closeSidebar();
    },
);

onUnmounted(() => {
    window.removeEventListener("keydown", handleEscape);
});
</script>

<template>
    <!-- Mobile Overlay -->
    <button
        v-if="open"
        type="button"
        aria-label="Close navigation"
        class="fixed inset-0 z-40 bg-black/60 lg:hidden"
        @click="closeSidebar"
    ></button>

    <!-- Sidebar -->
    <aside
        aria-label="Dashboard navigation"
        class="fixed left-0 top-0 z-50 flex h-screen w-[265px] flex-col border-r transition-[transform,background-color,border-color] duration-300 lg:translate-x-0"
        :class="[
            isDark
                ? 'border-white/10 bg-[#0b1220]'
                : 'border-slate-200 bg-white shadow-xl lg:shadow-none',
            open ? 'translate-x-0' : '-translate-x-full',
        ]"
    >
        <!-- Logo -->
        <div
            class="flex h-20 shrink-0 items-center justify-between border-b px-5"
            :class="isDark ? 'border-white/10' : 'border-slate-200'"
        >
            <RouterLink
                to="/dashboard"
                class="flex items-center gap-3"
                @click="closeSidebar"
            >
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-600 shadow-lg shadow-cyan-500/20"
                >
                    <Sparkles :size="24" class="text-white" />
                </div>

                <div>
                    <h2
                        class="text-lg font-bold leading-tight"
                        :class="isDark ? 'text-white' : 'text-slate-900'"
                    >
                        CodeAgiles
                    </h2>

                    <p
                        class="text-[11px] font-semibold tracking-wider text-cyan-500"
                    >
                        AFTERSALE AI
                    </p>
                </div>
            </RouterLink>

            <button
                type="button"
                aria-label="Close sidebar"
                class="rounded-lg p-2 lg:hidden"
                :class="
                    isDark
                        ? 'text-slate-400 hover:bg-white/10'
                        : 'text-slate-500 hover:bg-slate-100'
                "
                @click="closeSidebar"
            >
                <X :size="22" />
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto px-3 py-6">
            <div v-for="section in menu" :key="section.title" class="mb-7">
                <p
                    class="mb-3 px-4 text-[10px] font-bold tracking-[0.18em]"
                    :class="isDark ? 'text-slate-500' : 'text-slate-400'"
                >
                    {{ section.title }}
                </p>

                <div class="space-y-1">
                    <RouterLink
                        v-for="item in section.items"
                        :key="item.href"
                        :to="item.href"
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                        class="group flex items-center gap-3 rounded-xl border-l-[3px] px-4 py-3 transition-all duration-200"
                        :class="
                            isActive(item.href)
                                ? isDark
                                    ? 'border-cyan-400 bg-cyan-500/10 text-cyan-400'
                                    : 'border-cyan-600 bg-cyan-50 text-cyan-700'
                                : isDark
                                  ? 'border-transparent text-slate-400 hover:bg-white/5 hover:text-white'
                                  : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-950'
                        "
                        @click="closeSidebar"
                    >
                        <component
                            :is="item.icon"
                            :size="19"
                            class="shrink-0"
                        />

                        <span class="flex-1 text-sm font-medium">
                            {{ item.name }}
                        </span>

                        <ChevronRight
                            v-if="isActive(item.href)"
                            :size="15"
                            class="shrink-0"
                        />
                    </RouterLink>
                </div>
            </div>
        </nav>

        <!-- Bottom AI Card -->
        <div
            class="shrink-0 border-t p-4"
            :class="isDark ? 'border-white/10' : 'border-slate-200'"
        >
            <div
                class="rounded-xl border p-4"
                :class="
                    isDark
                        ? 'border-cyan-500/20 bg-gradient-to-br from-cyan-500/10 to-blue-500/10'
                        : 'border-cyan-200 bg-gradient-to-br from-cyan-50 to-blue-50'
                "
            >
                <div
                    class="mb-2 flex items-center gap-2"
                    :class="isDark ? 'text-cyan-400' : 'text-cyan-700'"
                >
                    <Sparkles :size="17" />

                    <span class="text-sm font-semibold"> AfterSale AI </span>
                </div>

                <p
                    class="text-xs leading-relaxed"
                    :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                >
                    Automate post-purchase support and grow your business.
                </p>
            </div>
        </div>
    </aside>
</template>
