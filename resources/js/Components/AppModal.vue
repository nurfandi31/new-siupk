<script setup>
import { useI18n } from '../i18n';
const { t } = useI18n();
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
    title: { type: String, required: true },
    closeable: { type: Boolean, default: true },
    centered: { type: Boolean, default: false },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg', 'full', 'fullscreen'].includes(value),
    },
});

const sizeClass = {
    sm: 'max-w-md',
    md: 'max-w-2xl',
    lg: 'max-w-4xl',
    full: 'max-w-[min(96rem,calc(100vw-2rem))]',
    fullscreen: 'max-w-none',
};
const containerClass = {
    sm: 'p-4',
    md: 'p-4',
    lg: 'p-4',
    full: 'p-4 sm:p-6',
    fullscreen: 'p-0',
};
const panelSizeClass = {
    sm: 'max-h-[calc(100vh-2rem)] rounded-2xl',
    md: 'max-h-[calc(100vh-2rem)] rounded-2xl',
    lg: 'max-h-[calc(100vh-2rem)] rounded-2xl',
    full: 'max-h-[calc(100vh-2rem)] rounded-2xl',
    fullscreen: 'h-screen max-h-screen rounded-none',
};
// Wrapper alignment — dialog (sm/lg/full) selalu center di tengah layar.
// md tetap top-aligned sheet, fullscreen mengambil alih seluruh viewport.
const wrapperAlignClass = {
    sm: 'items-center justify-center',
    md: 'items-stretch justify-stretch',
    lg: 'items-center justify-center',
    full: 'items-center justify-center',
    fullscreen: 'items-stretch justify-stretch',
};
const alignmentClass = computed(() =>
    props.centered ? 'items-center justify-center' : (wrapperAlignClass[props.size] ?? wrapperAlignClass.md),
);
// Backdrop overlay — fullscreen mode polos tanpa overlay/blur karena panel sudah
// menutupi seluruh viewport. Mode lain memakai overlay semi-transparan + blur.
const backdropClass = {
    sm: 'bg-primary/45 backdrop-blur-xs',
    md: 'bg-primary/45 backdrop-blur-xs',
    lg: 'bg-primary/45 backdrop-blur-xs',
    full: 'bg-primary/45 backdrop-blur-xs',
    fullscreen: '',
};

const titleId = useId();
const panel = ref(null);
let previousFocus = null;
let previousOverflow = '';

function close() {
    if (props.closeable) model.value = false;
}

function focusable() {
    return [...(panel.value?.querySelectorAll('button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])') || [])];
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }
    if (event.key !== 'Tab') return;

    const elements = focusable();
    if (!elements.length) { event.preventDefault(); panel.value?.focus(); return; }
    const first = elements[0];
    const last = elements.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}

watch(model, async (open) => {
    if (open) {
        previousFocus = document.activeElement;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        await nextTick();
        const target = panel.value?.querySelector('[autofocus]') || focusable()[0] || panel.value;
        target?.focus();
        return;
    }

    document.body.style.overflow = previousOverflow;
    previousFocus?.focus?.();
});

onBeforeUnmount(() => {
    if (model.value) document.body.style.overflow = previousOverflow;
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="model" :class="['fixed inset-0 z-50 flex flex-col overflow-hidden', backdropClass[size] ?? backdropClass.md, alignmentClass, containerClass[size] ?? containerClass.md]" @click.self="close">
                <section ref="panel" role="dialog" aria-modal="true" :aria-labelledby="titleId" tabindex="-1" :class="['flex w-full flex-col bg-surface-container-lowest shadow-2xl focus:outline-none', sizeClass[size] ?? sizeClass.md, panelSizeClass[size] ?? panelSizeClass.md]" @keydown="onKeydown">
                    <header v-if="$slots.header" class="relative flex shrink-0 items-start justify-between gap-4 px-5 pb-0 pt-5 sm:px-6 sm:pt-6">
                        <h2 :id="titleId" class="sr-only">{{ title }}</h2>
                        <slot name="header" />
                    </header>
                    <header v-else class="flex shrink-0 items-center justify-between gap-4 inset-divider px-5 py-4 sm:px-6">
                        <h2 :id="titleId" class="text-lg font-bold text-primary">{{ title }}</h2>
                        <button v-if="closeable" type="button" class="grid size-10 shrink-0 place-items-center rounded-full text-on-surface-variant transition-all duration-150 hover:bg-surface-container-low hover:text-primary active:scale-90 focus:outline-none focus:ring-2 focus:ring-primary-container/30" :aria-label="t('Tutup modal')" @click="close"><AppIcon name="close" /></button>
                    </header>
                    <div class="flex-1 overflow-y-auto p-5 sm:p-6"><slot /></div>
                    <footer v-if="$slots.footer" class="flex shrink-0 flex-wrap justify-end gap-3 inset-divider-t px-5 py-4 sm:px-6"><slot name="footer" /></footer>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 200ms cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-enter-active section,
.modal-leave-active section {
    transition: transform 220ms cubic-bezier(0.16, 1, 0.3, 1), opacity 200ms ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-from section,
.modal-leave-to section {
    opacity: 0;
    transform: translateY(0.75rem) scale(0.96);
}

@media (prefers-reduced-motion: reduce) {
    .modal-enter-active,
    .modal-leave-active,
    .modal-enter-active section,
    .modal-leave-active section {
        transition: none;
    }
}
</style>