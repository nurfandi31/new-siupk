<script setup>
import AppIcon from '../AppIcon.vue';

defineProps({
    stats: { type: Array, required: true },
    /**
     * Variant density:
     *  - 'compact' (default) : p-4, value text-lg
     *  - 'comfortable'       : p-5, value text-xl, lebih lega
     */
    density: { type: String, default: 'compact' },
});

// Material 3 tonal accents: kartu dibungkus ring tipis + aksen pojok kiri atas.
// Background ikon container tetap dipilih lewat stat.iconBg.
const accentTone = {
    primary: 'before:bg-primary',
    secondary: 'before:bg-secondary',
    tertiary: 'before:bg-tertiary',
    error: 'before:bg-error',
    warning: 'before:bg-amber-500',
    success: 'before:bg-emerald-500',
    info: 'before:bg-sky-500',
    neutral: 'before:bg-outline-variant',
};
</script>

<template>
    <div
        class="grid gap-3 sm:gap-4"
        :class="density === 'comfortable'
            ? 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5'
            : 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5'"
    >
        <div
            v-for="stat in stats"
            :key="stat.key"
            class="group relative isolate overflow-hidden rounded-2xl border border-outline-variant/70 bg-surface shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-within:ring-2 focus-within:ring-primary/30"
            :class="[
                density === 'comfortable' ? 'p-5' : 'p-4',
                // Aksen bar vertikal kiri (Material 3 style)
                'before:absolute before:inset-y-3 before:left-0 before:w-1 before:rounded-full',
                accentTone[stat.accent ?? 'neutral'] ?? accentTone.neutral,
            ]"
        >
            <!-- Header: label + ikon -->
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">
                        {{ stat.label }}
                    </p>
                    <p v-if="stat.eyebrow" class="mt-0.5 text-[11px] font-semibold text-on-surface-variant/80">
                        {{ stat.eyebrow }}
                    </p>
                </div>
                <span
                    v-if="stat.icon"
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl shadow-inner ring-1 ring-inset ring-black/[0.04]"
                    :class="stat.iconBg ?? 'bg-primary-container/30 text-primary'"
                >
                    <AppIcon :name="stat.icon" class="text-[18px] leading-none" />
                </span>
            </div>

            <!-- Value -->
            <div class="mt-3 flex items-baseline gap-2">
                <p
                    class="truncate font-extrabold tabular-nums tracking-tight text-on-surface"
                    :class="[
                        density === 'comfortable' ? 'text-2xl' : 'text-xl sm:text-2xl',
                        stat.valueClass,
                    ]"
                    :title="stat.value"
                >
                    {{ stat.value }}
                </p>
                <span
                    v-if="stat.suffix"
                    class="text-xs font-bold text-on-surface-variant"
                >{{ stat.suffix }}</span>
            </div>

            <!-- Sublabel / kualifikasi tambahan -->
            <p
                v-if="stat.sublabel"
                class="mt-1 text-[11px] leading-tight text-on-surface-variant"
            >
                {{ stat.sublabel }}
            </p>

            <!-- Delta / tren mini -->
            <div
                v-if="stat.delta !== undefined && stat.delta !== null"
                class="mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums"
                :class="stat.deltaClass ?? 'bg-surface-container-high text-on-surface-variant'"
            >
                <AppIcon
                    :name="(stat.deltaTone === 'down' ? 'trending_down' : (stat.deltaTone === 'flat' ? 'trending_flat' : 'trending_up'))"
                    class="text-[13px] leading-none"
                />
                <span>{{ stat.delta }}</span>
            </div>

            <!-- Progress bar -->
            <div
                v-if="stat.progress !== undefined && stat.progress !== null"
                class="mt-3"
            >
                <div class="flex items-center justify-between text-[10px] font-semibold uppercase tracking-widest text-on-surface-variant">
                    <span>{{ stat.progressLabel ?? 'Progress' }}</span>
                    <span class="tabular-nums text-on-surface">{{ stat.progress }}%</span>
                </div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-container-high">
                    <div
                        class="h-full rounded-full transition-all duration-500 ease-out"
                        :class="stat.progressClass ?? 'bg-primary'"
                        :style="{ width: `${Math.min(100, Math.max(0, Number(stat.progress)))}%` }"
                    ></div>
                </div>
            </div>

            <!-- Footer / catatan kecil (slot-able) -->
            <div
                v-if="stat.footer || $slots.footer"
                class="mt-3 flex items-center gap-1.5 border-t border-outline-variant/60 pt-2 text-[11px] text-on-surface-variant"
            >
                <AppIcon v-if="stat.footerIcon" :name="stat.footerIcon" class="text-[13px] leading-none" />
                <slot name="footer" :stat="stat">
                    <span class="truncate">{{ stat.footer }}</span>
                </slot>
            </div>
        </div>
    </div>
</template>
