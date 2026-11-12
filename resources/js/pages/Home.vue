<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from "vue";
import { RouterLink } from "vue-router";
import {
    ArrowRight,
    ArrowUpRight,
    Bot,
    Check,
    CheckCircle2,
    ChevronDown,
    Headphones,
    LayoutDashboard,
    Mail,
    Menu,
    MessageSquareText,
    Moon,
    Package,
    RefreshCcw,
    ShieldCheck,
    Sparkles,
    Store,
    Sun,
    Ticket,
    TrendingUp,
    Workflow,
    X,
    Zap,
} from "lucide-vue-next";

/* Navigation */
const navigation = [
    { name: "Features", href: "#features" },
    { name: "How It Works", href: "#how-it-works" },
    { name: "Pricing", href: "#pricing" },
    { name: "FAQ", href: "#faq" },
];

/* Features */
const features = [
    {
        icon: Bot,
        title: "AI-Powered Support",
        description:
            "Use AI assistance to prepare helpful responses and make customer support more efficient.",
        color: "from-cyan-500 to-blue-500",
    },
    {
        icon: Ticket,
        title: "Support Ticket Management",
        description:
            "Create, prioritize, track, and resolve customer issues from one organized workspace.",
        color: "from-violet-500 to-purple-500",
    },
    {
        icon: Mail,
        title: "Email Conversations",
        description:
            "Keep customer email replies connected with tickets and complete conversation history.",
        color: "from-blue-500 to-indigo-500",
    },
    {
        icon: RefreshCcw,
        title: "Returns Management",
        description:
            "Organize product return requests and manage after-sales issues with clarity.",
        color: "from-emerald-500 to-teal-500",
    },
    {
        icon: Store,
        title: "Multi-Store Management",
        description:
            "Manage support operations across multiple ecommerce stores in one dashboard.",
        color: "from-orange-500 to-amber-500",
    },
    {
        icon: LayoutDashboard,
        title: "Centralized Dashboard",
        description:
            "View support tickets, conversations, returns, and important activities in one place.",
        color: "from-pink-500 to-rose-500",
    },
];

/* How It Works */
const steps = [
    {
        number: "01",
        icon: CheckCircle2,
        title: "Create Your Account",
        description:
            "Register your business and access your support workspace.",
    },
    {
        number: "02",
        icon: Store,
        title: "Set Up Your Store",
        description:
            "Configure your ecommerce store and organize your support workflow.",
    },
    {
        number: "03",
        icon: Headphones,
        title: "Manage Customer Support",
        description:
            "Handle customer tickets, email replies, and returns from one dashboard.",
    },
];

/* Pricing */
const plans = [
    {
        name: "Starter",
        price: 29,
        description: "For smaller ecommerce businesses.",
        popular: false,
    },
    {
        name: "Growth",
        price: 49,
        description: "For growing ecommerce businesses.",
        popular: true,
    },
    {
        name: "Pro",
        price: 119,
        description: "For businesses scaling their support operations.",
        popular: false,
    },
];

/* FAQs */
const faqs = [
    {
        question: "What is CodeAgiles AfterSale AI?",
        answer: "CodeAgiles AfterSale AI is an ecommerce customer support platform for managing tickets, customer conversations, email replies, and product returns.",
    },
    {
        question: "Does it support WooCommerce?",
        answer: "The platform includes WooCommerce store integration workflows for ecommerce merchants.",
    },
    {
        question: "Can I manage multiple stores?",
        answer: "Yes. You can organize customer support operations across multiple stores.",
    },
    {
        question: "Can customers reply through email?",
        answer: "Yes. Customer email replies can be tracked in support ticket conversations.",
    },
    {
        question: "Can I manage product returns?",
        answer: "Yes. The platform includes tools for organizing and tracking product return requests.",
    },
];

/* Dashboard Preview */
const metrics = [
    { label: "Support Tickets", value: "128", icon: Ticket },
    {
        label: "Conversations",
        value: "64",
        icon: MessageSquareText,
    },
    { label: "Returns", value: "12", icon: RefreshCcw },
];

