<script setup>
import AppBadge from './AppBadge.vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    label: { type: String, required: true },
    icon: { type: String, default: null },
    count: { type: Number, required: true },
    dropActive: { type: Boolean, default: false },
    accent: {
        type: String,
        default: 'neutral',
        validator: (value) => ['neutral', 'primary', 'success', 'warning'].includes(value),
    },
});

defineEmits(['drag-over', 'drag-leave', 'drop']);

const accentStrip = {
    neutral: 'bg-outline-variant',
    primary: 'bg-primary',
    success: 'bg-secondary',
    warning: 'bg-tertiary',
};

const badgeTone = {
    neutral: 'neutral',
    primary: 'primary-soft',
    success: 'success-soft',
    warning: 'warning-soft',
};
</script>

<template>
    <section
        class="flex min-h-[460px] flex-col overflow-hidden rounded-2xl border bg-surface-container-lowest transition-colors"
        :class="dropActive ? 'border-primary bg-primary-container/15 ring-2 ring-primary/30' : 'border-outline-variant'"
        @dragover.prevent="$emit('drag-over')"
        @dragleave="$emit('drag-leave')"
        @drop.prevent="$emit('drop')"
    >
        <header class="flex items-center justify-between gap-2 border-b border-outline-variant bg-surface-container-low px-4 py-3">
            <div class="flex min-w-0 items-center gap-2">
                <span class="block size-2 shrink-0 rounded-full" :class="accentStrip[accent]" aria-hidden="true"></span>
                <AppIcon v-if="icon" :name="icon" class="shrink-0 text-base text-on-surface-variant" />
                <h2 class="truncate text-sm font-bold text-on-surface">{{ label }}</h2>
            </div>
            <AppBadge :tone="badgeTone[accent]">{{ count }}</AppBadge>
        </header>

        <div class="flex-1 space-y-3 overflow-y-auto p-3">
            <slot v-if="count > 0" />

            <div
                v-else
                class="flex h-32 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-outline-variant/70 bg-surface-container-lowest/60 px-4 text-center text-xs text-on-surface-variant"
            >
                <AppIcon name="drag_indicator" class="text-2xl text-outline" />
                <p class="font-medium">Belum ada pinjaman</p>
                <p class="text-[11px] leading-snug text-on-surface-variant/80">Tarik kartu ke kolom ini untuk pindah tahap.</p>
            </div>
        </div>
    </section>
</template>