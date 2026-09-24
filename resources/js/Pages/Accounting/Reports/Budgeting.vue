<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import AppSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface BudgetRow {
    row_id: number;
    code: string;
    name: string;
    account_type: 'revenue' | 'expense';
    budget_quarter: number;
    realized_quarter: number;
    variance: number;
    realized_pct: number;
    budget_ytd: number;
    realized_ytd: number;
    variance_ytd: number;
    realized_pct_ytd: number;
}

interface BudgetTotal {
    budget_quarter: number;
    realized_quarter: number;
    variance: number;
    realized_pct: number;
    budget_ytd: number;
    realized_ytd: number;
    variance_ytd: number;
    realized_pct_ytd: number;
}

interface BudgetingPayload {
    budget: {
        row_id: number;
        fiscal_year: number;
        name: string;
        status: string;
        approved_at: string | null;
    } | null;
    period: {
        year: number;
        quarter: number;
        is_ytd: boolean;
        label: string;
        range_label: string;
    };
    identity: {
        legal_name: string;
        short_name: string | null;
    };
    category_filter: string | null;
    categories: Array<{ value: string; label: string }>;
    rows: BudgetRow[];
    totals: {
        revenue: BudgetTotal;
        expense: BudgetTotal;
        net: BudgetTotal;
    };
    generated_at: string | null;
}

const props = defineProps<{
    budget: BudgetingPayload['budget'];
    period: BudgetingPayload['period'];
    identity: BudgetingPayload['identity'];
    category_filter: BudgetingPayload['category_filter'];
    categories: BudgetingPayload['categories'];
    rows: BudgetRow[];
    totals: BudgetingPayload['totals'];
    generated_at: string | null;
    filters: { year: number; quarter: string | number; category: string };
    error?: string;
}>();

const QUARTERS: Array<{ key: string; label: string }> = [
    { key: 'q1', label: 'Q1 (Jan–Mar)' },
    { key: 'q2', label: 'Q2 (Apr–Jun)' },
    { key: 'q3', label: 'Q3 (Jul–Sep)' },
    { key: 'q4', label: 'Q4 (Okt–Des)' },
    { key: 'ytd', label: 'YTD' },
];

const selectedQuarter = ref<string>(
    props.filters.quarter === 'ytd'
        ? 'ytd'
        : `q${Math.max(1, Math.min(4, Number(props.filters.quarter) || 1))}`,
);
const selectedYear = ref<string>(String(props.filters.year));
const selectedCategory = ref<string>(
    props.filters.category === 'all' || props.filters.category === '' ? 'all' : props.filters.category,
);

const money = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const categoryOptions = computed(() => [
    { value: 'all', label: 'Semua Akun' },
    ...props.categories.filter((c) => c.value !== 'all'),
]);

const quarterLabel = computed<string>(() => {
    const tab = QUARTERS.find((q) => q.key === selectedQuarter.value);
    return tab?.label ?? selectedQuarter.value.toUpperCase();
});

const isYtd = computed<boolean>(() => selectedQuarter.value === 'ytd');

const filteredRows = computed<BudgetRow[]>(() => props.rows);
const hasData = computed<boolean>(() => props.rows.length > 0);

const revenueRows = computed<BudgetRow[]>(() => props.rows.filter((r) => r.account_type === 'revenue'));
const expenseRows = computed<BudgetRow[]>(() => props.rows.filter((r) => r.account_type === 'expense'));

function apply(): void {
    router.get(
        '/accounting/reports/budgeting',
        {
            year: selectedYear.value,
            quarter: selectedQuarter.value,
            category: selectedCategory.value === 'all' ? 'all' : selectedCategory.value,
        },
        { preserveScroll: true, replace: true },
    );
}

function setQuarter(key: string): void {
    selectedQuarter.value = key;
    apply();
}

function pdfHref(): string {
    const q = new URLSearchParams({
        year: String(selectedYear.value),
        quarter: selectedQuarter.value,
        category: selectedCategory.value === 'all' ? 'all' : selectedCategory.value,
    });
    return `/accounting/reports/budgeting/pdf?${q.toString()}`;
}

function excelHref(): string {
    const q = new URLSearchParams({
        year: String(selectedYear.value),
        quarter: selectedQuarter.value,
        category: selectedCategory.value === 'all' ? 'all' : selectedCategory.value,
    });
    return `/accounting/reports/budgeting/excel?${q.toString()}`;
}

