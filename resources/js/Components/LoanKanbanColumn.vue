<script setup>
import AppBadge from './AppBadge.vue';

defineProps({
    label: { type: String, required: true },
    count: { type: Number, required: true },
    dropActive: { type: Boolean, default: false },
});

defineEmits(['drag-over', 'drag-leave', 'drop']);
</script>

<template>
    <section
        class="flex min-h-[420px] flex-col rounded-2xl border transition"
        :class="dropActive ? 'border-primary bg-primary/5' : 'border-outline-variant bg-surface-container-lowest'"
        @dragover.prevent="$emit('drag-over')"
        @dragleave="$emit('drag-leave')"
        @drop.prevent="$emit('drop')"
    >
        <header class="flex items-center justify-between px-4 py-3">
            <h2 class="font-bold text-primary">{{ label }}</h2>
            <AppBadge tone="primary-soft">{{ count }}</AppBadge>
        </header>
        <div class="flex-1 space-y-3 overflow-y-auto px-3 pb-3">
            <slot />
        </div>
    </section>
</template>
