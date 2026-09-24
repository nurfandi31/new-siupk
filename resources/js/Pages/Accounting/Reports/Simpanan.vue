<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

/* -------------------------------------------------------------------------- */
/*                                  Types                                     */
/* -------------------------------------------------------------------------- */

interface PeriodBag {
    year: number;
    month: number | null;
    as_of: string | null;
    from: string | null;
    until_exclusive: string | null;
    period_label: string;
    is_monthly: boolean;
}

interface IdentityBag {
    legal_name: string;
    short_name: string | null;
    logo_url?: string | null;
    registration_number?: string;
    address?: string;
    phone?: string;
    district_name?: string;
    regency_name?: string;
    manager_name?: string;
    manager_title?: string;
    treasurer_name?: string;
    treasurer_title?: string;
}

type SavingsKind = 'pokok' | 'wajib' | 'sukarela' | 'berjangka' | 'lainnya';

interface SavingsRow {
    row_id: number;
    code: string;
    name: string;
    parent_code: string | null;
    parent_name: string | null;
    kind: SavingsKind;
    kind_label: string;
    opening_balance: number;
    period_debit: number;
    period_credit: number;
    closing_balance: number;
}

interface KindBucket {
    label: string;
    opening_balance: number;
    period_debit: number;
    period_credit: number;
    closing_balance: number;
    count: number;
}

interface SimpananTotals {
    opening_balance: number;
    period_debit: number;
    period_credit: number;
    closing_balance: number;
}

interface FiltersBag {
    year: number;
    month: number | string;
}

interface MonthLabels {
    all: string;
    [k: number]: string;
}

const props = defineProps<{
    period: PeriodBag;
    identity: IdentityBag;
    rows: SavingsRow[];
    by_kind: Record<SavingsKind, KindBucket>;
    totals: SimpananTotals;
    generated_at: string | null;
    tenant_id: number;
    monthLabels: MonthLabels;
    filters: FiltersBag;
    error?: string;
}>();

/* -------------------------------------------------------------------------- */
/*                                 State                                      */
/* -------------------------------------------------------------------------- */

const KIND_TABS: Array<{ key: 'all' | SavingsKind; label: string }> = [
    { key: 'all', label: 'Semua' },
    { key: 'pokok', label: 'Simpanan Pokok' },
    { key: 'wajib', label: 'Simpanan Wajib' },
    { key: 'sukarela', label: 'Simpanan Sukarela' },
    { key: 'berjangka', label: 'Simpanan Berjangka' },
    { key: 'lainnya', label: 'Lainnya' },
];

const activeTab = ref<'all' | SavingsKind>('all');

const money = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/* -------------------------------------------------------------------------- */
/*                                Computed                                    */
/* -------------------------------------------------------------------------- */

const filteredRows = computed<SavingsRow[]>(() => {
    if (activeTab.value === 'all') return props.rows;
    return props.rows.filter((row) => row.kind === activeTab.value);
});

const visibleTotals = computed<SimpananTotals>(() => {
    if (activeTab.value === 'all') return props.totals;
    const bucket = props.by_kind[activeTab.value];
    return {
        opening_balance: bucket?.opening_balance ?? 0,
        period_debit: bucket?.period_debit ?? 0,
        period_credit: bucket?.period_credit ?? 0,
        closing_balance: bucket?.closing_balance ?? 0,
    };
});

const periodLabel = computed<string>(() => props.period.period_label ?? '');

const hasData = computed<boolean>(() => props.rows.length > 0);

function fmt(value: number | string | null | undefined): string {
    const n = typeof value === 'number' ? value : Number(value ?? 0);
    if (!Number.isFinite(n)) return money.format(0);
    if (n < 0) return `(${money.format(Math.abs(n))})`;
    return money.format(n);
}

function isEmptyKind(kind: SavingsKind): boolean {
    const bucket = props.by_kind[kind];
    return !bucket || (bucket.count ?? 0) === 0;
}
</script>

