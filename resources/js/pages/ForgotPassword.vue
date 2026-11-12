<script setup>
import { ref } from "vue";
import { RouterLink } from "vue-router";
import {
    Mail,
    ArrowLeft,
    Send,
    LoaderCircle,
    CheckCircle2,
    AlertCircle,
    KeyRound,
} from "lucide-vue-next";

const email = ref("");
const loading = ref(false);
const success = ref("");
const error = ref("");

async function handleSubmit() {
    if (loading.value) return;

    error.value = "";
    success.value = "";

    const userEmail = email.value.trim();

    if (!userEmail) {
        error.value = "Please enter your email address.";
        return;
    }

    loading.value = true;

    try {
        const response = await fetch("/api/auth/forgot-password", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({
                email: userEmail,
            }),
        });

        const data = await response.json();

        if (!response.ok || !data?.success) {
            error.value =
                data?.message || "Unable to send password reset link.";
            return;
        }

        success.value =
            data?.message ||
            "If your email is registered, a password reset link will be sent.";
    } catch (err) {
        console.error("Forgot Password Error:", err);
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
                    Secure password recovery for your account
                </p>
            </div>

            <!-- FORGOT PASSWORD FORM -->
            <form
                class="space-y-5 rounded-2xl border border-slate-800 bg-slate-900 p-8"
                @submit.prevent="handleSubmit"
            >
                <!-- ICON -->
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-cyan-500/10"
                >
                    <KeyRound :size="28" class="text-cyan-400" />
                </div>

                <div class="text-center">
                    <h2 class="text-2xl font-semibold text-white">
                        Forgot Password?
                    </h2>

                    <p class="mt-3 text-sm leading-6 text-slate-400">
                        Enter your registered email address. We'll send you a
                        link to reset your password.
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

                <!-- EMAIL INPUT -->
                <div>
                    <label
                        for="forgot-email"
                        class="mb-2 block text-sm text-slate-300"
                    >
                        Email address
                    </label>

                    <div class="relative">
                        <Mail
                            :size="19"
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"
                        />

                        <input
                            id="forgot-email"
                            v-model="email"
                            type="email"
                            name="email"
                            required
                            autocomplete="email"
                            placeholder="you@company.com"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 py-3 pl-12 pr-4 text-white outline-none focus:border-cyan-400"
                            @input="
                                error = '';
                                success = '';
                            "
                        />
                    </div>
                </div>

                <!-- SUBMIT BUTTON -->
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

                    <Send v-else :size="20" />

                    {{ loading ? "Sending..." : "Send Reset Link" }}
                </button>

                <!-- BACK TO LOGIN -->
                <RouterLink
                    to="/login"
                    class="flex items-center justify-center gap-2 text-sm font-medium text-cyan-400 hover:text-cyan-300"
                >
                    <ArrowLeft :size="18" />
                    Back to Login
                </RouterLink>
            </form>

            <p class="mt-6 text-center text-xs text-slate-500">
                Secure password recovery • CodeAgiles AfterSale AI
            </p>
        </div>
    </main>
</template>
