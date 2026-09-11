<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppFilterPill from '../../../Components/AppFilterPill.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    as_of: { type: String, required: true },
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    filter: { type: String, required: true },
    rows: { type: Array, required: true },
    totals: { type: Object, required: true },
    aging: { type: Array, required: true },
    by_village: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
});

const asOf = ref(props.filters.as_of);
const filter = ref(props.filters.filter || 'all');
const syncing = ref(false);

watch(
    () => [props.filters.as_of, props.filters.filter],
    () => {
        syncing.value = true;
        asOf.value = props.filters.as_of;
        filter.value = props.filters.filter || 'all';
        queueMicrotask(() => {
            syncing.value = false;
        });
    },
);

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}
function formatDate(v) {
    if (!v) return '—';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return '—';
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
}

const filterOptions = [
    { value: 'all', label: 'Semua aktif' },
    { value: 'overdue', label: 'Tunggakan' },
    { value: 'current', label: 'Lancar' },
];

function apply() {
    if (syncing.value) return;
    router.get(
        '/lending/reports/portfolio',
        { as_of: asOf.value, filter: filter.value },
        { preserveState: false, preserveScroll: true, replace: true },
    );
}

watch([asOf, filter], () => {
    if (syncing.value) return;
    apply();
});

const pdfHref = computed(() => {
    const q = new URLSearchParams({ as_of: asOf.value || props.as_of, filter: filter.value || 'all' });
    return `/lending/reports/portfolio/pdf?${q.toString()}`;
});

/** Group loans under desa headers + subtotal per desa. */
const villageSections = computed(() => {
    const map = new Map();
    for (const row of props.rows) {
        const key = row.village_name || '—';
        if (!map.has(key)) {
            map.set(key, {
                village: key,
                rows: [],
                count: 0,
                principal_disbursed: 0,
                principal_remaining: 0,
                interest_remaining: 0,
                overdue_principal: 0,
                overdue_interest: 0,
                overdue_count: 0,
            });
        }
        const s = map.get(key);
        s.rows.push(row);
        s.count += 1;
        s.principal_disbursed += Number(row.principal_disbursed || 0);
        s.principal_remaining += Number(row.principal_remaining || 0);
        s.interest_remaining += Number(row.interest_remaining || 0);
        s.overdue_principal += Number(row.overdue_principal || 0);
        s.overdue_interest += Number(row.overdue_interest || 0);
        if (Number(row.days_overdue || 0) > 0) s.overdue_count += 1;
    }
    return Array.from(map.values());
});
</script>