const activities = [
    {
        title: "Order delivery inquiry",
        subtitle: "Customer support ticket",
        status: "Open",
        color: "bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400",
    },
    {
        title: "Product return request",
        subtitle: "Returns management",
        status: "Pending",
        color: "bg-cyan-100 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400",
    },
    {
        title: "Email reply received",
        subtitle: "Customer conversation",
        status: "Resolved",
        color: "bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400",
    },
];

const highlights = [
    { icon: Headphones, label: "Customer Support" },
    { icon: Workflow, label: "Workflow Management" },
    { icon: Package, label: "Ecommerce Operations" },
];

const benefits = [
    "Keep customer conversations organized",
    "Manage support tickets and priorities",
    "Handle multiple stores from one platform",
    "Improve visibility into after-sales operations",
];

const reasons = [
    {
        icon: Zap,
        title: "Efficient Workflows",
        text: "Organize customer support tasks in one place.",
    },
    {
        icon: ShieldCheck,
        title: "Organized Access",
        text: "Dedicated owner and administration workflows.",
    },
    {
        icon: TrendingUp,
        title: "Ready to Grow",
        text: "Support your expanding ecommerce operations.",
    },
    {
        icon: Bot,
        title: "AI Assistance",
        text: "Bring AI into your customer support process.",
    },
];

const planBenefits = [
    "Customer support dashboard",
    "Ticket management",
    "After-sales support tools",
    "Subscription-based access",
];

const heroBenefits = [
    "Centralized Support",
    "Multi-Store Workflow",
    "AI Assistance",
];

/* Dark / Light Theme */
const theme = ref("light");
const dark = computed(() => theme.value === "dark");

function applyTheme() {
    document.documentElement.classList.toggle("dark", dark.value);
}

function toggleTheme() {
    theme.value = dark.value ? "light" : "dark";
    localStorage.setItem("aftersale-theme", theme.value);
    applyTheme();
}

/* Mobile Menu */
const mobileMenu = ref(false);

function closeMenu() {
    mobileMenu.value = false;
}

/* FAQ */
const openFaq = ref(null);

function toggleFaq(index) {
    openFaq.value = openFaq.value === index ? null : index;
}

/* Scroll Reveal Animations */
let observer = null;
let mediaQuery = null;

function initializeAnimations() {
    if (observer) {
        observer.disconnect();
    }

    const elements = document.querySelectorAll("[data-reveal]");

    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        elements.forEach((element) => {
            element.classList.add("is-visible");
        });
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    observer.unobserve(entry.target);
                }
            });
        },
        {
            threshold: 0.12,
            rootMargin: "0px 0px -20px 0px",
        },
    );

    elements.forEach((element) => {
        observer.observe(element);
    });
}

onMounted(async () => {
    const savedTheme = localStorage.getItem("aftersale-theme");

    if (savedTheme === "dark" || savedTheme === "light") {
        theme.value = savedTheme;
    } else {
        theme.value = window.matchMedia("(prefers-color-scheme: dark)").matches
            ? "dark"
            : "light";
    }

    applyTheme();

    await nextTick();
    initializeAnimations();
});

onUnmounted(() => {
    if (observer) observer.disconnect();
});
</script>

