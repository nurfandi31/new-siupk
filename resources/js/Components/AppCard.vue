<script setup>
import { ref } from 'vue';

defineProps({
    padded: { type: Boolean, default: true },
    bordered: { type: Boolean, default: false },
});

const root = ref(null);
defineExpose({ root });
</script>

<template>
    <section ref="root" class="elevated-card rounded-xl bg-surface-container-lowest" :class="[padded && 'p-6', bordered && 'ring-1 ring-outline-variant']">
        <header v-if="$slots.header" class="flex items-center justify-between gap-4" :class="[padded ? 'mb-6' : 'px-4 py-3 inset-divider']">
            <slot name="header" />
        </header>
        <slot />
    </section>
</template>
