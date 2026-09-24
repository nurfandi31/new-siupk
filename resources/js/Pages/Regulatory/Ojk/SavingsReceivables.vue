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
    from: string | null;
    until_exclusive: string | null;
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

interface SavingsRow {
    row_id: number;
    code: string;
    name: string;
    kind: 'pokok' | 'wajib' | 'sukarela' | 'berjangka' | 'lainnya';
    kind_label: string;
    closing_balance: number;
}

interface ReceivableRow {
    row_id: number;
    code: string;
    name: string;
    kind: 'pokok' | 'bunga' | 'lain';
    kind_label: string;
    closing_balance: number;
}

interface KindBucket {
    label: string;
    closing_balance: number;
    count: number;
}

interface PassivaBag {
    rows: SavingsRow[];
    by_kind: Record<'pokok' | 'wajib' | 'sukarela' | 'berjangka' | 'lainnya', KindBucket>;
    total_closing: number;
}

interface AktivaBag {
    rows: ReceivableRow[];
    by_kind: Record<'pokok' | 'bunga' | 'lain', KindBucket>;
    pokok_closing: number;
    bunga_closing: number;
    lain_closing: number;
    total_closing: number;
}

interface TotalsBag {
    savings: number;
    receivables: number;
    selisih: number;
}

interface RatioBag {
    piutang_to_simpanan: number;
    pokok_to_simpanan: number;
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
    passiva: PassivaBag;
    aktiva: AktivaBag;
    totals: TotalsBag;
    ratio: RatioBag;
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

/* -------------------------------------------------------------------------- */
/*                                Computed                                    */
/* -------------------------------------------------------------------------- */

const periodLabel = computed<string>(() => props.period.period_label ?? '');

const hasData = computed<boolean>(
    () => (props.passiva?.rows?.length ?? 0) > 0 || (props.aktiva?.rows?.length ?? 0) > 0,
);

function fmt(value: number | string | null | undefined): string {
    const n = typeof value === 'number' ? value : Number(value ?? 0);
    if (!Number.isFinite(n)) return money.format(0);
    if (n < 0) return `(${money.format(Math.abs(n))})`;
    return money.format(n);
}

function apply(): void {
    router.get(
        '/regulatory/ojk/savings-receivables',
        { year: selectedYear.value, month: selectedMonth.value },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed<string>(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
    });
    return `/regulatory/ojk/savings-receivables/pdf?${q.toString()}`;
});

const savingsKindOrder: Array<'pokok' | 'wajib' | 'sukarela' | 'berjangka' | 'lainnya'> = [
    'pokok',
    'wajib',
    'sukarela',
    'berjangka',
    'lainnya',
];

const receivableKindOrder: Array<'pokok' | 'bunga' | 'lain'> = ['pokok', 'bunga', 'lain'];
</script>

<template>
    <Head title="Simpanan & Piutang (SMPN) — OJK" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        OJK · Regulatory
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Simpanan &amp; Piutang (SMPN)</h1>
                    <p class="text-sm text-on-surface-variant">
                        {{ periodLabel }} · per {{ period.as_of ?? '-' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <AppBadge :tone="hasData ? 'success' : 'warning'">
                        {{ hasData ? 'Data tersedia' : 'Belum ada data' }}
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
                </div>
            </AppCard>

            <!-- KPI ringkas -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">Total Simpanan (Pasiva)</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-primary">{{ fmt(totals.savings) }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">Total Piutang (Aktiva)</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-primary">{{ fmt(totals.receivables) }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">Rasio Piutang / Simpanan</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-primary">
                        {{ Number(ratio.piutang_to_simpanan).toFixed(2) }}%
                    </p>
                </AppCard>
            </div>

            <!-- Tabel dua sisi -->
            <AppCard class="overflow-hidden p-0">
                <div class="flex items-center justify-between border-b border-outline-variant/40 bg-surface-container-low px-4 py-3">
                    <h2 class="text-base font-bold text-on-surface">Rekapitulasi Pasiva vs Aktiva</h2>
                    <p class="text-xs text-on-surface-variant">
                        Per {{ period.as_of ?? '-' }}
                    </p>
                </div>
                <div class="grid grid-cols-1 gap-0 md:grid-cols-2">
                    <!-- Pasiva -->
                    <div>
                        <div class="bg-primary px-4 py-2 text-sm font-bold uppercase tracking-wider text-on-primary">
                            Sisi PASIVA — Simpanan
                        </div>
                        <table class="w-full text-sm">
                            <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                                <tr>
                                    <th class="px-3 py-2 text-left">Jenis Simpanan</th>
                                    <th class="px-3 py-2 text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="kind in savingsKindOrder" :key="`p-${kind}`" class="border-t border-outline-variant/20">
                                    <td class="px-3 py-2">{{ passiva.by_kind[kind]?.label ?? kind }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ fmt(passiva.by_kind[kind]?.closing_balance) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                    <td class="px-3 py-2">Total Simpanan</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-primary">
                                        {{ fmt(totals.savings) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Aktiva -->
                    <div>
                        <div class="bg-secondary px-4 py-2 text-sm font-bold uppercase tracking-wider text-on-primary">
                            Sisi AKTIVA — Piutang
                        </div>
                        <table class="w-full text-sm">
                            <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                                <tr>
                                    <th class="px-3 py-2 text-left">Jenis Piutang</th>
                                    <th class="px-3 py-2 text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="kind in receivableKindOrder" :key="`a-${kind}`" class="border-t border-outline-variant/20">
                                    <td class="px-3 py-2">{{ aktiva.by_kind[kind]?.label ?? kind }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ fmt(aktiva.by_kind[kind]?.closing_balance) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                    <td class="px-3 py-2">Total Piutang</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-primary">
                                        {{ fmt(totals.receivables) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="border-t border-outline-variant/40 bg-surface-container-lowest px-4 py-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span><b>Selisih (Simpanan − Piutang):</b> {{ fmt(totals.selisih) }}</span>
                        <span class="text-on-surface-variant">
                            Rasio Piutang Pokok / Simpanan: <b>{{ Number(ratio.pokok_to_simpanan).toFixed(2) }}%</b>
                        </span>
                    </div>
                </div>
            </AppCard>

            <p v-if="generated_at" class="text-right text-[11px] text-on-surface-variant">
                Digenerate pada {{ generated_at }}
            </p>
        </div>
    </AuthenticatedLayout>
</template>