<template>
    <div
        class="min-h-screen overflow-x-hidden bg-white text-slate-900 transition-colors duration-300 dark:bg-slate-950 dark:text-white"
    >
        <!-- NAVBAR -->
        <header
            class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/90"
        >
            <nav
                class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-5 sm:px-8"
            >
                <RouterLink
                    to="/"
                    class="flex min-w-0 items-center gap-2 text-base font-extrabold tracking-tight sm:text-xl"
                    @click="closeMenu"
                >
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-500 text-slate-950 transition-transform duration-300 hover:rotate-12 hover:scale-110"
                    >
                        <Sparkles :size="22" />
                    </span>

                    <span class="truncate">
                        CodeAgiles
                        <span class="text-cyan-500">AfterSale AI</span>
                    </span>
                </RouterLink>

                <div
                    class="hidden items-center gap-8 text-sm font-medium text-slate-600 dark:text-slate-300 lg:flex"
                >
                    <a
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="transition-colors hover:text-cyan-500"
                    >
                        {{ item.name }}
                    </a>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Theme Toggle -->
                    <button
                        type="button"
                        :aria-label="
                            dark
                                ? 'Switch to light mode'
                                : 'Switch to dark mode'
                        "
                        :title="dark ? 'Light mode' : 'Dark mode'"
                        class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-slate-700 transition-all hover:border-cyan-500 active:scale-90 dark:border-slate-700 dark:bg-slate-900 dark:text-cyan-400"
                        @click="toggleTheme"
                    >
                        <Transition mode="out-in" name="theme-icon">
                            <Sun v-if="dark" key="sun" :size="20" />
                            <Moon v-else key="moon" :size="20" />
                        </Transition>
                    </button>

                    <RouterLink
                        to="/login"
                        class="hidden text-sm font-semibold text-slate-600 transition-colors hover:text-cyan-500 dark:text-slate-300 sm:block"
                    >
                        Login
                    </RouterLink>

                    <RouterLink
                        to="/register"
                        class="hidden items-center gap-2 rounded-xl bg-cyan-500 px-5 py-3 text-sm font-bold text-slate-950 transition-colors hover:bg-cyan-400 md:inline-flex"
                    >
                        Get Started
                        <ArrowRight :size="17" />
                    </RouterLink>

                    <button
                        type="button"
                        :aria-expanded="mobileMenu"
                        aria-label="Toggle navigation menu"
                        class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 dark:border-slate-700 lg:hidden"
                        @click="mobileMenu = !mobileMenu"
                    >
                        <X v-if="mobileMenu" :size="21" />
                        <Menu v-else :size="21" />
                    </button>
                </div>
            </nav>

            <!-- Mobile Navigation -->
            <Transition name="mobile-menu">
                <div
                    v-if="mobileMenu"
                    class="overflow-hidden border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950 lg:hidden"
                >
                    <div
                        class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-5 text-sm font-semibold"
                    >
                        <a
                            v-for="item in navigation"
                            :key="item.href"
                            :href="item.href"
                            class="py-1 transition-colors hover:text-cyan-500"
                            @click="closeMenu"
                        >
                            {{ item.name }}
                        </a>

                        <RouterLink to="/login" @click="closeMenu">
                            Login
                        </RouterLink>

                        <RouterLink
                            to="/register"
                            class="rounded-lg bg-cyan-500 px-4 py-3 text-center text-slate-950"
                            @click="closeMenu"
                        >
                            Create Account
                        </RouterLink>
                    </div>
                </div>
            </Transition>
        </header>

        <!-- HERO -->
        <section class="relative isolate overflow-hidden">
            <div
                class="pointer-events-none absolute left-1/2 top-0 -z-10 h-[500px] w-[700px] -translate-x-1/2 rounded-full bg-cyan-500/10 blur-[120px]"
            ></div>

            <div
                class="pointer-events-none absolute -right-32 top-32 -z-10 h-72 w-72 rounded-full bg-blue-500/10 blur-[90px]"
            ></div>

            <div
                class="mx-auto grid max-w-7xl items-center gap-16 px-5 pb-24 pt-20 sm:px-8 lg:grid-cols-2 lg:py-32"
            >
                <div>
                    <div data-reveal>
                        <div
                            class="mb-7 inline-flex items-center gap-2 rounded-full border border-cyan-500/25 bg-cyan-500/10 px-4 py-2 text-xs font-semibold text-cyan-600 dark:text-cyan-300 sm:text-sm"
                        >
                            <Sparkles :size="15" />
                            Smarter After-Sales Customer Support
                        </div>
                    </div>

                    <div data-reveal style="--reveal-delay: 100ms">
                        <h1
                            class="text-4xl font-extrabold leading-[1.12] tracking-tight sm:text-5xl lg:text-6xl"
                        >
                            Customer Support,

                            <span
                                class="mt-2 block bg-gradient-to-r from-cyan-500 via-sky-400 to-blue-500 bg-clip-text text-transparent"
                            >
                                Made Smarter
                            </span>

                            with AI.
                        </h1>
                    </div>

                    <div data-reveal style="--reveal-delay: 200ms">
                        <p
                            class="mt-7 max-w-xl text-base leading-8 text-slate-600 dark:text-slate-400 sm:text-lg"
                        >
                            Manage customer support tickets, email
                            conversations, product returns, and ecommerce
                            operations from one powerful platform. Simplify
                            after-sales support with CodeAgiles AfterSale AI.
                        </p>
                    </div>

                    <div data-reveal style="--reveal-delay: 300ms">
                        <div class="mt-9 flex flex-wrap items-center gap-4">
                            <RouterLink
                                to="/register"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-500 px-7 py-4 font-bold text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:-translate-y-1 hover:scale-[1.02] hover:bg-cyan-400"
                            >
                                Get Started
                                <ArrowRight :size="19" />
                            </RouterLink>

                            <a
                                href="#how-it-works"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 px-7 py-4 font-semibold transition-colors hover:border-cyan-500 hover:text-cyan-500 dark:border-slate-700"
                            >
                                Explore Platform
                                <ArrowUpRight :size="18" />
                            </a>
                        </div>
                    </div>

                    <div data-reveal style="--reveal-delay: 400ms">
                        <div
                            class="mt-10 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-500 dark:text-slate-400"
                        >
                            <span
                                v-for="item in heroBenefits"
                                :key="item"
                                class="flex items-center gap-2"
                            >
                                <CheckCircle2
                                    :size="17"
                                    class="text-cyan-500"
                                />
                                {{ item }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- DASHBOARD PREVIEW -->
                <div
                    data-reveal
                    style="--reveal-delay: 200ms"
                    class="relative mx-auto w-full max-w-xl"
                >
                    <div
                        class="pointer-events-none absolute -inset-8 rounded-full bg-cyan-500/15 blur-[90px]"
                    ></div>

                    <div
                        class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl shadow-cyan-900/10 dark:border-slate-700 dark:bg-slate-900 dark:shadow-cyan-950/30"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-500"
                                >
                                    <LayoutDashboard :size="22" />
                                </div>

                                <div>
                                    <p class="text-sm font-bold">
                                        AfterSale AI
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        Dashboard preview
                                    </p>
                                </div>
                            </div>

                            <span
                                class="flex items-center gap-2 rounded-full bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold text-emerald-500"
                            >
                                <span
                                    class="h-2 w-2 rounded-full bg-emerald-500"
                                ></span>
                                Demo
                            </span>
                        </div>

                        <div class="space-y-5 p-5">
                            <div>
                                <p class="text-sm text-slate-500">
                                    Welcome to your workspace
                                </p>

                                <h3 class="mt-1 text-xl font-bold">
                                    Support Overview
                                </h3>
                            </div>

                            <!-- Metrics -->
                            <div class="grid grid-cols-3 gap-3">
                                <div
                                    v-for="(metric, index) in metrics"
                                    :key="metric.label"
                                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 transition-transform duration-300 hover:-translate-y-1 dark:border-slate-800 dark:bg-slate-950 sm:p-4"
                                    :style="{
                                        animationDelay: `${500 + index * 120}ms`,
                                    }"
                                >
                                    <component
                                        :is="metric.icon"
                                        :size="19"
                                        class="text-cyan-500"
                                    />

                                    <p
                                        class="mt-3 text-xl font-extrabold sm:text-2xl"
                                    >
                                        {{ metric.value }}
                                    </p>

                                    <p
                                        class="mt-1 text-[10px] text-slate-500 sm:text-xs"
                                    >
                                        {{ metric.label }}
                                    </p>
                                </div>
                            </div>

                            <!-- Recent Activity -->
                            <div
                                class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800"
                            >
                                <div
                                    class="flex items-center justify-between border-b border-slate-200 px-4 py-4 dark:border-slate-800"
                                >
                                    <h4 class="text-sm font-semibold">
                                        Recent Support Activity
                                    </h4>

                                    <span class="text-xs text-cyan-500">
                                        Sample data
                                    </span>
                                </div>

                                <div
                                    v-for="activity in activities"
                                    :key="activity.title"
                                    class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 last:border-0 dark:border-slate-800"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <div
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"
                                        >
                                            <MessageSquareText :size="17" />
                                        </div>

                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-xs font-semibold sm:text-sm"
                                            >
                                                {{ activity.title }}
                                            </p>

                                            <p
                                                class="text-[11px] text-slate-500"
                                            >
                                                {{ activity.subtitle }}
                                            </p>
                                        </div>
                                    </div>

                                    <span
                                        :class="activity.color"
                                        class="shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold"
                                    >
                                        {{ activity.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="mt-5 text-center text-xs text-slate-500">
                        Illustrative dashboard with sample data.
                    </p>
                </div>
            </div>
        </section>

        <!-- PLATFORM HIGHLIGHTS -->
        <section
            class="border-y border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/40"
        >
            <div
                class="mx-auto grid max-w-7xl gap-8 px-5 py-10 text-center sm:grid-cols-3 sm:px-8"
            >
                <div
                    v-for="(item, index) in highlights"
                    :key="item.label"
                    data-reveal
                    :style="{ '--reveal-delay': `${index * 100}ms` }"
                    class="flex items-center justify-center gap-3 text-sm font-bold text-slate-600 dark:text-slate-300"
                >
                    <component
                        :is="item.icon"
                        :size="22"
                        class="text-cyan-500"
                    />
                    {{ item.label }}
                </div>
            </div>
        </section>

        <!-- FEATURES -->
        <section id="features" class="scroll-mt-24 px-5 py-24 sm:px-8 lg:py-32">
            <div class="mx-auto max-w-7xl">
                <div data-reveal class="mx-auto max-w-3xl text-center">
                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-cyan-500/20 bg-cyan-500/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] text-cyan-600 dark:text-cyan-400"
                    >
                        <Sparkles :size="14" />
                        Powerful Features
                    </div>

                    <h2
                        class="text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl lg:text-5xl"
                    >
                        Everything You Need to Support Your Customers
                    </h2>

                    <p
                        class="mx-auto mt-6 max-w-2xl text-base leading-8 text-slate-600 dark:text-slate-400"
                    >
                        Bring your after-sales operations together and give your
                        team the tools to manage customer issues more
                        efficiently.
                    </p>
                </div>

                <div class="mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(feature, index) in features"
                        :key="feature.title"
                        data-reveal
                        :style="{
                            '--reveal-delay': `${(index % 3) * 100}ms`,
                        }"
                        class="group relative h-full overflow-hidden rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-2 hover:border-cyan-500/50 hover:shadow-xl dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            :class="feature.color"
                            class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                        ></div>

                        <div
                            :class="feature.color"
                            class="mb-6 flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-lg shadow-slate-900/10 transition-transform duration-300 group-hover:scale-110"
                        >
                            <component :is="feature.icon" :size="26" />
                        </div>

                        <h3 class="mb-3 text-xl font-bold">
                            {{ feature.title }}
                        </h3>

                        <p class="leading-7 text-slate-500 dark:text-slate-400">
                            {{ feature.description }}
                        </p>

                        <ArrowUpRight
                            :size="19"
                            class="absolute right-6 top-7 text-slate-300 opacity-0 transition-all duration-300 group-hover:-translate-y-1 group-hover:translate-x-1 group-hover:text-cyan-500 group-hover:opacity-100"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- WHY CODEAGILES -->
        <section
            class="border-y border-slate-200 bg-slate-50 px-5 py-24 dark:border-slate-800 dark:bg-slate-900/40 sm:px-8"
        >
            <div
                class="mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-2"
            >
                <div data-reveal>
                    <p
                        class="mb-4 text-sm font-bold uppercase tracking-[0.2em] text-cyan-500"
                    >
                        Why CodeAgiles?
                    </p>

                    <h2
                        class="text-3xl font-extrabold leading-tight sm:text-4xl lg:text-5xl"
                    >
                        Spend Less Time Managing Issues.

                        <span class="text-cyan-500"> More Time Growing. </span>
                    </h2>

                    <p
                        class="mt-6 leading-8 text-slate-500 dark:text-slate-400"
                    >
                        Customer support gets complicated when tickets, emails,
                        and returns are scattered across different workflows.
                        CodeAgiles AfterSale AI helps bring them together.
                    </p>

                    <div class="mt-8 space-y-5">
                        <div
                            v-for="benefit in benefits"
                            :key="benefit"
                            class="flex items-start gap-3"
                        >
                            <CheckCircle2
                                :size="21"
                                class="mt-0.5 shrink-0 text-cyan-500"
                            />

                            <span class="text-slate-600 dark:text-slate-300">
                                {{ benefit }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div
                        v-for="(reason, index) in reasons"
                        :key="reason.title"
                        data-reveal
                        :style="{
                            '--reveal-delay': `${(index % 2) * 120}ms`,
                        }"
                        class="h-full rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-1.5 hover:scale-[1.015] hover:shadow-lg dark:border-slate-800 dark:bg-slate-950"
                    >
                        <component
                            :is="reason.icon"
                            :size="30"
                            class="mb-5 text-cyan-500"
                        />

                        <h3 class="mb-2 text-lg font-bold">
                            {{ reason.title }}
                        </h3>

                        <p
                            class="text-sm leading-7 text-slate-500 dark:text-slate-400"
                        >
                            {{ reason.text }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- HOW IT WORKS -->
        <section
            id="how-it-works"
            class="scroll-mt-24 px-5 py-24 sm:px-8 lg:py-32"
        >
            <div class="mx-auto max-w-7xl">
                <div data-reveal class="mx-auto max-w-3xl text-center">
                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-cyan-500/20 bg-cyan-500/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] text-cyan-600 dark:text-cyan-400"
                    >
                        <Sparkles :size="14" />
                        How It Works
                    </div>

                    <h2
                        class="text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl lg:text-5xl"
                    >
                        Get Started in Three Simple Steps
                    </h2>

                    <p
                        class="mx-auto mt-6 max-w-2xl text-base leading-8 text-slate-600 dark:text-slate-400"
                    >
                        A straightforward way to organize ecommerce customer
                        support.
                    </p>
                </div>

                <div class="mt-16 grid gap-6 md:grid-cols-3">
                    <div
                        v-for="(step, index) in steps"
                        :key="step.number"
                        data-reveal
                        :style="{
                            '--reveal-delay': `${index * 150}ms`,
                        }"
                        class="relative h-full overflow-hidden rounded-2xl border border-slate-200 bg-white p-8 shadow-sm transition-all duration-300 hover:-translate-y-2 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="text-5xl font-extrabold text-cyan-500/30"
                            >
                                {{ step.number }}
                            </span>

                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-500"
                            >
                                <component :is="step.icon" :size="24" />
                            </div>
                        </div>

                        <h3 class="mt-7 text-xl font-bold">
                            {{ step.title }}
                        </h3>

                        <p
                            class="mt-4 leading-7 text-slate-500 dark:text-slate-400"
                        >
                            {{ step.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- PRICING -->
        <section
            id="pricing"
            class="scroll-mt-24 border-y border-slate-200 bg-slate-50 px-5 py-24 dark:border-slate-800 dark:bg-slate-900/40 sm:px-8 lg:py-32"
        >
            <div class="mx-auto max-w-7xl">
                <div data-reveal class="mx-auto max-w-3xl text-center">
                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-cyan-500/20 bg-cyan-500/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] text-cyan-600 dark:text-cyan-400"
                    >
                        <Sparkles :size="14" />
                        Simple Pricing
                    </div>

                    <h2
                        class="text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl lg:text-5xl"
                    >
                        Choose the Right Plan for Your Business
                    </h2>

                    <p
                        class="mx-auto mt-6 max-w-2xl text-base leading-8 text-slate-600 dark:text-slate-400"
                    >
                        Explore our monthly subscription plans for ecommerce
                        customer support.
                    </p>
                </div>

                <div class="mt-16 grid gap-8 lg:grid-cols-3">
                    <div
                        v-for="(plan, index) in plans"
                        :key="plan.name"
                        data-reveal
                        :style="{
                            '--reveal-delay': `${index * 120}ms`,
                        }"
                        :class="
                            plan.popular
                                ? 'border-cyan-500 shadow-xl shadow-cyan-900/10'
                                : 'border-slate-200 shadow-sm dark:border-slate-800'
                        "
                        class="relative flex h-full flex-col rounded-2xl border bg-white p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-xl dark:bg-slate-900"
                    >
                        <span
                            v-if="plan.popular"
                            class="absolute -top-4 left-1/2 -translate-x-1/2 rounded-full bg-cyan-500 px-5 py-2 text-xs font-bold text-slate-950 shadow-lg shadow-cyan-500/20"
                        >
                            POPULAR CHOICE
                        </span>

                        <h3 class="text-2xl font-bold">
                            {{ plan.name }}
                        </h3>

                        <p
                            class="mt-3 min-h-12 text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            {{ plan.description }}
                        </p>

                        <div class="my-7 flex items-baseline gap-1">
                            <span class="text-5xl font-extrabold">
                                ${{ plan.price }}
                            </span>

                            <span class="text-slate-500"> /month </span>
                        </div>

                        <div
                            class="mb-8 h-px bg-slate-200 dark:bg-slate-800"
                        ></div>

                        <div class="mb-9 space-y-4">
                            <div
                                v-for="benefit in planBenefits"
                                :key="benefit"
                                class="flex gap-3"
                            >
                                <Check
                                    :size="19"
                                    class="shrink-0 text-cyan-500"
                                />

                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300"
                                >
                                    {{ benefit }}
                                </span>
                            </div>
                        </div>

                        <RouterLink
                            to="/register"
                            :class="
                                plan.popular
                                    ? 'bg-cyan-500 text-slate-950 hover:bg-cyan-400'
                                    : 'border border-slate-300 hover:border-cyan-500 dark:border-slate-700'
                            "
                            class="mt-auto flex items-center justify-center gap-2 rounded-xl px-5 py-4 font-bold transition-colors"
                        >
                            Get Started
                            <ArrowRight :size="18" />
                        </RouterLink>
                    </div>
                </div>

                <p class="mt-8 text-center text-sm text-slate-500">
                    Plan features and usage limits should be confirmed during
                    signup.
                </p>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="scroll-mt-24 px-5 py-24 sm:px-8 lg:py-32">
            <div class="mx-auto max-w-4xl">
                <div data-reveal class="mx-auto max-w-3xl text-center">
                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-cyan-500/20 bg-cyan-500/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] text-cyan-600 dark:text-cyan-400"
                    >
                        <Sparkles :size="14" />
                        Frequently Asked Questions
                    </div>

                    <h2
                        class="text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl lg:text-5xl"
                    >
                        Everything You Want to Know
                    </h2>

                    <p
                        class="mx-auto mt-6 max-w-2xl text-base leading-8 text-slate-600 dark:text-slate-400"
                    >
                        Answers to common questions about CodeAgiles AfterSale
                        AI.
                    </p>
                </div>

                <div class="mt-14 space-y-4">
                    <div
                        v-for="(faq, index) in faqs"
                        :key="faq.question"
                        data-reveal
                        :style="{
                            '--reveal-delay': `${index * 60}ms`,
                        }"
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-colors hover:border-cyan-500/40 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <button
                            type="button"
                            :aria-expanded="openFaq === index"
                            class="flex w-full cursor-pointer items-center justify-between gap-4 p-6 text-left font-semibold"
                            @click="toggleFaq(index)"
                        >
                            {{ faq.question }}

                            <ChevronDown
                                :size="20"
                                class="shrink-0 text-cyan-500 transition-transform duration-200"
                                :class="{
                                    'rotate-180': openFaq === index,
                                }"
                            />
                        </button>

                        <Transition name="faq">
                            <div
                                v-if="openFaq === index"
                                class="overflow-hidden"
                            >
                                <p
                                    class="px-6 pb-6 leading-7 text-slate-500 dark:text-slate-400"
                                >
                                    {{ faq.answer }}
                                </p>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="px-5 pb-24 sm:px-8 lg:pb-32">
            <div data-reveal class="mx-auto max-w-7xl">
                <div
                    class="relative isolate overflow-hidden rounded-3xl border border-cyan-500/30 bg-gradient-to-br from-cyan-50 via-white to-sky-50 px-6 py-16 text-center dark:from-cyan-950/60 dark:via-slate-900 dark:to-slate-900 sm:px-12 lg:py-20"
                >
                    <div
                        class="pointer-events-none absolute left-1/2 top-0 -z-10 h-60 w-96 -translate-x-1/2 rounded-full bg-cyan-500/15 blur-[90px]"
                    ></div>

                    <div
                        class="cta-sparkle mx-auto mb-6 flex w-fit items-center justify-center"
                    >
                        <Sparkles :size="36" class="text-cyan-500" />
                    </div>

                    <h2
                        class="mx-auto max-w-3xl text-3xl font-extrabold sm:text-4xl lg:text-5xl"
                    >
                        Ready to Simplify Customer Support?
                    </h2>

                    <p
                        class="mx-auto mt-6 max-w-2xl leading-8 text-slate-600 dark:text-slate-300"
                    >
                        Bring your ecommerce customer support operations
                        together with CodeAgiles AfterSale AI.
                    </p>

                    <RouterLink
                        to="/register"
                        class="mt-9 inline-flex items-center gap-2 rounded-xl bg-cyan-500 px-8 py-4 font-bold text-slate-950 shadow-lg shadow-cyan-500/20 transition-all hover:-translate-y-1 hover:scale-[1.03] hover:bg-cyan-400"
                    >
                        Get Started Today
                        <ArrowUpRight :size="19" />
                    </RouterLink>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer
            class="border-t border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950"
        >
            <div
                class="mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:px-8 md:grid-cols-3"
            >
                <div>
                    <RouterLink to="/" class="text-xl font-extrabold">
                        CodeAgiles
                        <span class="text-cyan-500"> AfterSale AI </span>
                    </RouterLink>

                    <p
                        class="mt-4 max-w-sm text-sm leading-7 text-slate-500 dark:text-slate-400"
                    >
                        Smarter after-sales customer support for modern
                        ecommerce businesses.
                    </p>
                </div>

                <div>
                    <h3 class="mb-5 font-bold">Platform</h3>

                    <div
                        class="flex flex-col gap-3 text-sm text-slate-500 dark:text-slate-400"
                    >
                        <a
                            v-for="item in navigation"
                            :key="item.href"
                            :href="item.href"
                            class="transition-colors hover:text-cyan-500"
                        >
                            {{ item.name }}
                        </a>
                    </div>
                </div>

                <div>
                    <h3 class="mb-5 font-bold">Account</h3>

                    <div
                        class="flex flex-col gap-3 text-sm text-slate-500 dark:text-slate-400"
                    >
                        <RouterLink to="/login" class="hover:text-cyan-500">
                            Login
                        </RouterLink>

                        <RouterLink to="/register" class="hover:text-cyan-500">
                            Create Account
                        </RouterLink>

                        <RouterLink
                            to="/forgot-password"
                            class="hover:text-cyan-500"
                        >
                            Forgot Password
                        </RouterLink>
                    </div>
                </div>
            </div>

            <div
                class="border-t border-slate-200 px-5 py-6 text-center text-xs text-slate-500 dark:border-slate-800"
            >
                © {{ new Date().getFullYear() }} CodeAgiles AfterSale AI. All
                rights reserved.
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* Scroll reveal */
[data-reveal] {
    opacity: 0;
    transform: translateY(35px);
    transition:
        opacity 700ms cubic-bezier(0.22, 1, 0.36, 1),
        transform 700ms cubic-bezier(0.22, 1, 0.36, 1);
    transition-delay: var(--reveal-delay, 0ms);
}

