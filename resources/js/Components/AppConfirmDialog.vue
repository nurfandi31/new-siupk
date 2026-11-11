<script setup>
import { useI18n } from '../i18n';
const { t } = useI18n();
import { computed } from 'vue';
import { useConfirm } from '../composables/useConfirm';
import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';
import AppModal from './AppModal.vue';

const { confirmState, handleConfirm, handleCancel } = useConfirm();

const tones = {
    danger: {
        eyebrow: t('Tindakan berisiko'),
        icon: 'warning',
        badge: 'bg-error-container/70 text-error ring-error/25',
        glow: 'bg-error/25',
        labelColor: 'text-error',
        callout: 'border-error/20 bg-error-container/30 text-error',
        button: 'danger',
    },
    warning: {
        eyebrow: t('Perlu perhatian'),
        icon: 'notification_important',
        badge: 'bg-tertiary-fixed/70 text-tertiary ring-tertiary/25',
        glow: 'bg-tertiary/25',
        labelColor: 'text-tertiary',
        callout: 'border-tertiary/20 bg-tertiary-fixed/30 text-tertiary',
        button: 'tertiary',
    },
    primary: {
        eyebrow: t('Konfirmasi tindakan'),
        icon: 'help',
        badge: 'bg-primary-container/70 text-primary ring-primary/25',
        glow: 'bg-primary/25',
        labelColor: 'text-primary',
        callout: 'border-primary/20 bg-primary-container/25 text-primary',
        button: 'primary',
    },
    success: {
        eyebrow: t('Konfirmasi tindakan'),
        icon: 'check_circle',
        badge: 'bg-secondary-container/70 text-secondary ring-secondary/25',
        glow: 'bg-secondary/25',
        labelColor: 'text-secondary',
        callout: 'border-secondary/20 bg-secondary-container/30 text-secondary',
        button: 'success',
    },
    info: {
        eyebrow: t('Pemberitahuan'),
        icon: 'info',
        badge: 'bg-primary-container/70 text-primary ring-primary/25',
        glow: 'bg-primary/25',
        labelColor: 'text-primary',
        callout: 'border-primary/20 bg-primary-container/25 text-primary',
        button: 'primary',
    },
};

const tone = computed(() => tones[confirmState.variant] || tones.primary);

const eyebrow = computed(() => {
    if (!confirmState.cancelLabel && confirmState.variant !== 'danger') {
        return 'Pemberitahuan';
    }
    return tone.value.eyebrow;
});
</script>

<template>
    <AppModal
        :model-value="confirmState.open"
        :title="confirmState.title"
        size="sm"
        @update:model-value="handleCancel"
    >
        <template #header>
            <div class="flex w-full items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3.5">
                    <div class="relative shrink-0">
                        <span
                            class="absolute -inset-1.5 rounded-2xl opacity-70 blur-lg"
                            :class="tone.glow"
                            aria-hidden="true"
                        />
                        <div
                            class="relative grid size-12 place-items-center rounded-2xl shadow-sm ring-1 ring-inset"
                            :class="tone.badge"
                        >
                            <AppIcon :name="confirmState.icon || tone.icon" class="text-2xl" />
                        </div>
                    </div>
                    <div class="min-w-0 pt-0.5">
                        <p
                            class="text-[10px] font-bold uppercase tracking-[0.16em]"
                            :class="tone.labelColor"
                        >
                            {{ eyebrow }}
                        </p>
                        <p class="mt-0.5 break-words text-lg font-bold leading-snug text-primary">
                            {{ confirmState.title }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="grid size-9 shrink-0 place-items-center rounded-full text-on-surface-variant/60 transition-all duration-150 hover:bg-surface-container-low hover:text-primary active:scale-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container/40"
                    :aria-label="t('Tutup dialog')"
                    @click="handleCancel"
                >
                    <AppIcon name="close" class="text-xl" />
                </button>
            </div>
        </template>

        <div class="space-y-4">
            <p class="text-sm leading-relaxed text-on-surface-variant">
                {{ confirmState.message }}
            </p>

            <div
                v-if="confirmState.cancelLabel"
                class="flex items-start gap-2.5 rounded-xl border px-3.5 py-3 text-[11px] font-medium leading-relaxed"
                :class="tone.callout"
            >
                <AppIcon name="info" class="mt-px text-base" />
                <p>{{ t('Periksa kembali tindakan ini. Anda masih bisa membatalkan sebelum melanjutkan.') }}</p>
            </div>
        </div>

        <template #footer>
            <div class="flex w-full flex-col gap-2.5 sm:flex-row sm:justify-end">
                <AppButton
                    v-if="confirmState.cancelLabel"
                    variant="secondary"
                    class="sm:min-w-24"
                    @click="handleCancel"
                >
                    {{ confirmState.cancelLabel }}
                </AppButton>
                <AppButton
                    :variant="tone.button"
                    class="sm:min-w-32"
                    @click="handleConfirm"
                >
                    {{ confirmState.confirmLabel }}
                </AppButton>
            </div>
        </template>
    </AppModal>
</template>
