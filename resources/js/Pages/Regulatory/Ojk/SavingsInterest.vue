<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

/* -------------------------------------------------------------------------- */
/*                                  Types                                     */
/* -------------------------------------------------------------------------- */

interface PeriodBag {
    year: number;
    month: number | null;
    as_of: string | null;
    from?: string | null;
    until_exclusive?: string | null;
    period_label: string;
    is_monthly: boolean;
}

interface IdentityBag {
    legal_name: string;
    short_name?: string | null;
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

interface InterestRow {
    code: string;
    name: string;
    kind: 'pokok' | 'wajib' | 'sukarela' | 'berjangka' | 'lainnya';
    kind_label: string;
    opening_balance: number;
    closing_balance: number;
    average_balance: number;
    rate: number;
    days: number;
    interest_estimated: number;
}

interface KindBucket {
    label: string;
    opening_balance: number;
    closing_balance: number;
    interest_estimated: number;
    count: number;
    rate: number;
}

interface TotalsBag {
    opening_balance: number;
    closing_balance: number;
    average_balance: number;
    interest_estimated: number;
    average_rate: number;
}

interface ExpenseRow {
    code: string;
    name: string;
    period_debit: number;
    period_credit: number;
    period_net: number;
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
    default_rate: number;
    is_monthly: boolean;
    days_in_period: number;
    interest_rows: InterestRow[];
    buckets: Record<'pokok' | 'wajib' | 'sukarela' | 'berjangka' | 'lainnya', KindBucket>;
    totals: TotalsBag;
    expense_rows: ExpenseRow[];
    expense_total: number;
    generated_at: string | null;
    tenant_id: number;
    monthLabels: MonthLabels;
    filters: FiltersBag;
    error?: string;
}>();

/* -------------------------------------------------------------------------- */
/*                                 State                                      */
/* -------------------------------------------------------------------------- */

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));

const money = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    const list = [];
    for (let y = current + 1; y >= current - 5; y--) {
        list.push({ value: String(y), label: String(y) });
    }
    return list;
});

const monthOptions = computed(() => {
    const opts: { value: string; label: string }[] = [
        { value: 'all', label: 'Januari – Desember' },
    ];
    const labels = props.monthLabels;
    for (let m = 1; m <= 12; m++) {
        opts.push({ value: String(m), label: labels[m] ?? `Bulan ${m}` });
    }
    return opts;
});

const bucketOrder: Array<'pokok' | 'wajib' | 'sukarela' | 'berjangka' | 'lainnya'> = [
    'pokok',
    'wajib',
    'sukarela',
    'berjangka',
    'lainnya',
];

/* -------------------------------------------------------------------------- */
/*                                Computed                                    */
/* -------------------------------------------------------------------------- */

const hasData = computed<boolean>(() => props.interest_rows.length > 0);
const hasExpense = computed<boolean>(() => props.expense_rows.length > 0);

const ratePercent = computed<string>(() => (Number(props.default_rate) * 100).toFixed(2));

function fmt(value: number | string | null | undefined): string {
    const n = typeof value === 'number' ? value : Number(value ?? 0);
    if (!Number.isFinite(n)) return money.format(0);
    if (n < 0) return `(${money.format(Math.abs(n))})`;
    return money.format(n);
}

function apply(): void {
    router.get(
        '/regulatory/ojk/interest',
        { year: selectedYear.value, month: selectedMonth.value },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed<string>(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
    });
    return `/regulatory/ojk/interest/pdf?${q.toString()}`;
});
</script>