<template>
    <Head title="Portofolio Pinjaman" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Portofolio Pinjaman</h1>
                    <p class="text-sm text-on-surface-variant">{{ period.period_label }}</p>
                </div>
                <a :href="pdfHref" target="_blank" rel="noopener">
                    <AppButton variant="secondary" icon="picture_as_pdf" size="compact">PDF</AppButton>
                </a>
            </div>

            <AppCard class="p-4">
                <div class="grid gap-3 sm:grid-cols-[200px_1fr_auto] sm:items-end">
                    <AppDatePicker v-model="asOf" mode="day" label="Posisi per" />
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Filter</p>
                        <AppFilterPill
                            v-model="filter"
                            :items="filterOptions"
                            variant="outline"
                            aria-label="Filter status pinjaman"
                        />
                    </div>
                </div>
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pinjaman</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.count }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ totals.overdue_count }} tunggakan</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sisa Pokok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.principal_remaining) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sisa Jasa</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.interest_remaining) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Nilai Tunggakan</p>
                    <p class="mt-2 text-2xl font-bold" :class="totals.overdue_amount > 0 ? 'text-error' : 'text-primary'">
                        {{ formatMoney(totals.overdue_amount) }}
                    </p>
                </AppCard>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <AppCard v-for="bucket in aging" :key="bucket.key" class="p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-on-surface-variant">{{ bucket.label }}</p>
                    <p class="mt-2 text-lg font-bold text-primary">{{ bucket.count }}</p>
                    <p class="mt-1 text-sm tabular-nums text-on-surface">{{ formatMoney(bucket.principal) }}</p>
                    <p v-if="bucket.overdue > 0" class="mt-0.5 text-xs tabular-nums text-error">
                        tunggakan {{ formatMoney(bucket.overdue) }}
                    </p>
                </AppCard>
            </div>

            <AppCard v-if="by_village.length" class="overflow-hidden p-0">
                <div class="border-b border-outline-variant px-4 py-3">
                    <h2 class="text-sm font-bold text-primary">Kolektibilitas ringkas per desa</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">Desa</th>
                                <th class="px-3 py-2 text-right">Pinjaman</th>
                                <th class="px-3 py-2 text-right">Sisa Pokok</th>
                                <th class="px-3 py-2 text-right">Tunggakan</th>
                                <th class="px-3 py-2 text-right"># Nunggak</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in by_village" :key="v.village" class="border-t border-outline-variant/40">
                                <td class="px-3 py-2 font-medium">{{ v.village }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ v.count }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(v.principal) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums" :class="v.overdue > 0 ? 'text-error font-semibold' : ''">
                                    {{ formatMoney(v.overdue) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ v.overdue_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">ID</th>
                                <th class="px-3 py-2 text-left">Kelompok</th>
                                <th class="px-3 py-2 text-left">Produk</th>
                                <th class="px-3 py-2 text-left">Cair</th>
                                <th class="px-3 py-2 text-right">Alokasi</th>
                                <th class="px-3 py-2 text-right">Sisa Pokok</th>
                                <th class="px-3 py-2 text-right">Sisa Jasa</th>
                                <th class="px-3 py-2 text-right">Tungg. Pokok</th>
                                <th class="px-3 py-2 text-right">Tungg. Jasa</th>
                                <th class="px-3 py-2 text-center">Hari</th>
                                <th class="px-3 py-2 text-left">Jatuh tempo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="11" class="px-3 py-8 text-center text-on-surface-variant">
                                    Tidak ada pinjaman aktif untuk filter ini.
                                </td>
                            </tr>
                            <template v-for="(section, sectionIndex) in villageSections" :key="section.village">
                                <tr v-if="sectionIndex > 0" aria-hidden="true">
                                    <td colspan="11" class="h-4 border-0 bg-transparent p-0" />
                                </tr>
                                <tr class="border-y border-outline-variant bg-surface-container-low">
                                    <td colspan="11" class="px-3 py-2.5 text-sm font-bold uppercase tracking-wide text-primary">
                                        Desa {{ section.village }}
                                        <span class="ml-2 font-medium normal-case tracking-normal text-on-surface-variant">
                                            · {{ section.count }} pinjaman
                                        </span>
                                    </td>
                                </tr>
                                <tr
                                    v-for="row in section.rows"
                                    :key="row.row_id"
                                    class="border-t border-outline-variant/40 hover:bg-surface-container-low/50"
                                >
                                    <td class="px-3 py-2">
                                        <a :href="`/lending/loans/${row.row_id}`" class="font-semibold text-primary hover:underline">
                                            #{{ row.id }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-on-surface">{{ row.group_name }}</div>
                                        <div class="text-[10px] uppercase tracking-wide text-on-surface-variant">{{ row.loan_number }}</div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <AppBadge tone="neutral">{{ row.product_code }}</AppBadge>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ formatDate(row.disbursed_at) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.principal_disbursed) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ formatMoney(row.principal_remaining) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.interest_remaining) }}</td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums"
                                        :class="row.overdue_principal > 0 ? 'font-semibold text-error' : ''"
                                    >
                                        {{ formatMoney(row.overdue_principal) }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums"
                                        :class="row.overdue_interest > 0 ? 'font-semibold text-error' : ''"
                                    >
                                        {{ formatMoney(row.overdue_interest) }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-center tabular-nums"
                                        :class="row.days_overdue > 0 ? 'font-semibold text-error' : 'text-on-surface-variant'"
                                    >
                                        {{ row.days_overdue > 0 ? row.days_overdue : '—' }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ formatDate(row.next_due_date) }}</td>
                                </tr>
                                <tr class="border-t border-outline-variant bg-surface-container-low/50 text-sm font-semibold">
                                    <td class="px-3 py-2.5" colspan="4">
                                        Total {{ section.village }}
                                        <span v-if="section.overdue_count" class="ml-1 font-medium text-error">
                                            · {{ section.overdue_count }} nunggak
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ formatMoney(section.principal_disbursed) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ formatMoney(section.principal_remaining) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ formatMoney(section.interest_remaining) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-error">{{ formatMoney(section.overdue_principal) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-error">{{ formatMoney(section.overdue_interest) }}</td>
                                    <td colspan="2" />
                                </tr>
                            </template>
                        </tbody>
                        <tfoot v-if="rows.length > 0">
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2" colspan="4">Jumlah seluruh desa ({{ totals.count }})</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.principal_disbursed) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.principal_remaining) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.interest_remaining) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-error">{{ formatMoney(totals.overdue_principal) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-error">{{ formatMoney(totals.overdue_interest) }}</td>
                                <td colspan="2" />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