[data-reveal].is-visible {
    opacity: 1;
    transform: translateY(0);
}

/* Theme icon */
.theme-icon-enter-active,
.theme-icon-leave-active {
    transition:
        opacity 200ms ease,
        transform 200ms ease;
}

.theme-icon-enter-from {
    opacity: 0;
    transform: rotate(-90deg) scale(0.6);
}

.theme-icon-leave-to {
    opacity: 0;
    transform: rotate(90deg) scale(0.6);
}

/* Mobile navigation */
.mobile-menu-enter-active,
.mobile-menu-leave-active {
    transition:
        opacity 250ms ease,
        max-height 250ms ease;
    max-height: 400px;
}

.mobile-menu-enter-from,
.mobile-menu-leave-to {
    opacity: 0;
    max-height: 0;
}

/* FAQ accordion */
.faq-enter-active,
.faq-leave-active {
    transition:
        opacity 300ms ease,
        max-height 300ms ease;
    max-height: 250px;
}

.faq-enter-from,
.faq-leave-to {
    opacity: 0;
    max-height: 0;
}

/* CTA floating animation */
.cta-sparkle {
    animation: float-sparkle 4s ease-in-out infinite;
}

@keyframes float-sparkle {
    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-7px);
    }
}

@media (prefers-reduced-motion: reduce) {
    [data-reveal] {
        opacity: 1;
        transform: none;
        transition: none;
    }

    .cta-sparkle {
        animation: none;
    }
}
</style>
