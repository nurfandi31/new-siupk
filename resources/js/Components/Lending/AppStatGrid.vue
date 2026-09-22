<script setup>
import AppIcon from '../AppIcon.vue';

defineProps({
    stats: { type: Array, required: true },
});

const accentRing = {
    primary: 'ring-1 ring-primary/20',
    secondary: 'ring-1 ring-secondary/30',
    tertiary: 'ring-1 ring-tertiary/30',
    error: 'ring-1 ring-error/30',
};
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
        <div
            v-for="stat in stats"
            :key="stat.key"
            class="relative overflow-hidden rounded-2xl border border-outline-variant bg-surface p-4 sm:p-5"
            :class="stat.accent ? (accentRing[stat.accent] ?? '') : null"
        >
            <div class="flex items-start justify-between gap-2">
                <p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">
                    {{ stat.label }}
                </p>
                <span
                    v-if="stat.icon"
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                    :class="stat.iconBg ?? 'bg-primary-container/20 text-primary'"
                >
                    <AppIcon :name="stat.icon" class="text-lg leading-none" />
                </span>
            </div>

            <p
                class="mt-2 text-xl font-extrabold tabular-nums tracking-tight text-on-surface sm:text-2xl"
                :class="stat.valueClass"
            >
                {{ stat.value }}
            </p>

            <p v-if="stat.sublabel" class="mt-1 text-xs text-on-surface-variant">
                {{ stat.sublabel }}
            </p>

            <div v-if="stat.progress !== undefined && stat.progress !== null" class="mt-3">
                <div class="flex items-center justify-between text-[11px] font-semibold text-on-surface-variant">
                    <span>{{ stat.progressLabel ?? 'Progress' }}</span>
                    <span class="tabular-nums text-on-surface">{{ stat.progress }}%</span>
                </div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-container-high">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="stat.progressClass ?? 'bg-primary'"
                        :style="{ width: `${Math.min(100, Math.max(0, Number(stat.progress)))}%` }"
                    ></div>
                </div>
            </div>
        </div>
    </div>
</template>
