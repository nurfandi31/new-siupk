<script setup>
import { useI18n } from '../i18n';
const { t } = useI18n();
import { computed, onBeforeUnmount, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from '../composables/useToast';
import AppIcon from './AppIcon.vue';

const page = usePage();
const { toastState, show, dismiss, pause, resume } = useToast();

const tones = {
    success: {
        label: t('Berhasil'),
        icon: 'check_circle',
        iconBg: 'bg-secondary-container/70 text-secondary ring-secondary/25',
        halo: 'bg-secondary/25',
        accent: 'bg-secondary',
        bar: 'bg-secondary',
        labelColor: 'text-secondary',
    },
    error: {
        label: t('Gagal'),
        icon: 'error',
        iconBg: 'bg-error-container/70 text-error ring-error/25',
        halo: 'bg-error/25',
        accent: 'bg-error',
        bar: 'bg-error',
        labelColor: 'text-error',
    },
    warning: {
        label: t('Perhatian'),
        icon: 'warning',
        iconBg: 'bg-tertiary-fixed/70 text-tertiary ring-tertiary/25',
        halo: 'bg-tertiary/25',
        accent: 'bg-tertiary',
        bar: 'bg-tertiary',
        labelColor: 'text-tertiary',
    },
    info: {
        label: t('Informasi'),
        icon: 'info',
        iconBg: 'bg-primary-container/70 text-primary ring-primary/25',
        halo: 'bg-primary/25',
        accent: 'bg-primary',
        bar: 'bg-primary',
        labelColor: 'text-primary',
    },
};

const toneStyles = computed(() => tones[toastState.tone] || tones.info);

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash || typeof flash !== 'object') return;
        if (flash.success != null) show('success', flash.success);
        else if (flash.error != null) show('error', flash.error);
        else if (flash.warning != null) show('warning', flash.warning);
        else if (flash.info != null) show('info', flash.info);
    },
    { deep: true, immediate: true },
);

onBeforeUnmount(() => {
    dismiss();
});
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex justify-end px-4 sm:top-6 sm:px-6"
            aria-live="polite"
            aria-atomic="true"
        >
            <Transition name="toast">
                <div
                    v-if="toastState.visible"
                    :key="toastState.token"
                    role="status"
                    class="toast-card pointer-events-auto relative flex w-full max-w-md items-start gap-3.5 overflow-hidden rounded-2xl border border-outline-variant/50 bg-surface-container-lowest/95 py-3.5 pl-5 pr-3.5 shadow-2xl shadow-black/10 ring-1 ring-black/5 backdrop-blur-md"
                    @mouseenter="pause"
                    @mouseleave="resume"
                >
                    <!-- Aksen warna sesuai tone -->
                    <span class="absolute inset-y-0 left-0 w-1.5" :class="toneStyles.accent" aria-hidden="true" />

                    <!-- Ikon -->
                    <div
                        class="relative grid size-10 shrink-0 place-items-center rounded-xl ring-1 ring-inset"
                        :class="toneStyles.iconBg"
                    >
                        <span class="absolute inset-0 rounded-xl opacity-60 blur-md" :class="toneStyles.halo" aria-hidden="true" />
                        <AppIcon :name="toneStyles.icon" class="relative text-[22px]" />
                    </div>

                    <!-- Pesan -->
                    <div class="min-w-0 flex-1 pt-0.5">
                        <p
                            class="text-[10px] font-bold uppercase tracking-[0.16em]"
                            :class="toneStyles.labelColor"
                        >
                            {{ toastState.title || toneStyles.label }}
                        </p>
                        <p class="mt-0.5 text-sm font-semibold leading-snug text-on-surface">
                            {{ toastState.message }}
                        </p>
                    </div>

                    <!-- Tombol tutup -->
                    <button
                        type="button"
                        class="grid size-8 shrink-0 place-items-center rounded-full text-on-surface-variant/60 transition-colors hover:bg-surface-container-high hover:text-on-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                        :aria-label="t('Tutup notifikasi')"
                        @click="dismiss"
                    >
                        <AppIcon name="close" class="text-lg" />
                    </button>

                    <!-- Progress bar durasi tampil -->
                    <span
                        v-if="toastState.duration > 0"
                        class="toast-progress absolute inset-x-0 bottom-0 h-0.5 origin-left"
                        :class="toneStyles.bar"
                        :style="{ animationDuration: `${toastState.duration}ms` }"
                        aria-hidden="true"
                    />
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active {
    transition: opacity 240ms ease, transform 280ms cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-0.75rem) scale(0.96);
}

.toast-progress {
    animation-name: toast-progress;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
}
.toast-card:hover .toast-progress {
    animation-play-state: paused;
}
@keyframes toast-progress {
    from {
        transform: scaleX(1);
    }
    to {
        transform: scaleX(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .toast-enter-active,
    .toast-leave-active {
        transition: none;
    }
    .toast-progress {
        animation: none;
    }
}
</style>
