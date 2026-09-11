import { computed, onMounted, onUnmounted, ref } from 'vue';

export const THEMES = [
    { id: 'classic', label: 'Klasik' },
    { id: 'forest', label: 'Hutan' },
    { id: 'amber', label: 'Amber' },
    { id: 'violet', label: 'Violet' },
    { id: 'ocean', label: 'Laut' },
    { id: 'rose', label: 'Mawar' },
    { id: 'midnight', label: 'Malam' },
];

const STORAGE_KEY = 'siupk-theme';
const DEFAULT_THEME = 'classic';
const themeIds = new Set(THEMES.map((t) => t.id));

const theme = ref(readStored());

function readStored() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw && themeIds.has(raw)) return raw;
    } catch {
        /* private mode */
    }
    return DEFAULT_THEME;
}

function apply(id) {
    const next = themeIds.has(id) ? id : DEFAULT_THEME;
    document.documentElement.setAttribute('data-theme', next);
    try {
        localStorage.setItem(STORAGE_KEY, next);
    } catch {
        /* ignore */
    }
    theme.value = next;
}

export function useTheme() {
    const current = computed(() => THEMES.find((t) => t.id === theme.value) ?? THEMES[0]);

    function setTheme(id) {
        apply(id);
    }

    function onStorage(e) {
        if (e.key === STORAGE_KEY && e.newValue && themeIds.has(e.newValue)) {
            document.documentElement.setAttribute('data-theme', e.newValue);
            theme.value = e.newValue;
        }
    }

    onMounted(() => {
        apply(theme.value);
        window.addEventListener('storage', onStorage);
    });

    onUnmounted(() => {
        window.removeEventListener('storage', onStorage);
    });

    return { theme, themes: THEMES, current, setTheme };
}
