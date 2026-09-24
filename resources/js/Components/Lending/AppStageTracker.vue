<script setup>
import AppIcon from '../AppIcon.vue';

const props = defineProps({
    stages: { type: Array, required: true },
    currentKey: { type: String, required: true },
    orientation: {
        type: String,
        default: 'horizontal',
        validator: (value) => ['horizontal', 'vertical'].includes(value),
    },
});

function statusOf(stage) {
    const order = props.stages.findIndex((s) => s.key === props.currentKey);
    const stageOrder = props.stages.findIndex((s) => s.key === stage.key);
    if (order < 0) return 'upcoming';
    if (stageOrder < order) return 'done';
    if (stageOrder === order) return 'current';
    return 'upcoming';
}

function toneClass(status) {
    if (status === 'done') return 'bg-secondary text-on-secondary border-secondary';
    if (status === 'current') return 'bg-primary text-on-primary border-primary ring-4 ring-primary/15';
    return 'bg-surface text-on-surface-variant border-outline-variant';
}

function labelClass(status) {
    if (status === 'done') return 'text-on-surface';
    if (status === 'current') return 'text-on-surface font-extrabold';
    return 'text-on-surface-variant';
}

function metaClass(status) {
    if (status === 'current') return 'text-primary font-semibold';
    if (status === 'done') return 'text-on-surface-variant';
    return 'text-on-surface-variant/70';
}

function connectorClass(status) {
    if (status === 'done') return 'bg-secondary';
    return 'bg-outline-variant';
}

function statusBadgeClass(status) {
    if (status === 'done') return 'bg-secondary-container text-secondary';
    if (status === 'current') return 'bg-primary-container text-primary';
    return 'bg-surface-container-low text-on-surface-variant';
}

function statusLabel(status) {
    if (status === 'done') return 'Selesai';
    if (status === 'current') return 'Aktif';
    return 'Akan Datang';
}
</script>

<template>
    <!-- ===== VERTICAL (untuk sidebar sempit) ===== -->
    <ol v-if="orientation === 'vertical'" class="space-y-0">
        <li
            v-for="(stage, idx) in stages"
            :key="stage.key"
            class="relative flex gap-3"
        >
            <!-- kolom kiri: bulatan + connector vertikal -->
            <div class="flex flex-col items-center">
                <span
                    class="relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold transition"
                    :class="toneClass(statusOf(stage))"
                >
                    <AppIcon v-if="statusOf(stage) === 'done'" name="check" class="text-base leading-none" />
                    <template v-else>{{ idx + 1 }}</template>
                </span>
                <div
                    v-if="idx < stages.length - 1"
                    class="mt-1 w-0.5 flex-1 rounded-full"
                    :class="connectorClass(statusOf(stage))"
                ></div>
            </div>

            <!-- kolom kanan: label + deskripsi + status kecil -->
            <div class="min-w-0 flex-1 pb-4 pt-1 last:pb-0">
                <div class="flex items-start justify-between gap-2">
                    <p
                        class="min-w-0 flex-1 text-sm leading-snug"
                        :class="labelClass(statusOf(stage))"
                        :title="stage.label"
                    >
                        {{ stage.label }}
                    </p>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                        :class="statusBadgeClass(statusOf(stage))"
                    >
                        {{ statusLabel(statusOf(stage)) }}
                    </span>
                </div>
                <p
                    v-if="stage.description"
                    class="mt-0.5 text-[11px] leading-snug"
                    :class="metaClass(statusOf(stage))"
                >
                    {{ stage.description }}
                </p>
            </div>
        </li>
    </ol>

    <!-- ===== HORIZONTAL (untuk header lebar) ===== -->
    <ol v-else class="flex w-full items-center gap-0 overflow-x-auto">
        <li
            v-for="(stage, idx) in stages"
            :key="stage.key"
            class="flex min-w-0 flex-1 items-center"
        >
            <div class="flex min-w-0 items-center gap-2.5">
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold transition"
                    :class="toneClass(statusOf(stage))"
                >
                    <AppIcon v-if="statusOf(stage) === 'done'" name="check" class="text-base leading-none" />
                    <template v-else>{{ idx + 1 }}</template>
                </span>
                <span
                    class="truncate text-sm font-semibold"
                    :class="statusOf(stage) === 'upcoming' ? 'text-on-surface-variant' : 'text-on-surface'"
                    :title="stage.label"
                >
                    {{ stage.label }}
                </span>
            </div>

            <div
                v-if="idx < stages.length - 1"
                class="mx-2.5 h-0.5 min-w-[12px] flex-1 rounded-full"
                :class="connectorClass(statusOf(stage))"
            ></div>
        </li>
    </ol>
</template>