<template>
    <Head title="Daftar Simpanan" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Pelaporan
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Daftar Simpanan</h1>
                    <p class="text-sm text-on-surface-variant">
                        {{ periodLabel }} · per {{ period.as_of ?? '-' }}
                    </p>
                </div>
                <AppBadge :tone="hasData ? 'success' : 'warning'">
                    {{ hasData ? `${props.rows.length} akun` : 'Belum ada data' }}
                </AppBadge>
            </div>

            <AppCard v-if="error" class="border border-error/30 bg-error-container/40 p-4">
                <p class="text-sm text-error">
                    <span class="font-bold">Gagal membangun laporan:</span> {{ error }}
                </p>
            </AppCard>

            <AppCard class="p-4">
                <ReportPeriodFilter
                    :year="filters.year"
                    :month="filters.month"
                    base-url="/accounting/reports/simpanan"
                    pdf-url="/accounting/reports/simpanan/pdf"
                    excel-url="/accounting/reports/simpanan/excel"
                />
            </AppCard>

            <!-- Tabs / breakdown per savings kind -->
            <AppCard class="overflow-hidden p-0">
                <div class="flex flex-wrap items-center gap-1 border-b border-outline-variant/40 bg-surface-container-low px-4 py-2 text-sm">
                    <button
                        v-for="tab in KIND_TABS"
                        :key="tab.key"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="
                            activeTab === tab.key
                                ? 'bg-primary text-on-primary'
                                : 'text-on-surface-variant hover:bg-surface-container'
                        "
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                        <span
                            v-if="tab.key !== 'all' && !isEmptyKind(tab.key)"
                            class="ml-1 inline-block rounded-full bg-white/20 px-1.5 text-[10px]"
                        >
                            {{ props.by_kind[tab.key]?.count ?? 0 }}
                        </span>
                    </button>
                </div>

                <!-- Per-kind sub-totals -->
                <div
                    v-if="activeTab !== 'all'"
                    class="grid grid-cols-2 gap-2 border-b border-outline-variant/40 bg-surface-container-lowest p-4 text-xs sm:grid-cols-4"
                >
                    <div>
                        <p class="text-on-surface-variant">Saldo Awal</p>
                        <p class="font-bold tabular-nums text-primary">
                            {{ fmt(visibleTotals.opening_balance) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-on-surface-variant">Mutasi Debit</p>
                        <p class="font-bold tabular-nums">{{ fmt(visibleTotals.period_debit) }}</p>
                    </div>
                    <div>
                        <p class="text-on-surface-variant">Mutasi Kredit</p>
                        <p class="font-bold tabular-nums">{{ fmt(visibleTotals.period_credit) }}</p>
                    </div>
                    <div>
                        <p class="text-on-surface-variant">Saldo Akhir</p>
                        <p class="font-bold tabular-nums" :class="visibleTotals.closing_balance < 0 ? 'text-error' : 'text-primary'">
                            {{ fmt(visibleTotals.closing_balance) }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left w-16">No</th>
                                <th class="px-3 py-2 text-left w-28">Kode Akun</th>
                                <th class="px-3 py-2 text-left">Nama Akun</th>
                                <th class="px-3 py-2 text-left w-40">Jenis Simpanan</th>
                                <th class="px-3 py-2 text-right w-32">Saldo Awal</th>
                                <th class="px-3 py-2 text-right w-32">Mutasi Debit</th>
                                <th class="px-3 py-2 text-right w-32">Mutasi Kredit</th>
                                <th class="px-3 py-2 text-right w-32">Saldo Akhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredRows.length === 0">
                                <td colspan="8" class="px-3 py-8 text-center text-on-surface-variant">
                                    Belum ada data simpanan untuk filter ini.
                                </td>
                            </tr>
                            <tr
                                v-for="(row, idx) in filteredRows"
                                :key="row.row_id"
                                class="border-t border-outline-variant/30"
                            >
                                <td class="px-3 py-2 tabular-nums">{{ idx + 1 }}</td>
                                <td class="px-3 py-2 font-medium tabular-nums">{{ row.code }}</td>
                                <td class="px-3 py-2">
                                    {{ row.name }}
                                    <span v-if="row.parent_name" class="block text-[11px] text-on-surface-variant">
                                        {{ row.parent_code }} · {{ row.parent_name }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <AppBadge tone="info-soft">{{ row.kind_label }}</AppBadge>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums" :class="row.opening_balance < 0 ? 'text-error' : ''">
                                    {{ fmt(row.opening_balance) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(row.period_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(row.period_credit) }}</td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums font-semibold"
                                    :class="row.closing_balance < 0 ? 'text-error' : 'text-primary'"
                                >
                                    {{ fmt(row.closing_balance) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2" colspan="4">
                                    {{ activeTab === 'all' ? 'Total Daftar Simpanan' : `Total ${props.by_kind[activeTab]?.label ?? ''}` }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(visibleTotals.opening_balance) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(visibleTotals.period_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(visibleTotals.period_credit) }}</td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums"
                                    :class="visibleTotals.closing_balance < 0 ? 'text-error' : 'text-primary'"
                                >
                                    {{ fmt(visibleTotals.closing_balance) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>

            <p v-if="generated_at" class="text-right text-[11px] text-on-surface-variant">
                Digenerate pada {{ generated_at }}
            </p>
        </div>
    </AuthenticatedLayout>
</template>
