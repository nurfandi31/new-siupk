<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppBadge from './AppBadge.vue';
import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';
import { useMoney } from '../composables/useMoney';

const props = defineProps({
    loan: { type: Object, required: true },
    stageLabel: { type: String, required: true },
    stage: { type: String, default: 'proposal' },
    actions: { type: Array, default: () => [] },
});

defineEmits(['action']);

const { money } = useMoney();

const principal = computed(() => {
    const amount = props.loan.principal_amount
        ?? props.loan.proposed_amount
        ?? props.loan.verification_amount
        ?? props.loan.allocated_amount
        ?? 0;
    return Number(amount);
});

const beneficiariesCount = computed(() => props.loan.beneficiaries?.length ?? props.loan.beneficiaries_count ?? 0);

const productName = computed(() => props.loan.product?.name ?? '—');

const stageBadgeTone = {
    proposal: 'info-soft',
    verifikasi: 'primary-soft',
    waiting: 'warning-soft',
    aktif: 'success-soft',
    lunas: 'neutral',
};

const formatDate = (value) => {
    if (!value) return null;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return null;
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
};

const stageDateLabel = computed(() => {
    const map = {
        proposal: { label: 'Tgl Pengajuan', value: props.loan.proposed_at },
        verifikasi: { label: 'Tgl Verifikasi', value: props.loan.verified_at },
        waiting: { label: 'Tgl Pendanaan', value: props.loan.funded_at },
        aktif: { label: 'Tgl Cair', value: props.loan.disbursed_at },
        lunas: { label: 'Tgl Lunas', value: props.loan.completed_at },
    };
    return map[props.stage] ?? null;
});

const stageDateText = computed(() => {
    const entry = stageDateLabel.value;
    if (!entry?.value) return null;
    const text = formatDate(entry.value);
    return text ? `${entry.label}: ${text}` : null;
});

const nextDueText = computed(() => {
    if (!props.loan.next_due_date) return null;
    const text = formatDate(props.loan.next_due_date);
    return text ? `Angsuran berikutnya: ${text}` : null;
});

const remainingText = computed(() => {
    if (props.stage !== 'aktif' && props.stage !== 'lunas') return null;
    const remaining = Number(props.loan.principal_remaining ?? 0);
    return `Sisa pokok: ${money(remaining)}`;
});

const termMonths = computed(() => {
    const value = Number(props.loan.term_months ?? 0);
    return value > 0 ? `${value} bln` : null;
});
</script>

<template>
    <article
        draggable="true"
        class="group cursor-grab overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm transition hover:-translate-y-0.5 hover:border-primary/60 hover:shadow-md active:cursor-grabbing"
    >
        <header class="flex items-start justify-between gap-2 px-4 pb-2 pt-3">
            <div class="min-w-0 flex-1">
                <Link
                    :href="`/lending/loans/${loan.row_id}`"
                    class="block truncate text-sm font-bold leading-tight text-primary hover:underline"
                >
                    {{ loan.loan_number || `#${loan.row_id}` }}
                </Link>
                <p class="mt-0.5 truncate text-xs text-on-surface-variant" :title="loan.group_name">
                    {{ loan.group_name || '—' }}
                </p>
            </div>
            <AppBadge :tone="stageBadgeTone[stage] ?? 'neutral'">{{ stageLabel }}</AppBadge>
        </header>

        <div class="mx-4 mb-3 rounded-lg bg-surface-container-low px-3 py-2.5">
            <p class="text-[11px] font-medium uppercase tracking-wide text-on-surface-variant/80">Plafon</p>
            <p class="mt-0.5 truncate text-base font-extrabold tabular-nums leading-tight text-on-surface">
                {{ money(principal) }}
            </p>
            <p class="mt-0.5 truncate text-[11px] text-on-surface-variant" :title="productName">
                {{ productName }}
            </p>
        </div>

        <dl class="space-y-1 px-4 pb-3 text-xs text-on-surface-variant">
            <div class="flex items-center gap-2">
                <AppIcon name="group" class="shrink-0 text-base text-outline" />
                <span class="truncate">{{ beneficiariesCount }} pemanfaat</span>
                <span v-if="termMonths" class="ml-auto shrink-0 rounded-full bg-surface-container px-2 py-0.5 text-[11px] font-semibold text-on-surface-variant">
                    {{ termMonths }}
                </span>
            </div>
            <div v-if="Number(loan.service_rate ?? 0) > 0" class="flex items-center gap-2">
                <AppIcon name="percent" class="shrink-0 text-base text-outline" />
                <span>Jasa {{ Number(loan.service_rate).toFixed(2) }}%</span>
            </div>
            <div v-if="stageDateText" class="flex items-center gap-2">
                <AppIcon name="event" class="shrink-0 text-base text-outline" />
                <span class="truncate">{{ stageDateText }}</span>
            </div>
            <div v-if="nextDueText" class="flex items-center gap-2">
                <AppIcon name="schedule" class="shrink-0 text-base text-outline" />
                <span class="truncate">{{ nextDueText }}</span>
            </div>
            <div v-if="remainingText" class="flex items-center gap-2">
                <AppIcon name="account_balance_wallet" class="shrink-0 text-base text-outline" />
                <span class="truncate">{{ remainingText }}</span>
            </div>
        </dl>

        <footer
            v-if="$slots.default || actions.length"
            class="flex flex-wrap items-center justify-between gap-2 border-t border-outline-variant bg-surface-container-low/60 px-3 py-2"
        >
            <AppIcon name="drag_indicator" class="text-base text-outline/70 transition group-hover:text-primary" />
            <div class="flex flex-wrap items-center justify-end gap-1.5">
                <slot name="prefix" />
                <Link :href="`/lending/loans/${loan.row_id}`" data-row-stop>
                    <AppButton variant="ghost" size="compact" icon="visibility" data-row-stop>Detail</AppButton>
                </Link>
                <AppButton
                    v-for="action in actions"
                    :key="action.key"
                    variant="secondary"
                    size="compact"
                    data-row-stop
                    @click="$emit('action', action.key)"
                >
                    {{ action.label }}
                </AppButton>
            </div>
        </footer>
    </article>
</template>