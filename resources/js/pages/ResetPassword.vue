<script setup>
import { computed, reactive, ref } from "vue";
import { RouterLink, useRoute, useRouter } from "vue-router";
import {
    KeyRound,
    Eye,
    EyeOff,
    LoaderCircle,
    CheckCircle2,
    AlertCircle,
    ArrowLeft,
    LockKeyhole,
} from "lucide-vue-next";

const route = useRoute();
const router = useRouter();

const form = reactive({
    password: "",
    password_confirmation: "",
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);
const loading = ref(false);
const error = ref("");
const success = ref("");

const email = computed(() => {
    return typeof route.query.email === "string" ? route.query.email : "";
});

const token = computed(() => {
    return typeof route.query.token === "string" ? route.query.token : "";
});

const isValidLink = computed(() => {
    return Boolean(email.value && token.value);
});

async function handleSubmit() {
    if (loading.value || success.value) return;

    error.value = "";

    if (!isValidLink.value) {
        error.value = "Invalid password reset link.";
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
        const response = await fetch("/api/auth/reset-password", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({
                email: email.value,
                token: token.value,
                password: form.password,
                password_confirmation: form.password_confirmation,
            }),
        });

        const data = await response.json();

        if (!response.ok || !data?.success) {
            error.value =
                data?.message ||
                Object.values(data?.errors || {}).flat()[0] ||
                "Unable to reset your password.";
            return;
        }

        success.value =
            data?.message || "Your password has been reset successfully.";

        form.password = "";
        form.password_confirmation = "";
    } catch (err) {
        console.error("Reset Password Error:", err);
        error.value = "Unable to connect to the server. Please try again.";
    } finally {
        loading.value = false;
    }
}

function goToLogin() {
    router.replace("/login");
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
                    Securely reset your account password
                </p>
            </div>

            <!-- RESET PASSWORD CARD -->
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
                        Reset Password
                    </h2>

                    <p class="mt-3 text-sm text-slate-400">
                        Create a new password for your account.
                    </p>
                </div>

                <!-- INVALID LINK -->
                <div
                    v-if="!isValidLink"
                    role="alert"
                    class="flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-400"
                >
                    <AlertCircle :size="19" class="shrink-0" />
                    <span>
                        This reset link is invalid. Please request a new one.
                    </span>
                </div>

                <!-- ERROR -->
                <div
                    v-if="error"
                    role="alert"
                    class="flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-400"
                >
                    <AlertCircle :size="19" class="shrink-0" />
                    <span>{{ error }}</span>
                </div>

                <!-- SUCCESS -->
                <div
                    v-if="success"
                    role="status"
                    class="flex items-start gap-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-400"
                >
                    <CheckCircle2 :size="19" class="shrink-0" />
                    <span>{{ success }}</span>
                </div>

                <template v-if="isValidLink && !success">
                    <!-- EMAIL -->
                    <div>
                        <label class="mb-2 block text-sm text-slate-300">
                            Account email
                        </label>

                        <input
                            :value="email"
                            type="email"
                            readonly
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-3 text-slate-400 outline-none"
                        />
                    </div>

                    <!-- NEW PASSWORD -->
                    <div>
                        <label
                            for="new-password"
                            class="mb-2 block text-sm text-slate-300"
                        >
                            New Password
                        </label>

                        <div class="relative">
                            <LockKeyhole
                                :size="19"
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"
                            />

                            <input
                                id="new-password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Enter new password"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 py-3 pl-12 pr-12 text-white outline-none focus:border-cyan-400"
                                @input="error = ''"
                            />

                            <button
                                type="button"
                                :aria-label="
                                    showPassword
                                        ? 'Hide password'
                                        : 'Show password'
                                "
                                class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-400 hover:text-cyan-400"
                                @click="showPassword = !showPassword"
                            >
                                <EyeOff v-if="showPassword" :size="20" />
                                <Eye v-else :size="20" />
                            </button>
                        </div>
                    </div>

                    <!-- CONFIRM PASSWORD -->
                    <div>
                        <label
                            for="confirm-password"
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
                                id="confirm-password"
                                v-model="form.password_confirmation"
                                :type="
                                    showConfirmPassword ? 'text' : 'password'
                                "
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Confirm new password"
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 py-3 pl-12 pr-12 text-white outline-none focus:border-cyan-400"
                                @input="error = ''"
                            />

                            <button
                                type="button"
                                :aria-label="
                                    showConfirmPassword
                                        ? 'Hide password'
                                        : 'Show password'
                                "
                                class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-400 hover:text-cyan-400"
                                @click="
                                    showConfirmPassword = !showConfirmPassword
                                "
                            >
                                <EyeOff v-if="showConfirmPassword" :size="20" />
                                <Eye v-else :size="20" />
                            </button>
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

                        <KeyRound v-else :size="20" />

                        {{
                            loading ? "Resetting Password..." : "Reset Password"
                        }}
                    </button>
                </template>

                <!-- LOGIN AFTER SUCCESS -->
                <button
                    v-if="success"
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-cyan-500 py-3 font-semibold text-slate-950 hover:bg-cyan-400"
                    @click="goToLogin"
                >
                    Continue to Login
                </button>

                <!-- BACK TO LOGIN -->
                <RouterLink
                    v-if="!success"
                    to="/login"
                    class="flex items-center justify-center gap-2 text-sm font-medium text-cyan-400 hover:text-cyan-300"
                >
                    <ArrowLeft :size="18" />
                    Back to Login
                </RouterLink>
            </form>
        </div>
    </main>
</template>
