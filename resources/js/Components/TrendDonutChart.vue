<script setup>
import { computed } from 'vue';

const props = defineProps({
    data: { type: Array, required: true },
    title: { type: String, default: 'Komposisi' },
    centerLabel: { type: String, default: 'Total' },
    size: { type: Number, default: 180 },
});

const palette = [
    { fill: 'var(--color-primary)', text: 'primary' },
    { fill: 'var(--color-secondary)', text: 'secondary' },
    { fill: 'var(--color-tertiary)', text: 'tertiary' },
    { fill: 'var(--color-warning)', text: 'warning' },
    { fill: 'var(--color-error)', text: 'error' },
    { fill: 'var(--color-info)', text: 'info' },
];

const total = computed(() =>
    props.data.reduce((sum, d) => sum + (Number(d.value) || 0), 0),
);

const R = 70;
const STROKE = 22;
const SIZE = computed(() => props.size);
const CX = computed(() => SIZE.value / 2);
const CY = computed(() => SIZE.value / 2);
const C = computed(() => 2 * Math.PI * R);

const slices = computed(() => {
    if (total.value <= 0) return [];
    let acc = 0;
    return props.data.map((d, i) => {
        const v = Number(d.value) || 0;
        const fraction = v / total.value;
        const dash = fraction * C.value;
        const gap = C.value - dash;
        const offset = -acc;
        acc += dash;
        const color = palette[i % palette.length];
        return {
            key: d.key ?? d.label ?? i,
            label: d.label,
            value: v,
            fraction,
            percent: fraction * 100,
            fill: d.fill ?? color.fill,
            text: color.text,
            dasharray: `${dash} ${gap}`,
            dashoffset: offset,
        };
    });
});

const centerValue = computed(() => formatCompact(total.value));

function formatCompact(v) {
    if (!v) return '0';
    if (v >= 1e9) return (v / 1e9).toFixed(1).replace(/\.0$/, '') + 'M';
    if (v >= 1e6) return (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'jt';
    if (v >= 1e3) return (v / 1e3).toFixed(1).replace(/\.0$/, '') + 'rb';
    return Math.round(v).toString();
}
</script>

<template>
    <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-center sm:gap-6">
        <div class="relative shrink-0" :style="{ width: `${SIZE}px`, height: `${SIZE}px` }">
            <svg
                :viewBox="`0 0 ${SIZE} ${SIZE}`"
                :width="SIZE"
                :height="SIZE"
                class="block"
                role="img"
                :aria-label="`Donut chart: ${title}`"
            >
                <circle
                    :cx="CX"
                    :cy="CY"
                    :r="R"
                    fill="transparent"
                    stroke="var(--color-surface-container-high)"
                    :stroke-width="STROKE"
                />
                <g :transform="`rotate(-90 ${CX} ${CY})`">
                    <circle
                        v-for="s in slices"
                        :key="s.key"
                        :cx="CX"
                        :cy="CY"
                        :r="R"
                        fill="transparent"
                        :stroke="s.fill"
                        :stroke-width="STROKE"
                        :stroke-dasharray="s.dasharray"
                        :stroke-dashoffset="s.dashoffset"
                        class="transition-all duration-300"
                    />
                </g>
                <text
                    :x="CX"
                    :y="CY - 4"
                    text-anchor="middle"
                    dominant-baseline="middle"
                    fill="var(--color-on-surface-variant)"
                    font-size="11"
                    font-weight="600"
                    class="uppercase tracking-wider"
                >
                    {{ centerLabel }}
                </text>
                <text
                    :x="CX"
                    :y="CY + 12"
                    text-anchor="middle"
                    dominant-baseline="middle"
                    fill="var(--color-on-surface)"
                    font-size="20"
                    font-weight="800"
                >
                    {{ centerValue }}
                </text>
            </svg>
        </div>

        <ul class="w-full flex-1 space-y-1.5">
            <li
                v-for="s in slices"
                :key="`leg-${s.key}`"
                class="flex items-center justify-between gap-3 text-xs"
            >
                <span class="inline-flex min-w-0 items-center gap-2 text-on-surface-variant">
                    <span class="size-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: s.fill }" />
                    <span class="truncate font-medium text-on-surface">{{ s.label }}</span>
                </span>
                <span class="shrink-0 font-semibold tabular-nums" :class="`text-${s.text}`">
                    {{ s.percent.toFixed(1) }}%
                </span>
            </li>
            <li v-if="!slices.length" class="text-center text-xs text-on-surface-variant">
                Belum ada data untuk ditampilkan.
            </li>
        </ul>
    </div>
</template>
