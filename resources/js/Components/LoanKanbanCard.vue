<script setup>
import { Link } from '@inertiajs/vue3';
import AppBadge from './AppBadge.vue';
import AppButton from './AppButton.vue';
import { useMoney } from '../composables/useMoney';

defineProps({
    loan: { type: Object, required: true },
    stageLabel: { type: String, required: true },
    actions: { type: Array, default: () => [] },
});

defineEmits(['action']);

const { money } = useMoney();
</script>

<template>
    <article
        draggable="true"
        class="cursor-grab rounded-xl border border-outline-variant bg-surface-container-lowest p-4 transition hover:border-primary"
    >
        <div class="flex items-start justify-between gap-2">
            <div>
                <p class="font-bold text-primary">{{ loan.loan_number || `#${loan.row_id}` }}</p>
                <p class="text-xs text-on-surface-variant">{{ loan.group_name }}</p>
            </div>
            <AppBadge tone="neutral">{{ stageLabel }}</AppBadge>
        </div>

        <p class="mt-2 text-sm font-semibold tabular-nums text-primary">
            {{ money(loan.principal_amount || loan.proposed_amount || 0) }}
        </p>
        <p class="text-xs text-on-surface-variant">{{ (loan.beneficiaries ?? []).length }} pemanfaat</p>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <Link :href="`/lending/loans/${loan.row_id}`">
                <AppButton variant="ghost" size="compact" icon="visibility">Detail</AppButton>
            </Link>
            <AppButton
                v-for="action in actions"
                :key="action.key"
                variant="secondary"
                size="compact"
                @click="$emit('action', action.key)"
            >
                {{ action.label }}
            </AppButton>
        </div>
    </article>
</template>
