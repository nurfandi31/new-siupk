<script setup>
import AppIcon from '../AppIcon.vue';

defineProps({
    backHref: { type: String, default: null },
    backLabel: { type: String, default: 'Kembali' },
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    breadcrumbs: { type: Array, default: () => [] },
    tone: { type: String, default: 'neutral' },
});
</script>

<template>
    <header class="relative overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-sm">
        <div
            class="pointer-events-none absolute inset-0 opacity-[0.05]"
            :class="{
                'bg-primary': tone === 'primary',
                'bg-secondary': tone === 'success',
                'bg-tertiary': tone === 'warning',
                'bg-error': tone === 'error',
            }"
            aria-hidden="true"
        ></div>

        <div class="relative px-5 py-5 sm:px-7 sm:py-6">
            <nav v-if="breadcrumbs.length" class="flex flex-wrap items-center gap-1.5 text-xs text-on-surface-variant" aria-label="Breadcrumb">
                <template v-for="(crumb, idx) in breadcrumbs" :key="idx">
                    <Link
                        v-if="crumb.href && idx < breadcrumbs.length - 1"
                        :href="crumb.href"
                        class="font-medium hover:text-primary"
                    >
                        {{ crumb.label }}
                    </Link>
                    <span v-else class="font-semibold text-on-surface">{{ crumb.label }}</span>
                    <AppIcon
                        v-if="idx < breadcrumbs.length - 1"
                        name="chevron_right"
                        class="text-base text-on-surface-variant/60"
                    />
                </template>
            </nav>

            <div class="mt-2 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            v-if="backHref"
                            :href="backHref"
                            class="inline-flex size-9 items-center justify-center rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary"
                            :aria-label="backLabel"
                        >
                            <AppIcon name="arrow_back" class="text-lg" />
                        </Link>
                        <h1 class="text-xl font-extrabold tracking-tight text-on-surface sm:text-2xl lg:text-[26px]">
                            {{ title }}
                        </h1>
                        <slot name="badges" />
                    </div>
                    <p v-if="subtitle" class="mt-1.5 max-w-3xl text-sm text-on-surface-variant">
                        {{ subtitle }}
                    </p>
                    <slot name="meta" />
                </div>

                <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
                    <slot name="actions" />
                </div>
            </div>

            <slot name="footer" />
        </div>
    </header>
</template>
