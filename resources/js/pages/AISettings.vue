<script setup>
import { computed, onMounted, ref, watch } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";
import { useTheme } from "@/composables/useTheme";

const router = useRouter();
const { isDark } = useTheme();

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || "/api",
    headers: { Accept: "application/json" },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem("accessToken");
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

const defaults = () => ({
    ai_enabled: false,
    reply_tone: "professional",
    reply_language: "English",
    return_window_days: 7,
    return_policy: "",
    refund_policy: "",
    custom_instructions: "",
    auto_reply_enabled: false,
});

const stores = ref([]);
const selectedStoreId = ref("");
const settings = ref(defaults());

const loadingStores = ref(false);
const loadingSettings = ref(false);
const saving = ref(false);
const error = ref("");
const success = ref("");

const tones = [
    {
        value: "professional",
        title: "Professional",
        description: "Clear, polished and business-friendly replies.",
    },
    {
        value: "friendly",
        title: "Friendly",
        description: "Warm, approachable and conversational replies.",
    },
    {
        value: "empathetic",
        title: "Empathetic",
        description: "Understanding and reassuring customer support.",
    },
    {
        value: "concise",
        title: "Concise",
        description: "Short, direct and helpful responses.",
    },
];

const languages = [
    "English",
    "Urdu",
    "Hindi",
    "Arabic",
    "French",
    "German",
    "Spanish",
    "Italian",
    "Portuguese",
    "Dutch",
];

const selectedStore = computed(() =>
    stores.value.find(
        (store) => String(store.id) === String(selectedStoreId.value),
    ),
);

const storeName = (store) =>
    store?.store_name || store?.name || `Store #${store?.id}`;

const cardClass = computed(() =>
    isDark.value
        ? "border-slate-700 bg-slate-900 text-slate-100"
        : "border-slate-200 bg-white text-slate-900",
);

const inputClass = computed(() =>
    isDark.value
        ? "border-slate-700 bg-slate-950 text-white placeholder:text-slate-500 focus:border-indigo-400"
        : "border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 focus:border-indigo-500",
);

const mutedClass = computed(() =>
    isDark.value ? "text-slate-400" : "text-slate-500",
);

const getError = (err, fallback) => {
    if (err.response?.status === 401) {
        localStorage.removeItem("accessToken");
        router.push("/login");
        return "Session expired. Please log in again.";
    }

    const data = err.response?.data;
    const firstError = data?.errors
        ? Object.values(data.errors).flat()[0]
        : null;

    return firstError || data?.message || fallback;
};

const loadStores = async () => {
    loadingStores.value = true;
    error.value = "";
    success.value = "";

    try {
        const response = await api.get("/stores");
        const data = response.data;

        const items = Array.isArray(data)
            ? data
            : Array.isArray(data?.stores)
              ? data.stores
              : Array.isArray(data?.data)
                ? data.data
                : [];

        stores.value = items;

        if (!items.length) {
            selectedStoreId.value = "";
            settings.value = defaults();
            return;
        }

        const exists = items.some(
            (store) => String(store.id) === String(selectedStoreId.value),
        );

        if (!exists) {
            selectedStoreId.value = String(items[0].id);
        } else {
            await loadSettings();
        }
    } catch (err) {
        error.value = getError(err, "Failed to load stores.");
    } finally {
        loadingStores.value = false;
    }
};

let settingsRequestId = 0;

const loadSettings = async () => {
    const storeId = selectedStoreId.value;
    const requestId = ++settingsRequestId;

    settings.value = defaults();
    error.value = "";
    success.value = "";

    if (!storeId) return;

    loadingSettings.value = true;

    try {
        const response = await api.get(`/ai/settings/${storeId}`);

        if (requestId !== settingsRequestId) return;

        settings.value = {
            ...defaults(),
            ...(response.data?.settings || {}),
            auto_reply_enabled: false,
        };
    } catch (err) {
        if (requestId !== settingsRequestId) return;
        error.value = getError(err, "Failed to load AI settings.");
    } finally {
        if (requestId === settingsRequestId) loadingSettings.value = false;
    }
};

watch(selectedStoreId, (newId, oldId) => {
    if (newId !== oldId) loadSettings();
});

