<script setup>
defineProps({
    fields: { type: Array, required: true },
    columns: { type: [Number, String], default: 3 },
});
</script>

<template>
    <dl
        class="grid gap-x-6 gap-y-4"
        :class="{
            'sm:grid-cols-2': Number(columns) >= 2,
            'sm:grid-cols-2 lg:grid-cols-3': Number(columns) >= 3,
            'sm:grid-cols-2 lg:grid-cols-4': Number(columns) >= 4,
        }"
    >
        <div
            v-for="field in fields"
            :key="field.key"
            :class="field.span ? `sm:col-span-${Math.min(12, Math.max(1, Number(field.span) * Math.floor(12 / Number(columns))))}` : null"
            class="min-w-0"
        >
            <dt class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">
                {{ field.label }}
            </dt>
            <dd class="mt-1 text-sm font-semibold text-on-surface break-words">
                <slot :name="`field-${field.key}`" :field="field" :value="field.value">
                    <span :class="field.muted ? 'font-normal text-on-surface-variant' : null">
                        {{ field.value ?? '—' }}
                    </span>
                </slot>
            </dd>
            <dd v-if="field.hint" class="mt-0.5 text-xs text-on-surface-variant">
                {{ field.hint }}
            </dd>
        </div>
    </dl>
</template>
