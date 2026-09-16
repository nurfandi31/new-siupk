<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
});
</script>

<template>
    <header class="flex flex-col gap-4 border-b border-outline-variant pb-5 md:flex-row md:items-end md:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-extrabold tracking-tight text-on-surface sm:text-2xl">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 max-w-2xl text-sm text-on-surface-variant">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