const saveSettings = async () => {
    if (!selectedStoreId.value || saving.value || loadingSettings.value) return;

    error.value = "";
    success.value = "";

    const days = Number(settings.value.return_window_days);

    if (
        !Number.isInteger(days) ||
        days < 0 ||
        days > 365 ||
        settings.value.return_window_days === ""
    ) {
        error.value = "Return window must be a whole number between 0 and 365.";
        return;
    }

    for (const key of [
        "return_policy",
        "refund_policy",
        "custom_instructions",
    ]) {
        if (String(settings.value[key] || "").length > 10000) {
            error.value =
                "Policy and custom instruction fields cannot exceed 10,000 characters.";
            return;
        }
    }

    const storeId = selectedStoreId.value;

    const payload = {
        ai_enabled: Boolean(settings.value.ai_enabled),
        reply_tone: settings.value.reply_tone,
        reply_language: settings.value.reply_language,
        return_window_days: days,
        return_policy: settings.value.return_policy || "",
        refund_policy: settings.value.refund_policy || "",
        custom_instructions: settings.value.custom_instructions || "",
    };

    saving.value = true;

    try {
        const response = await api.put(`/ai/settings/${storeId}`, payload);

        if (String(selectedStoreId.value) !== String(storeId)) return;

        if (response.data?.success === false) {
            throw new Error(
                response.data.message || "Failed to save settings.",
            );
        }

        settings.value = {
            ...defaults(),
            ...(response.data?.settings || payload),
            auto_reply_enabled: false,
        };

        success.value =
            response.data?.message || "AI settings saved successfully.";
    } catch (err) {
        if (String(selectedStoreId.value) !== String(storeId)) return;
        error.value =
            err.message && !err.response
                ? err.message
                : getError(err, "Failed to save AI settings.");
    } finally {
        saving.value = false;
    }
};

onMounted(loadStores);
</script>

