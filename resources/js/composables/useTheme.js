import { ref, computed } from "vue";

// Shared theme state across all Vue components
const STORAGE_KEY = "codeagiles-theme";

const getInitialTheme = () => {
    if (typeof window === "undefined") {
        return "dark";
    }

    const savedTheme = localStorage.getItem(STORAGE_KEY);

    return savedTheme === "light" || savedTheme === "dark"
        ? savedTheme
        : "dark";
};

const theme = ref(getInitialTheme());

const isDark = computed(() => theme.value === "dark");

function applyTheme(selectedTheme) {
    if (typeof document === "undefined") return;

    document.documentElement.setAttribute("data-theme", selectedTheme);

    // Tailwind CSS dark mode support
    document.documentElement.classList.toggle("dark", selectedTheme === "dark");
}

function setTheme(selectedTheme) {
    if (selectedTheme !== "light" && selectedTheme !== "dark") {
        return;
    }

    theme.value = selectedTheme;

    localStorage.setItem(STORAGE_KEY, selectedTheme);

    applyTheme(selectedTheme);
}

function toggleTheme() {
    const nextTheme = theme.value === "dark" ? "light" : "dark";

    setTheme(nextTheme);
}

function initializeTheme() {
    applyTheme(theme.value);
}

// Apply theme when module loads
initializeTheme();

export function useTheme() {
    return {
        theme,
        isDark,
        setTheme,
        toggleTheme,
        initializeTheme,
    };
}
