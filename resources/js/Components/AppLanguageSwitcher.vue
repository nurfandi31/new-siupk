<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';
import { useI18n } from '../i18n';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
        validator: (value) => ['left', 'right'].includes(value),
    },
    tone: {
        type: String,
        default: 'surface',
        validator: (value) => ['surface', 'light', 'dark'].includes(value),
    },
    // A filled, high-contrast circle for public/landing headers where the
    // switcher sits next to a call-to-action button.
    accent: {
        type: Boolean,
        default: false,
    },
});

const { locale, locales, setLocale, t } = useI18n();
const open = ref(false);
const root = ref(null);

const current = computed(() => locales.find((item) => item.code === locale.value) || locales[0]);

const triggerClass = computed(() => {
    if (props.accent) {
        return 'bg-gradient-to-br from-primary to-secondary text-on-primary shadow-md shadow-primary/25 ring-1 ring-white/25 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/35';
    }

    if (props.tone === 'light') {
        return 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 hover:text-emerald-700';
    }

    if (props.tone === 'dark') {
        return 'bg-white/10 text-white ring-1 ring-white/20 hover:bg-white/20';
    }

    return 'text-on-surface-variant ring-1 ring-outline-variant hover:bg-surface-container hover:text-primary';
});

function choose(code) {
    const changed = code !== locale.value;
    setLocale(code);
    open.value = false;

    // Sebagian label disusun saat setup (mis. kolom tabel & menu), jadi muat
    // ulang halaman agar seluruh UI langsung memakai bahasa yang dipilih.
    if (changed) {
        router.reload({ preserveScroll: true, preserveState: false });
    }
}

function onDocumentPointerDown(event) {
    if (!open.value) return;
    if (root.value?.contains(event.target)) return;
    open.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape' && open.value) {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="inline-flex size-10 shrink-0 items-center justify-center rounded-full shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            :class="triggerClass"
            :aria-label="`${t('Ganti bahasa')} (${current.short})`"
            :title="`${t('Ganti bahasa')} (${current.short})`"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="open = !open"
        >
            <AppIcon :name="accent ? 'language' : 'translate'" class="text-xl leading-none" />
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="-translate-y-1 scale-95 opacity-0"
            enter-to-class="translate-y-0 scale-100 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="translate-y-0 scale-100 opacity-100"
            leave-to-class="-translate-y-1 scale-95 opacity-0"
        >
            <div
                v-if="open"
                role="menu"
                :aria-label="t('Pilih bahasa')"
                class="absolute top-full z-[80] mt-2 w-56 origin-top overflow-hidden rounded-2xl floating-shadow bg-surface-container-lowest ring-1 ring-outline-variant/50"
                :class="props.align === 'left' ? 'left-0' : 'right-0'"
            >
                <p class="inset-divider bg-surface-container-low/50 px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                    {{ t('Pilih bahasa') }}
                </p>
                <div class="p-1">
                    <button
                        v-for="item in locales"
                        :key="item.code"
                        type="button"
                        role="menuitemradio"
                        :aria-checked="locale === item.code"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors hover:bg-surface-container-low"
                        :class="locale === item.code && 'bg-primary-container/40 font-semibold'"
                        @click="choose(item.code)"
                    >
                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-surface-container text-[10px] font-black text-primary ring-1 ring-outline-variant">
                            {{ item.short }}
                        </span>
                        <span class="min-w-0 flex-1 truncate text-sm text-primary">{{ item.label }}</span>
                        <AppIcon v-if="locale === item.code" name="check_circle" filled class="text-secondary" />
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