<template>
    <div
        class="min-h-screen px-4 py-7 transition-colors sm:px-6 lg:px-8"
        :class="
            isDark ? 'bg-slate-950 text-white' : 'bg-slate-50 text-slate-900'
        "
    >
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-indigo-500">
                        CodeAgiles AfterSale AI
                    </p>
                    <h1 class="mt-1 text-3xl font-bold">
                        AI Assistant Settings
                    </h1>
                    <p class="mt-2 text-sm" :class="mutedClass">
                        Configure your store's AI-powered customer support
                        drafts.
                    </p>
                </div>

                <button
                    type="button"
                    :disabled="loadingStores || loadingSettings || saving"
                    @click="loadStores"
                    class="rounded-xl border px-5 py-2.5 text-sm font-semibold transition hover:border-indigo-500 disabled:opacity-50"
                    :class="cardClass"
                >
                    {{ loadingStores ? "Refreshing..." : "↻ Refresh" }}
                </button>
            </div>

            <div
                v-if="error"
                role="alert"
                class="rounded-xl border border-red-300 bg-red-50 px-5 py-4 text-sm text-red-700"
            >
                {{ error }}
            </div>

            <div
                v-if="success"
                role="status"
                class="rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-4 text-sm text-emerald-700"
            >
                {{ success }}
            </div>

            <section
                class="rounded-2xl border p-6 shadow-sm"
                :class="cardClass"
            >
                <h2 class="text-lg font-bold">Select Store</h2>
                <p class="mb-4 mt-1 text-sm" :class="mutedClass">
                    Each store has its own AI configuration.
                </p>

                <select
                    v-model="selectedStoreId"
                    :disabled="loadingStores || saving"
                    class="w-full rounded-xl border px-4 py-3 outline-none transition sm:max-w-md"
                    :class="inputClass"
                >
                    <option value="">Select a store</option>
                    <option
                        v-for="store in stores"
                        :key="store.id"
                        :value="String(store.id)"
                    >
                        {{ storeName(store) }}
                    </option>
                </select>

                <p
                    v-if="!loadingStores && !stores.length"
                    class="mt-3 text-sm"
                    :class="mutedClass"
                >
                    No stores found. Create a store to configure AI settings.
                </p>
                <p
                    v-else-if="selectedStore"
                    class="mt-3 text-xs"
                    :class="mutedClass"
                >
                    Configuring: {{ storeName(selectedStore) }}
                </p>
            </section>

            <div v-if="selectedStoreId" class="space-y-6">
                <div
                    v-if="loadingSettings"
                    class="py-10 text-center"
                    :class="mutedClass"
                >
                    Loading AI settings...
                </div>

                <template v-else>
                    <section
                        class="rounded-2xl border p-6 shadow-sm"
                        :class="cardClass"
                    >
                        <div class="flex items-center justify-between gap-5">
                            <div>
                                <h2 class="text-lg font-bold">
                                    Enable AI Assistant
                                </h2>
                                <p class="mt-1 text-sm" :class="mutedClass">
                                    Allow AI to generate customer support reply
                                    drafts.
                                </p>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="settings.ai_enabled"
                                aria-label="Enable AI Assistant"
                                @click="
                                    settings.ai_enabled = !settings.ai_enabled
                                "
                                class="relative h-7 w-12 shrink-0 rounded-full transition-colors"
                                :class="
                                    settings.ai_enabled
                                        ? 'bg-indigo-600'
                                        : 'bg-slate-400'
                                "
                            >
                                <span
                                    class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow transition-transform"
                                    :class="
                                        settings.ai_enabled
                                            ? 'translate-x-5'
                                            : ''
                                    "
                                ></span>
                            </button>
                        </div>

                        <div
                            class="mt-5 rounded-xl px-4 py-3 text-sm"
                            :class="
                                settings.ai_enabled
                                    ? 'bg-indigo-500/10 text-indigo-500'
                                    : isDark
                                      ? 'bg-slate-800 text-slate-300'
                                      : 'bg-slate-100 text-slate-600'
                            "
                        >
                            {{
                                settings.ai_enabled
                                    ? "AI Assistant is enabled. Customer replies still require your approval."
                                    : "AI Assistant is currently disabled for this store."
                            }}
                        </div>
                    </section>

                    <section
                        class="rounded-2xl border p-6 shadow-sm"
                        :class="cardClass"
                    >
                        <h2 class="text-lg font-bold">Reply Tone</h2>
                        <p class="mt-1 text-sm" :class="mutedClass">
                            Choose how the AI communicates with your customers.
                        </p>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <label
                                v-for="tone in tones"
                                :key="tone.value"
                                class="flex cursor-pointer gap-3 rounded-xl border p-4 transition"
                                :class="
                                    settings.reply_tone === tone.value
                                        ? 'border-indigo-500 bg-indigo-500/10'
                                        : isDark
                                          ? 'border-slate-700 hover:border-slate-500'
                                          : 'border-slate-200 hover:border-slate-400'
                                "
                            >
                                <input
                                    v-model="settings.reply_tone"
                                    type="radio"
                                    name="reply_tone"
                                    :value="tone.value"
                                    class="mt-1 accent-indigo-600"
                                />
                                <span>
                                    <span class="block font-semibold">{{
                                        tone.title
                                    }}</span>
                                    <span
                                        class="mt-1 block text-sm"
                                        :class="mutedClass"
                                    >
                                        {{ tone.description }}
                                    </span>
                                </span>
                            </label>
                        </div>
                    </section>

                    <section
                        class="rounded-2xl border p-6 shadow-sm"
                        :class="cardClass"
                    >
                        <h2 class="text-lg font-bold">
                            Language & Return Window
                        </h2>

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <label
                                    for="replyLanguage"
                                    class="mb-2 block text-sm font-semibold"
                                >
                                    Reply Language
                                </label>
                                <select
                                    id="replyLanguage"
                                    v-model="settings.reply_language"
                                    class="w-full rounded-xl border px-4 py-3 outline-none"
                                    :class="inputClass"
                                >
                                    <option
                                        v-for="language in languages"
                                        :key="language"
                                        :value="language"
                                    >
                                        {{ language }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label
                                    for="returnDays"
                                    class="mb-2 block text-sm font-semibold"
                                >
                                    Return Window (Days)
                                </label>
                                <input
                                    id="returnDays"
                                    v-model.number="settings.return_window_days"
                                    type="number"
                                    min="0"
                                    max="365"
                                    step="1"
                                    class="w-full rounded-xl border px-4 py-3 outline-none"
                                    :class="inputClass"
                                />
                                <p class="mt-2 text-xs" :class="mutedClass">
                                    Allowed range: 0–365 days.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-2xl border p-6 shadow-sm"
                        :class="cardClass"
                    >
                        <h2 class="text-lg font-bold">Store Policies</h2>
                        <p class="mt-1 text-sm" :class="mutedClass">
                            AI uses these policies when preparing customer
                            support replies.
                        </p>

                        <div class="mt-5 space-y-5">
                            <div>
                                <label
                                    for="returnPolicy"
                                    class="mb-2 block text-sm font-semibold"
                                >
                                    Return Policy
                                </label>
                                <textarea
                                    id="returnPolicy"
                                    v-model="settings.return_policy"
                                    rows="5"
                                    maxlength="10000"
                                    placeholder="Enter your store's return policy..."
                                    class="w-full resize-y rounded-xl border px-4 py-3 outline-none"
                                    :class="inputClass"
                                ></textarea>
                                <p
                                    class="mt-1 text-right text-xs"
                                    :class="mutedClass"
                                >
                                    {{ settings.return_policy.length }} / 10000
                                </p>
                            </div>

                            <div>
                                <label
                                    for="refundPolicy"
                                    class="mb-2 block text-sm font-semibold"
                                >
                                    Refund Policy
                                </label>
                                <textarea
                                    id="refundPolicy"
                                    v-model="settings.refund_policy"
                                    rows="5"
                                    maxlength="10000"
                                    placeholder="Enter your store's refund policy..."
                                    class="w-full resize-y rounded-xl border px-4 py-3 outline-none"
                                    :class="inputClass"
                                ></textarea>
                                <p
                                    class="mt-1 text-right text-xs"
                                    :class="mutedClass"
                                >
                                    {{ settings.refund_policy.length }} / 10000
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-2xl border p-6 shadow-sm"
                        :class="cardClass"
                    >
                        <h2 class="text-lg font-bold">Custom Instructions</h2>
                        <p class="mt-1 text-sm" :class="mutedClass">
                            Add extra guidance for AI reply style and customer
                            service. Instructions cannot override saved policies
                            or safety rules.
                        </p>

                        <textarea
                            v-model="settings.custom_instructions"
                            rows="6"
                            maxlength="10000"
                            placeholder="Example: Keep replies polite, brief, and helpful..."
                            class="mt-5 w-full resize-y rounded-xl border px-4 py-3 outline-none"
                            :class="inputClass"
                        ></textarea>

                        <p class="mt-1 text-right text-xs" :class="mutedClass">
                            {{ settings.custom_instructions.length }} / 10000
                        </p>
                    </section>

                    <section
                        class="rounded-2xl border p-6 shadow-sm"
                        :class="cardClass"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-bold">
                                    Automatic Customer Replies
                                </h2>
                                <p class="mt-1 text-sm" :class="mutedClass">
                                    Automatic sending is not available yet.
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-500"
                            >
                                Coming Later
                            </span>
                        </div>

                        <div class="mt-5 flex items-center gap-3 opacity-60">
                            <div
                                class="relative h-7 w-12 rounded-full bg-slate-400"
                            >
                                <span
                                    class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white"
                                ></span>
                            </div>
                            <span class="text-sm">Auto-reply disabled</span>
                        </div>

                        <p
                            class="mt-4 rounded-xl bg-indigo-500/10 p-4 text-sm text-indigo-500"
                        >
                            Gemini generates drafts only. Store owners must
                            review and send customer replies manually.
                        </p>
                    </section>

                    <div
                        class="flex flex-wrap items-center justify-between gap-4 pb-8"
                    >
                        <p class="text-sm" :class="mutedClass">
                            Changes are applied after saving.
                        </p>

                        <button
                            type="button"
                            :disabled="
                                saving || loadingSettings || !selectedStoreId
                            "
                            @click="saveSettings"
                            class="rounded-xl bg-indigo-600 px-7 py-3 font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                saving
                                    ? "Saving Settings..."
                                    : "Save AI Settings"
                            }}
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
