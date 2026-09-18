<script setup>
const props = defineProps({
    name: { type: String, required: true },
    filled: { type: Boolean, default: false },
    label: { type: String, default: null },
    tone: {
        type: String,
        default: 'neutral',
        validator: (value) => ['neutral', 'success', 'warning', 'danger', 'error', 'info', 'primary', 'secondary', 'tertiary'].includes(value),
    },
    containerSize: { type: [Number, String], default: 9 },
    containerShape: {
        type: String,
        default: 'rounded',
        validator: (value) => ['rounded', 'pill'].includes(value),
    },
});

const containerClasses = {
    neutral: 'bg-surface-container-low text-on-surface-variant',
    success: 'bg-secondary-container text-on-secondary-container',
    warning: 'bg-tertiary-fixed text-on-tertiary',
    danger: 'bg-error-container text-on-error-container',
    error: 'bg-error-container text-on-error-container',
    info: 'bg-primary-container text-on-primary-container',
    primary: 'bg-primary-container text-on-primary-container',
    secondary: 'bg-secondary-container text-on-secondary-container',
    tertiary: 'bg-tertiary-fixed text-on-tertiary',
};
</script>

<template>
    <span
        v-if="tone === 'neutral'"
        class="material-symbols-outlined shrink-0"
        :class="{ 'is-filled': filled }"
        :aria-hidden="label ? undefined : 'true'"
        :aria-label="label || undefined"
        :role="label ? 'img' : undefined"
        style="font-size: inherit; line-height: 1; width: 1em; height: 1em; display: inline-block; text-align: center;"
    >{{ name }}</span>

    <span
        v-else
        class="grid shrink-0 place-items-center"
        :class="[
            `size-${containerSize}`,
            containerShape === 'pill' ? 'rounded-full' : 'rounded-lg',
            containerClasses[tone],
        ]"
        :aria-hidden="label ? undefined : 'true'"
        :aria-label="label || undefined"
        :role="label ? 'img' : undefined"
    >
        <span
            class="material-symbols-outlined"
            :class="{ 'is-filled': filled }"
            style="font-size: 1.25rem; line-height: 1;"
        >{{ name }}</span>
    </span>
</template>
