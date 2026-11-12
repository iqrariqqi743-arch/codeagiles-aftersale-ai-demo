<script setup>
import { ref, reactive, onMounted } from "vue";
import { useRouter, RouterLink } from "vue-router";
import { Eye, EyeOff, LoaderCircle, LogIn } from "lucide-vue-next";

const router = useRouter();

const passwordRef = ref(null);

const form = reactive({
    email: "",
    password: "",
});

const rememberMe = ref(false);
const showPassword = ref(false);
const loading = ref(false);
const error = ref("");

/* Restore remembered email */
onMounted(() => {
    const savedEmail = localStorage.getItem("rememberedEmail");

    if (savedEmail) {
        form.email = savedEmail;
        rememberMe.value = true;
    }
});

/* Clear error when user types */
function handleChange() {
    error.value = "";
}

/* Remember Me */
function handleRememberMe(event) {
    rememberMe.value = event.target.checked;

    if (!rememberMe.value) {
        localStorage.removeItem("rememberedEmail");
    }
}

/* Laravel API Login */
async function handleSubmit() {
    if (loading.value) return;

    loading.value = true;
    error.value = "";

    const email = form.email.trim();
    const password = passwordRef.value?.value || form.password;

    try {
        const response = await fetch("/api/auth/login", {
            method: "POST",

            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },

            body: JSON.stringify({
                email,
                password,
            }),
        });

        const data = await response.json();

        if (!response.ok || !data?.success) {
            error.value = data?.message || "Login failed.";
            return;
        }

        if (!data?.token || !data?.user) {
            error.value = "Invalid login response.";
            return;
        }

        const role = String(data.user?.role?.name || data.user?.role || "")
            .toLowerCase()
            .replace(/[\s-]/g, "_");

        if (
            role !== "owner" &&
            role !== "super_admin" &&
            role !== "superadmin"
        ) {
            error.value = "Your account does not have dashboard access.";
            return;
        }

        /* Remember only email */
        if (rememberMe.value) {
            localStorage.setItem("rememberedEmail", email);
        } else {
            localStorage.removeItem("rememberedEmail");
        }

        /* Existing authentication storage */
        localStorage.setItem("accessToken", data.token);
        localStorage.setItem("user", JSON.stringify(data.user));

        /* Role-based redirect */
        if (role === "super_admin" || role === "superadmin") {
            router.replace("/super-admin");
        } else {
            router.replace("/dashboard");
        }
    } catch (err) {
        console.error("Login Error:", err);

        error.value = "Unable to connect to the server. Please try again.";
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <main
        class="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-12"
    >
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="mb-8 text-center">
                <RouterLink to="/">
                    <h1 class="text-3xl font-bold text-white">
                        CodeAgiles
                        <span class="text-cyan-400">AfterSale AI</span>
                    </h1>
                </RouterLink>

                <p class="mt-3 text-slate-400">
                    Sign in to manage your customer experience
                </p>
            </div>

            <!-- Login Form -->
            <form
                id="login-form"
                name="login"
                method="post"
                autocomplete="on"
                class="space-y-5 rounded-2xl border border-slate-800 bg-slate-900 p-8"
                @submit.prevent="handleSubmit"
            >
                <h2 class="text-2xl font-semibold text-white">Welcome back</h2>

                <!-- Error -->
                <div
                    v-if="error"
                    role="alert"
                    class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-400"
                >
                    {{ error }}
                </div>

                <!-- Email -->
                <div>
                    <label
                        for="email"
                        class="mb-2 block text-sm text-slate-300"
                    >
                        Email address
                    </label>

                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        name="username"
                        required
                        autocomplete="username"
                        placeholder="you@company.com"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-400"
                        @input="handleChange"
                    />
                </div>

                <!-- Password -->
                <div>
                    <label
                        for="password"
                        class="mb-2 block text-sm text-slate-300"
                    >
                        Password
                    </label>

                    <div class="relative">
                        <input
                            id="password"
                            ref="passwordRef"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 pr-12 text-white outline-none focus:border-cyan-400"
                            @input="handleChange"
                        />

                        <button
                            type="button"
                            :aria-label="
                                showPassword ? 'Hide password' : 'Show password'
                            "
                            class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-400 hover:text-cyan-400"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" :size="20" />
                            <Eye v-else :size="20" />
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between gap-3">
                    <label
                        class="flex cursor-pointer select-none items-center gap-2"
                    >
                        <input
                            :checked="rememberMe"
                            type="checkbox"
                            name="rememberMe"
                            class="h-4 w-4 cursor-pointer rounded accent-cyan-500"
                            @change="handleRememberMe"
                        />

                        <span class="text-sm text-slate-300">
                            Remember me
                        </span>
                    </label>

                    <RouterLink
                        to="/forgot-password"
                        class="text-sm font-medium text-cyan-400 hover:text-cyan-300"
                    >
                        Forgot password?
                    </RouterLink>
                </div>

                <!-- Login Button -->
                <button
                    type="submit"
                    :disabled="loading"
                    class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-cyan-500 py-3 font-semibold text-slate-950 transition-colors hover:bg-cyan-400 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <LoaderCircle
                        v-if="loading"
                        :size="20"
                        class="animate-spin"
                    />

                    <LogIn v-else :size="20" />

                    {{ loading ? "Signing in..." : "Sign in" }}
                </button>

                <!-- Register -->
                <p class="text-center text-sm text-slate-400">
                    Don't have an account?

                    <RouterLink
                        to="/register"
                        class="font-medium text-cyan-400 hover:text-cyan-300"
                    >
                        Create account
                    </RouterLink>
                </p>
            </form>
        </div>
    </main>
</template>