function fmt(value: number | string | null | undefined): string {
    const n = typeof value === 'number' ? value : Number(value ?? 0);
    if (!Number.isFinite(n)) return money.format(0);
    if (n < 0) return `(${money.format(Math.abs(n))})`;
    return money.format(n);
}

function fmtPct(value: number | string | null | undefined): string {
    const n = typeof value === 'number' ? value : Number(value ?? 0);
    if (!Number.isFinite(n)) return '0.00%';
    return `${n.toFixed(2)}%`;
}

function pctTone(pct: number): string {
    if (pct >= 95 && pct <= 105) return 'text-primary';
    if (pct > 105) return 'text-error';
    return 'text-warning';
}

function statusTone(s: string): string {
    if (s === 'approved') return 'success';
    return 'warning-soft';
}

watch(
    () => props.filters,
    (val) => {
        selectedYear.value = String(val.year);
        selectedQuarter.value = val.quarter === 'ytd' || val.quarter === 'all'
            ? 'ytd'
            : `q${Math.max(1, Math.min(4, Number(val.quarter) || 1))}`;
        selectedCategory.value = !val.category || val.category === 'all' ? 'all' : val.category;
    },
);
</script>

<template>
    <Head title="E-Budgeting per Triwulan" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Pelaporan
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">E-Budgeting per Triwulan</h1>
                    <p class="text-sm text-on-surface-variant">
                        {{ period.range_label }} · Rencana Anggaran vs Realisasi
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <AppBadge v-if="budget" :tone="statusTone(budget.status)">
                        Anggaran: {{ budget.status === 'approved' ? 'Disetujui' : 'Draft' }}
                    </AppBadge>
                    <AppBadge v-else tone="warning-soft">Belum ada anggaran</AppBadge>
                </div>
            </div>

            <AppCard v-if="error" class="border border-error/30 bg-error-container/40 p-4">
                <p class="text-sm text-error">
                    <span class="font-bold">Gagal membangun laporan:</span> {{ error }}
                </p>
            </AppCard>

            <AppCard class="p-4">
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-4">
                    <AppDatePicker v-model="selectedYear" label="Tahun" mode="year" required />
                    <AppSelect
                        v-model="selectedCategory"
                        label="Kategori Akun"
                        :options="categoryOptions"
                    />
                    <div class="flex items-end">
                        <button
                            type="button"
                            class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-on-primary hover:bg-primary/90"
                            @click="apply"
                        >
                            Tampilkan
                        </button>
                    </div>
                    <div class="flex items-end gap-2">
                        <a
                            :href="pdfHref()"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex h-11 flex-1 items-center justify-center rounded-xl border border-outline-variant px-3 text-sm font-semibold text-primary hover:bg-surface-container-low"
                        >
                            PDF
                        </a>
                        <a
                            :href="excelHref()"
                            class="inline-flex h-11 flex-1 items-center justify-center gap-1.5 rounded-xl border border-outline-variant px-3 text-sm font-semibold text-primary hover:bg-surface-container-low"
                        >
                            Excel
                        </a>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-1 border-t border-outline-variant/40 pt-4 text-sm">
                    <span class="mr-2 text-xs font-bold uppercase tracking-wide text-on-surface-variant">
                        Periode:
                    </span>
                    <button
                        v-for="q in QUARTERS"
                        :key="q.key"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="
                            selectedQuarter === q.key
                                ? 'bg-primary text-on-primary'
                                : 'text-on-surface-variant hover:bg-surface-container'
                        "
                        @click="setQuarter(q.key)"
                    >
                        {{ q.label }}
                    </button>
                </div>
            </AppCard>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard class="p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-on-surface-variant">
                        Anggaran {{ quarterLabel }}
                    </p>
                    <p class="mt-2 text-xl font-bold text-primary">
                        {{ fmt(props.totals.revenue.budget_quarter) }}
                    </p>
                    <p class="text-xs text-on-surface-variant">
                        Pendapatan + Beban
                    </p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-on-surface-variant">
                        Realisasi {{ quarterLabel }}
                    </p>
                    <p class="mt-2 text-xl font-bold text-primary">
                        {{ fmt(props.totals.expense.realized_quarter - props.totals.revenue.realized_quarter) }}
                    </p>
                    <p class="text-xs text-on-surface-variant">
                        Selisih Pendapatan − Beban
                    </p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-on-surface-variant">
                        Surplus / (Defisit) Anggaran
                    </p>
                    <p
                        class="mt-2 text-xl font-bold"
                        :class="props.totals.net.variance < 0 ? 'text-error' : 'text-primary'"
                    >
                        {{ fmt(props.totals.net.variance) }}
                    </p>
                    <p class="text-xs text-on-surface-variant">
                        Selisih anggaran vs realisasi {{ quarterLabel }}
                    </p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-on-surface-variant">
                        % Realisasi YTD
                    </p>
                    <p
                        class="mt-2 text-xl font-bold"
                        :class="pctTone(props.totals.net.realized_pct_ytd)"
                    >
                        {{ fmtPct(props.totals.net.realized_pct_ytd) }}
                    </p>
                    <p class="text-xs text-on-surface-variant">
                        Surplus/(Defisit) realized vs budget
                    </p>
                </AppCard>
            </div>

            <AppCard class="overflow-hidden p-0">
                <div class="border-b border-outline-variant/40 bg-surface-container-low px-4 py-3">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">
                        Rincian Akun
                    </h2>
                </div>

                <div v-if="!hasData" class="p-6">
                    <AppEmptyState
                        icon="calculate"
                        title="Belum ada data anggaran"
                        description="Belum ada akun pendapatan/beban untuk filter ini, atau rencana anggaran untuk periode terkait belum diinput."
                    />
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-lowest text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left w-12" rowspan="2">No</th>
                                <th class="px-3 py-2 text-left w-24" rowspan="2">Kode</th>
                                <th class="px-3 py-2 text-left" rowspan="2">Nama Akun</th>
                                <th class="px-3 py-2 text-right" colspan="4">
                                    {{ isYtd ? 'S.d. Triwulan Berjalan' : quarterLabel }}
                                </th>
                                <th class="px-3 py-2 text-right" colspan="4">Year-to-Date</th>
                            </tr>
                            <tr>
                                <th class="px-3 py-2 text-right">Anggaran</th>
                                <th class="px-3 py-2 text-right">Realisasi</th>
                                <th class="px-3 py-2 text-right">Selisih</th>
                                <th class="px-3 py-2 text-right">%</th>
                                <th class="px-3 py-2 text-right">Anggaran</th>
                                <th class="px-3 py-2 text-right">Realisasi</th>
                                <th class="px-3 py-2 text-right">Selisih</th>
                                <th class="px-3 py-2 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="revenueRows.length > 0">
                                <td :colspan="11" class="bg-surface-container px-3 py-1.5 text-xs font-bold uppercase tracking-wide">
                                    Pendapatan
                                </td>
                            </tr>
                            <tr
                                v-for="(r, idx) in revenueRows"
                                :key="r.row_id"
                                class="border-t border-outline-variant/30"
                            >
                                <td class="px-3 py-1.5 tabular-nums">{{ idx + 1 }}</td>
                                <td class="px-3 py-1.5 font-medium tabular-nums">{{ r.code }}</td>
                                <td class="px-3 py-1.5">{{ r.name }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(r.budget_quarter) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(r.realized_quarter) }}</td>
                                <td
                                    class="px-3 py-1.5 text-right tabular-nums"
                                    :class="r.variance < 0 ? 'text-error' : 'text-primary'"
                                >
                                    {{ fmt(r.variance) }}
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums" :class="pctTone(r.realized_pct)">
                                    {{ fmtPct(r.realized_pct) }}
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(r.budget_ytd) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(r.realized_ytd) }}</td>
                                <td
                                    class="px-3 py-1.5 text-right tabular-nums"
                                    :class="r.variance_ytd < 0 ? 'text-error' : 'text-primary'"
                                >
                                    {{ fmt(r.variance_ytd) }}
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums" :class="pctTone(r.realized_pct_ytd)">
                                    {{ fmtPct(r.realized_pct_ytd) }}
                                </td>
                            </tr>
                            <tr v-if="revenueRows.length > 0" class="bg-surface-container-low font-semibold">
                                <td :colspan="3" class="px-3 py-1.5">Total Pendapatan</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(props.totals.revenue.budget_quarter) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(props.totals.revenue.realized_quarter) }}</td>
                                <td
                                    class="px-3 py-1.5 text-right tabular-nums"
                                    :class="props.totals.revenue.variance < 0 ? 'text-error' : ''"
                                >
                                    {{ fmt(props.totals.revenue.variance) }}
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums" :class="pctTone(props.totals.revenue.realized_pct)">
                                    {{ fmtPct(props.totals.revenue.realized_pct) }}
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(props.totals.revenue.budget_ytd) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(props.totals.revenue.realized_ytd) }}</td>
                                <td
                                    class="px-3 py-1.5 text-right tabular-nums"
                                    :class="props.totals.revenue.variance_ytd < 0 ? 'text-error' : ''"
                                >
                                    {{ fmt(props.totals.revenue.variance_ytd) }}
                                </td>
                                <td
                                    class="px-3 py-1.5 text-right tabular-nums"
                                    :class="pctTone(props.totals.revenue.realized_pct_ytd)"
                                >
                                    {{ fmtPct(props.totals.revenue.realized_pct_ytd) }}
                                </td>
                            </tr>

                            <tr v-if="expenseRows.length > 0">
                                <td :colspan="11" class="bg-surface-container px-3 py-1.5 text-xs font-bold uppercase tracking-wide">
                                    Beban
                                </td>
                            </tr>
                            <tr
                                v-for="(r, idx) in expenseRows"
                                :key="r.row_id"
                                class="border-t border-outline-variant/30"
                            >
                                <td class="px-3 py-1.5 tabular-nums">{{ idx + 1 }}</td>
                                <td class="px-3 py-1.5 font-medium tabular-nums">{{ r.code }}</td>
                                <td class="px-3 py-1.5">{{ r.name }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(r.budget_quarter) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(r.realized_quarter) }}</td>
                                <td
                                    class="px-3 py-1.5 text-right tabular-nums"
                                    :class="r.variance < 0 ? 'text-success' : 'text-error'"
                                >
                                    {{ fmt(r.variance) }}
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums" :class="pctTone(r.realized_pct)">
                                    {{ fmtPct(r.realized_pct) }}
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(r.budget_ytd) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(r.realized_ytd) }}</td>
                                <td
                                    class="px-3 py-1.5 text-right tabular-nums"
                                    :class="r.variance_ytd < 0 ? 'text-success' : 'text-error'"
                                >
                                    {{ fmt(r.variance_ytd) }}
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums" :class="pctTone(r.realized_pct_ytd)">
                                    {{ fmtPct(r.realized_pct_ytd) }}
                                </td>
                            </tr>
                            <tr v-if="expenseRows.length > 0" class="bg-surface-container-low font-semibold">
                                <td :colspan="3" class="px-3 py-1.5">Total Beban</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(props.totals.expense.budget_quarter) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(props.totals.expense.realized_quarter) }}</td>
                                <td
                                    class="px-3 py-1.5 text-right tabular-nums"
                                    :class="props.totals.expense.variance < 0 ? 'text-success' : 'text-error'"
                                >
                                    {{ fmt(props.totals.expense.variance) }}
                                </td>
                                <td
                                    class="px-3 py-1.5 text-right tabular-nums"
                                    :class="pctTone(props.totals.expense.realized_pct)"
                                >
                                    {{ fmtPct(props.totals.expense.realized_pct) }}
                                </td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(props.totals.expense.budget_ytd) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(props.totals.expense.realized_ytd) }}</td>
                                <td
                                    class="px-3 py-1.5 text-right tabular-nums"
                                    :class="props.totals.expense.variance_ytd < 0 ? 'text-success' : 'text-error'"
                                >
                                    {{ fmt(props.totals.expense.variance_ytd) }}
                                </td>
                                <td
                                    class="px-3 py-1.5 text-right tabular-nums"
                                    :class="pctTone(props.totals.expense.realized_pct_ytd)"
                                >
                                    {{ fmtPct(props.totals.expense.realized_pct_ytd) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="font-semibold">
                            <tr class="border-t-2 border-outline bg-primary/10 text-primary">
                                <td :colspan="3" class="px-3 py-2">SURPLUS / (DEFISIT)</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(props.totals.net.budget_quarter) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(props.totals.net.realized_quarter) }}</td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums"
                                    :class="props.totals.net.variance < 0 ? 'text-error' : ''"
                                >
                                    {{ fmt(props.totals.net.variance) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums" :class="pctTone(props.totals.net.realized_pct)">
                                    {{ fmtPct(props.totals.net.realized_pct) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(props.totals.net.budget_ytd) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(props.totals.net.realized_ytd) }}</td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums"
                                    :class="props.totals.net.variance_ytd < 0 ? 'text-error' : ''"
                                >
                                    {{ fmt(props.totals.net.variance_ytd) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums" :class="pctTone(props.totals.net.realized_pct_ytd)">
                                    {{ fmtPct(props.totals.net.realized_pct_ytd) }}
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