<template>
    <Head title="Daftar Bunga Simpanan — OJK" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        OJK · Regulatory
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Daftar Bunga Simpanan</h1>
                    <p class="text-sm text-on-surface-variant">
                        {{ period.period_label ?? '' }} · per {{ period.as_of ?? '-' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <AppBadge :tone="hasData ? 'success' : 'warning'">
                        {{ hasData ? `${props.interest_rows.length} akun` : 'Belum ada data' }}
                    </AppBadge>
                    <a :href="pdfUrl" target="_blank" class="inline-flex">
                        <AppButton variant="outline">
                            <span class="material-symbols-outlined mr-1.5 text-base">picture_as_pdf</span>
                            Cetak PDF
                        </AppButton>
                    </a>
                </div>
            </div>

            <AppCard v-if="error" class="border border-error/30 bg-error-container/40 p-4">
                <p class="text-sm text-error">
                    <span class="font-bold">Gagal membangun laporan:</span> {{ error }}
                </p>
            </AppCard>

            <AppCard class="p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">
                            Tahun
                        </label>
                        <SmartSelect v-model="selectedYear" :options="yearOptions" hide-label @update:model-value="apply" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">
                            Bulan
                        </label>
                        <SmartSelect v-model="selectedMonth" :options="monthOptions" hide-label @update:model-value="apply" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">
                            Tarif asumsi (p.a.)
                        </label>
                        <p class="ml-1 mt-2 text-lg font-semibold tabular-nums text-primary">
                            {{ ratePercent }}%
                        </p>
                        <p class="text-xs text-on-surface-variant">
                            {{ days_in_period }} hari periode
                        </p>
                    </div>
                </div>
            </AppCard>

            <!-- KPI -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">Saldo Awal</p>
                    <p class="mt-1 text-xl font-bold tabular-nums">{{ fmt(totals.opening_balance) }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">Saldo Akhir</p>
                    <p class="mt-1 text-xl font-bold tabular-nums">{{ fmt(totals.closing_balance) }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">Saldo Rata-rata</p>
                    <p class="mt-1 text-xl font-bold tabular-nums">{{ fmt(totals.average_balance) }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">Total Bunga (estimasi)</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-primary">
                        {{ fmt(totals.interest_estimated) }}
                    </p>
                </AppCard>
            </div>

            <!-- Rekap per jenis -->
            <AppCard class="overflow-hidden p-0">
                <div class="border-b border-outline-variant/40 bg-surface-container-low px-4 py-3 text-sm font-bold uppercase tracking-wider text-on-surface-variant">
                    Rekap per Jenis Simpanan
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-lowest text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">Jenis Simpanan</th>
                                <th class="px-3 py-2 text-right">Akun</th>
                                <th class="px-3 py-2 text-right">Saldo Awal</th>
                                <th class="px-3 py-2 text-right">Saldo Akhir</th>
                                <th class="px-3 py-2 text-right">Saldo Rata-rata</th>
                                <th class="px-3 py-2 text-right">Rate</th>
                                <th class="px-3 py-2 text-right">Bunga (estimasi)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="kind in bucketOrder" :key="kind" class="border-t border-outline-variant/20">
                                <td class="px-3 py-2 font-medium">{{ props.buckets[kind]?.label ?? kind }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ props.buckets[kind]?.count ?? 0 }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ fmt(props.buckets[kind]?.opening_balance) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ fmt(props.buckets[kind]?.closing_balance) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ fmt((Number(props.buckets[kind]?.opening_balance ?? 0) + Number(props.buckets[kind]?.closing_balance ?? 0)) / 2) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ ratePercent }}%</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold text-primary">
                                    {{ fmt(props.buckets[kind]?.interest_estimated) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- Rincian per akun -->
            <AppCard class="overflow-hidden p-0">
                <div class="border-b border-outline-variant/40 bg-surface-container-low px-4 py-3 text-sm font-bold uppercase tracking-wider text-on-surface-variant">
                    Rincian per Akun
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-lowest text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left w-16">No</th>
                                <th class="px-3 py-2 text-left w-28">Kode</th>
                                <th class="px-3 py-2 text-left">Nama Akun</th>
                                <th class="px-3 py-2 text-left w-40">Jenis</th>
                                <th class="px-3 py-2 text-right">Saldo Awal</th>
                                <th class="px-3 py-2 text-right">Saldo Akhir</th>
                                <th class="px-3 py-2 text-right">Saldo Rata-rata</th>
                                <th class="px-3 py-2 text-right">Rate</th>
                                <th class="px-3 py-2 text-right">Bunga</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!hasData">
                                <td colspan="9" class="px-3 py-8 text-center text-on-surface-variant">
                                    Belum ada data simpanan untuk periode ini.
                                </td>
                            </tr>
                            <tr v-for="(row, idx) in props.interest_rows" :key="`${row.code}-${idx}`" class="border-t border-outline-variant/20">
                                <td class="px-3 py-2 tabular-nums">{{ idx + 1 }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ row.code }}</td>
                                <td class="px-3 py-2">{{ row.name }}</td>
                                <td class="px-3 py-2">
                                    <AppBadge tone="info-soft">{{ row.kind_label }}</AppBadge>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(row.opening_balance) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(row.closing_balance) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(row.average_balance) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ (Number(row.rate) * 100).toFixed(2) }}%</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold text-primary">
                                    {{ fmt(row.interest_estimated) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- Beban bunga dari jurnal (jika ada) -->
            <AppCard v-if="hasExpense" class="overflow-hidden p-0">
                <div class="border-b border-outline-variant/40 bg-surface-container-low px-4 py-3 text-sm font-bold uppercase tracking-wider text-on-surface-variant">
                    Beban Bunga Simpanan (dari Jurnal Akun 5.x Bunga Simpanan)
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-lowest text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left w-16">No</th>
                                <th class="px-3 py-2 text-left w-28">Kode</th>
                                <th class="px-3 py-2 text-left">Nama Akun</th>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Kredit</th>
                                <th class="px-3 py-2 text-right">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, idx) in props.expense_rows" :key="`${row.code}-${idx}`" class="border-t border-outline-variant/20">
                                <td class="px-3 py-2 tabular-nums">{{ idx + 1 }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ row.code }}</td>
                                <td class="px-3 py-2">{{ row.name }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(row.period_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(row.period_credit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ fmt(row.period_net) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td colspan="3" class="px-3 py-2">Total Beban Bunga Simpanan</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(props.expense_total) }}</td>
                                <td colspan="2"></td>
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
