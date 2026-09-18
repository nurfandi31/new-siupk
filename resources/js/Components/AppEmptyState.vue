<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    icon: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, default: null },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div
        class="grid place-items-center rounded-xl bg-surface-container-low text-center text-on-surface-variant"
        :class="compact ? 'min-h-0 p-3' : 'min-h-40 p-6'"
    >
        <div>
            <AppIcon :name="icon" :class="compact ? 'text-2xl' : 'text-4xl'" />
            <p class="mt-2 font-semibold" :class="compact && 'text-sm'">{{ title }}</p>
            <p v-if="description" class="mt-1 text-sm">{{ description }}</p>
            <div v-if="$slots.default" class="mt-4"><slot /></div>
        </div>
    </div>
</template>
