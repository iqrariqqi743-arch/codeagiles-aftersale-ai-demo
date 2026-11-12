<script setup>
import { reactive, ref } from "vue";
import { RouterLink, useRouter } from "vue-router";
import {
    UserRound,
    Mail,
    LockKeyhole,
    Eye,
    EyeOff,
    LoaderCircle,
    UserPlus,
    AlertCircle,
    CheckCircle2,
} from "lucide-vue-next";

const router = useRouter();

const form = reactive({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);
const loading = ref(false);
const error = ref("");
const success = ref("");
const fieldErrors = ref({});

function clearErrors() {
    error.value = "";
    fieldErrors.value = {};
}

async function handleSubmit() {
    if (loading.value) return;

    clearErrors();

    if (!form.name.trim()) {
        error.value = "Please enter your full name.";
        return;
    }

    if (form.password.length < 8) {
        error.value = "Password must be at least 8 characters.";
        return;
    }

    if (form.password !== form.password_confirmation) {
        error.value = "Passwords do not match.";
        return;
    }

    loading.value = true;

    try {
        const response = await fetch("/api/auth/register", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({
                name: form.name.trim(),
                email: form.email.trim(),
                password: form.password,
                password_confirmation: form.password_confirmation,
            }),
        });

        const data = await response.json();

        if (!response.ok || !data?.success) {
            error.value = data?.message || "Unable to create your account.";

            fieldErrors.value = data?.errors || {};
            return;
        }

        success.value = data?.message || "Account created successfully.";

        form.password = "";
        form.password_confirmation = "";

        // Registration API may return a token.
        // Do not save it here; use the normal login flow.
        // Redirect to login after success.
        router.replace({
            path: "/login",
            query: { registered: "1" },
        });
    } catch (err) {
        console.error("Registration Error:", err);
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
            <!-- LOGO -->
            <div class="mb-8 text-center">
                <RouterLink to="/">
                    <h1 class="text-3xl font-bold text-white">
                        CodeAgiles
                        <span class="text-cyan-400">AfterSale AI</span>
                    </h1>
                </RouterLink>

                <p class="mt-3 text-slate-400">
                    Create your account and get started
                </p>
            </div>

            <!-- REGISTER FORM -->
            <form
                class="space-y-5 rounded-2xl border border-slate-800 bg-slate-900 p-8"
                @submit.prevent="handleSubmit"
            >
                <!-- HEADING -->
                <div class="text-center">
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-cyan-500/10"
                    >
                        <UserPlus :size="28" class="text-cyan-400" />
                    </div>

                    <h2 class="text-2xl font-semibold text-white">
                        Create Account
                    </h2>

                    <p class="mt-2 text-sm text-slate-400">
                        Start managing your customer support with AI
                    </p>
                </div>

                <!-- ERROR MESSAGE -->
                <div
                    v-if="error"
                    role="alert"
                    class="flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-400"
                >
                    <AlertCircle :size="19" class="shrink-0" />
                    <span>{{ error }}</span>
                </div>

                <!-- SUCCESS MESSAGE -->
                <div
                    v-if="success"
                    role="status"
                    class="flex items-start gap-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-400"
                >
                    <CheckCircle2 :size="19" class="shrink-0" />
                    <span>{{ success }}</span>
                </div>

                <!-- FULL NAME -->
                <div>
                    <label
                        for="register-name"
                        class="mb-2 block text-sm text-slate-300"
                    >
                        Full Name
                    </label>

                    <div class="relative">
                        <UserRound
                            :size="19"
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"
                        />

                        <input
                            id="register-name"
                            v-model="form.name"
                            type="text"
                            name="name"
                            required
                            autocomplete="name"
                            placeholder="Enter your full name"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 py-3 pl-12 pr-4 text-white outline-none focus:border-cyan-400"
                            @input="clearErrors"
                        />
                    </div>

                    <p
                        v-if="fieldErrors.name?.length"
                        class="mt-1 text-xs text-red-400"
                    >
                        {{ fieldErrors.name[0] }}
                    </p>
                </div>

                <!-- EMAIL -->
                <div>
                    <label
                        for="register-email"
                        class="mb-2 block text-sm text-slate-300"
                    >
                        Email Address
                    </label>

                    <div class="relative">
                        <Mail
                            :size="19"
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"
                        />

                        <input
                            id="register-email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            required
                            autocomplete="email"
                            placeholder="you@company.com"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 py-3 pl-12 pr-4 text-white outline-none focus:border-cyan-400"
                            @input="clearErrors"
                        />
                    </div>

                    <p
                        v-if="fieldErrors.email?.length"
                        class="mt-1 text-xs text-red-400"
                    >
                        {{ fieldErrors.email[0] }}
                    </p>
                </div>

                <!-- PASSWORD -->
                <div>
                    <label
                        for="register-password"
                        class="mb-2 block text-sm text-slate-300"
                    >
                        Password
                    </label>

                    <div class="relative">
                        <LockKeyhole
                            :size="19"
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"
                        />

                        <input
                            id="register-password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Minimum 8 characters"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 py-3 pl-12 pr-12 text-white outline-none focus:border-cyan-400"
                            @input="clearErrors"
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

                    <p
                        v-if="fieldErrors.password?.length"
                        class="mt-1 text-xs text-red-400"
                    >
                        {{ fieldErrors.password[0] }}
                    </p>
                </div>

                <!-- CONFIRM PASSWORD -->
                <div>
                    <label
                        for="register-confirm-password"
                        class="mb-2 block text-sm text-slate-300"
                    >
                        Confirm Password
                    </label>

                    <div class="relative">
                        <LockKeyhole
                            :size="19"
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"
                        />

                        <input
                            id="register-confirm-password"
                            v-model="form.password_confirmation"
                            :type="showConfirmPassword ? 'text' : 'password'"
                            name="password_confirmation"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Confirm your password"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 py-3 pl-12 pr-12 text-white outline-none focus:border-cyan-400"
                            @input="clearErrors"
                        />

                        <button
                            type="button"
                            :aria-label="
                                showConfirmPassword
                                    ? 'Hide password'
                                    : 'Show password'
                            "
                            class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-400 hover:text-cyan-400"
                            @click="showConfirmPassword = !showConfirmPassword"
                        >
                            <EyeOff v-if="showConfirmPassword" :size="20" />
                            <Eye v-else :size="20" />
                        </button>
                    </div>
                </div>

                <!-- REGISTER BUTTON -->
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

                    <UserPlus v-else :size="20" />

                    {{ loading ? "Creating Account..." : "Create Account" }}
                </button>

                <!-- LOGIN LINK -->
                <p class="text-center text-sm text-slate-400">
                    Already have an account?

                    <RouterLink
                        to="/login"
                        class="font-medium text-cyan-400 hover:text-cyan-300"
                    >
                        Sign in
                    </RouterLink>
                </p>
            </form>

            <p class="mt-6 text-center text-xs text-slate-500">
                CodeAgiles AfterSale AI • Secure Registration
            </p>
        </div>
    </main>
</template>
