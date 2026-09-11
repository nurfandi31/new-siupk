<script setup>
import AppIcon from '../AppIcon.vue';

const props = defineProps({
    block: { type: Object, required: true },
    opened: { type: Boolean, default: false },
});

const emit = defineEmits(['open']);

const iconMap = {
    table: 'table_chart',
    markdown: 'description',
    json: 'data_object',
    text: 'article',
};

const kind = (props.block.kind || 'table').toLowerCase();
const icon = iconMap[kind] ?? 'description';
</script>

<template>
    <button
        type="button"
        class="assistant-artifact-card group flex w-full items-start gap-3 rounded-xl border border-outline-variant bg-primary-soft/40 px-4 py-3 text-left transition-all duration-150 active:scale-[0.99] hover:border-primary hover:bg-primary-soft/70 focus:outline-none focus:ring-2 focus:ring-primary/30"
        :aria-label="`Buka ${block.title}`"
        @click="emit('open', block)"
    >
        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary text-on-primary shadow-xs">
            <AppIcon :name="icon" class="text-lg" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-primary">{{ block.title }}</p>
            <p v-if="block.summary" class="line-clamp-1 text-xs text-on-surface-variant">{{ block.summary }}</p>
        </div>
        <span class="ml-1 shrink-0 text-on-surface-variant transition-transform duration-150 group-hover:translate-x-0.5">
            <AppIcon name="chevron_right" />
        </span>
    </button>
</template>