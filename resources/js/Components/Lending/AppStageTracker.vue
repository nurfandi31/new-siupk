<script setup>
import AppIcon from '../AppIcon.vue';

const props = defineProps({
    stages: { type: Array, required: true },
    currentKey: { type: String, required: true },
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
    return 'bg-surface-container-high text-on-surface-variant border-outline-variant';
}

function connectorClass(status) {
    if (status === 'done') return 'bg-secondary';
    return 'bg-outline-variant';
}
</script>

<template>
    <ol class="flex w-full items-stretch gap-0 overflow-x-auto pb-1">
        <li
            v-for="(stage, idx) in stages"
            :key="stage.key"
            class="flex min-w-[120px] flex-1 items-center"
        >
            <div class="flex flex-col items-center gap-1.5 text-center">
                <span
                    class="flex size-9 items-center justify-center rounded-full border-2 text-xs font-bold transition"
                    :class="toneClass(statusOf(stage))"
                >
                    <AppIcon v-if="statusOf(stage) === 'done'" name="check" class="text-base leading-none" />
                    <template v-else>{{ idx + 1 }}</template>
                </span>
                <span
                    class="text-[11px] font-bold uppercase tracking-wider"
                    :class="statusOf(stage) === 'upcoming' ? 'text-on-surface-variant' : 'text-on-surface'"
                >
                    {{ stage.label }}
                </span>
                <span
                    v-if="stage.description"
                    class="hidden text-[10px] text-on-surface-variant sm:block max-w-[110px]"
                >
                    {{ stage.description }}
                </span>
            </div>

            <div
                v-if="idx < stages.length - 1"
                class="mx-2 h-0.5 flex-1 rounded-full"
                :class="connectorClass(statusOf(stage))"
            ></div>
        </li>
    </ol>
</template>
