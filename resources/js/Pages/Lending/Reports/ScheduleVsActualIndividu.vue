<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface ScheduleRow {
    installment_number: number;
    due_date: string;
    paid_at: string | null;
    status: string;
    realization_status: string;
    plan_principal: number;
    plan_interest: number;
    actual_principal: number;
    actual_interest: number;
    gap_principal: number;
    gap_interest: number;
    delay_days: number | null;
    is_late: boolean;
    is_paid: boolean;
}

interface LoanBlock {
    loan_row_id: number;
    loan_id: number;
    loan_number: string;
    member_name: string;
    member_number: string | null;
    village_name: string;
    product_code: string;
    product_name: string;
    principal_amount: number;
    disbursed_at: string | null;
    rows: ScheduleRow[];
    totals: {
        installment_count: number;
        plan_principal: number;
        plan_interest: number;
        actual_principal: number;
        actual_interest: number;
        gap_principal: number;
        gap_interest: number;
        realized_count: number;
        late_count: number;
        total_delay_days: number;
        avg_delay_days: number;
        pct_realization: number;
    };
}

interface GrandTotals {
    loan_count: number;
    installment_count: number;
    plan_principal: number;
    plan_interest: number;
    actual_principal: number;
    actual_interest: number;
    gap_principal: number;
    gap_interest: number;
    realized_count: number;
    late_count: number;
    total_delay_days: number;
    avg_delay_days: number;
    pct_realization: number;
}

interface Identity {
    legal_name: string;
    short_name?: string | null;
}

interface Period {
    period_label: string;
    as_of: string;
}

interface MonthLabels {
    [key: string]: string;
}

interface Filters {
    year: number;
    month: number;
    loan_id: string | null;
    product: string | null;
}

const props = defineProps<{
    year: number;
    month: number;
    period: Period;
    identity: Identity;
    loans: LoanBlock[];
    totals: GrandTotals;
    monthLabels: MonthLabels;
    filters: Filters;
}>();

const selectedYear = ref<number>(props.filters.year);
const selectedMonth = ref<number>(props.filters.month);
const selectedLoanId = ref<string>(props.filters.loan_id ?? '');
const selectedProduct = ref<string>(props.filters.product ?? 'all');

const monthOptions = computed(() =>
    Object.entries(props.monthLabels).map(([v, label]) => ({ value: String(v), label: String(label) })),
);

const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    const list: { value: string; label: string }[] = [];
    for (let y = current + 1; y >= current - 5; y--) {
        list.push({ value: String(y), label: String(y) });
    }
    return list;
});

const productOptions = computed(() => {
    const seen = new Map<string, { value: string; label: string }>();
    for (const loan of props.loans) {
        if (loan.product_code && loan.product_code !== '—') {
            seen.set(loan.product_code, {
                value: loan.product_code,
                label: `${loan.product_code} — ${loan.product_name}`,
            });
        }
    }
    return [{ value: 'all', label: 'Semua Produk' }, ...seen.values()];
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v: number | string | null | undefined): string {
    return money.format(Number(v ?? 0));
}

function apply(): void {
    const params = new URLSearchParams({
        year: String(selectedYear.value),
        month: String(selectedMonth.value),
    });
    if (selectedLoanId.value && selectedLoanId.value.trim() !== '') {
        params.set('loan_id', selectedLoanId.value.trim());
    }
    if (selectedProduct.value && selectedProduct.value !== 'all') {
        params.set('product', selectedProduct.value);
    }
    router.get(`/lending/reports/schedule-vs-actual-individu?${params.toString()}`, {}, {
        preserveScroll: true,
        replace: true,
    });
}

const pdfHref = computed<string>(() => {
    const params = new URLSearchParams({
        year: String(selectedYear.value),
        month: String(selectedMonth.value),
    });
    if (selectedLoanId.value && selectedLoanId.value.trim() !== '') {
        params.set('loan_id', selectedLoanId.value.trim());
    }
    if (selectedProduct.value && selectedProduct.value !== 'all') {
        params.set('product', selectedProduct.value);
    }
    return `/lending/reports/schedule-vs-actual-individu/pdf?${params.toString()}`;
});

const excelHref = computed<string>(() => {
    const params = new URLSearchParams({
        year: String(selectedYear.value),
        month: String(selectedMonth.value),
    });
    if (selectedLoanId.value && selectedLoanId.value.trim() !== '') {
        params.set('loan_id', selectedLoanId.value.trim());
    }
    if (selectedProduct.value && selectedProduct.value !== 'all') {
        params.set('product', selectedProduct.value);
    }
    return `/lending/reports/schedule-vs-actual-individu/excel?${params.toString()}`;
});

function statusClass(s: string): string {
    if (s === 'Tepat Waktu') return 'bg-secondary/10 text-secondary';
    if (s === 'Terlambat') return 'bg-warning/10 text-warning';
    if (s === 'Lewat Jatuh Tempo') return 'bg-error/10 text-error';
    return 'bg-surface-container text-on-surface-variant';
}
</script>

<template>
    <Head title="Rencana vs Realisasi — Pinjaman Individu" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan Piutang</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Rencana & Realisasi — Pinjaman Individu</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Bandingkan rencana angsuran vs realisasi per pinjaman individu —
                        {{ period.period_label }} — {{ identity.legal_name }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="pdfHref" target="_blank" rel="noopener">
                        <AppButton variant="secondary" icon="picture_as_pdf" size="compact">PDF</AppButton>
                    </a>
                    <a :href="excelHref" rel="noopener">
                        <AppButton variant="outline" icon="table_view" size="compact">Excel</AppButton>
                    </a>
                </div>
            </div>

            <AppCard class="p-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <AppDatePicker v-model="selectedYear" mode="year" label="Tahun" />
                    <SmartSelect v-model="selectedMonth" :options="monthOptions" label="Bulan" />
                    <SmartSelect v-model="selectedProduct" :options="productOptions" label="Produk" />
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Loan ID / Nomor</label>
                        <input
                            v-model="selectedLoanId"
                            type="text"
                            placeholder="contoh: 123 atau L-2025-001"
                            class="block w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                    </div>
                    <div class="flex items-end">
                        <AppButton type="button" class="!min-h-11 h-11 px-5" @click="apply">Tampilkan</AppButton>
                    </div>
                </div>
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Rencana Pokok</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ formatMoney(totals.plan_principal) }}</p>
                    <p class="text-xs text-on-surface-variant">Rencana Jasa {{ formatMoney(totals.plan_interest) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Realisasi Pokok</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ formatMoney(totals.actual_principal) }}</p>
                    <p class="text-xs text-on-surface-variant">Realisasi Jasa {{ formatMoney(totals.actual_interest) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">% Realisasi</p>
                    <p class="mt-2 text-xl font-bold" :class="totals.pct_realization >= 90 ? 'text-secondary' : 'text-warning'">
                        {{ totals.pct_realization }}%
                    </p>
                    <p class="text-xs text-on-surface-variant">{{ totals.realized_count }} dari {{ totals.installment_count }} angsuran</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Rata-rata Keterlambatan</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ totals.avg_delay_days }} hari</p>
                    <p class="text-xs text-on-surface-variant">{{ totals.late_count }} angsuran terlambat</p>
                </AppCard>
            </div>

            <div v-if="loans.length === 0">
                <AppCard class="p-8 text-center">
                    <p class="text-sm text-on-surface-variant">Tidak ada pinjaman individu aktif dengan angsuran jatuh tempo pada periode ini.</p>
                </AppCard>
            </div>

            <div v-for="loan in loans" :key="loan.loan_row_id" class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-on-surface">
                            {{ loan.member_name }}
                            <span class="text-sm font-normal text-on-surface-variant">
                                ({{ loan.loan_number }} · #{{ loan.loan_id }})
                            </span>
                        </h2>
                        <p class="text-xs text-on-surface-variant">
                            {{ loan.village_name }} · {{ loan.product_code }} · Alokasi {{ formatMoney(loan.principal_amount) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="inline-flex rounded-full bg-surface-container px-3 py-1 font-semibold">
                            Realisasi {{ loan.totals.pct_realization }}%
                        </span>
                        <span
                            class="inline-flex rounded-full px-3 py-1 font-semibold"
                            :class="loan.totals.gap_principal + loan.totals.gap_interest > 0 ? 'bg-warning/10 text-warning' : 'bg-secondary/10 text-secondary'"
                        >
                            Gap {{ formatMoney(loan.totals.gap_principal + loan.totals.gap_interest) }}
                        </span>
                        <span v-if="loan.totals.late_count > 0" class="inline-flex rounded-full bg-error/10 px-3 py-1 font-semibold text-error">
                            {{ loan.totals.late_count }} terlambat (rerata {{ loan.totals.avg_delay_days }} hari)
                        </span>
                    </div>
                </div>

                <AppCard class="overflow-hidden p-0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                                <tr>
                                    <th class="px-3 py-2 text-left">No</th>
                                    <th class="px-3 py-2 text-center">Tgl Jatuh Tempo</th>
                                    <th class="px-3 py-2 text-center">Tgl Bayar</th>
                                    <th class="px-3 py-2 text-center">Angsuran</th>
                                    <th class="px-3 py-2 text-right">Rencana Pokok</th>
                                    <th class="px-3 py-2 text-right">Realisasi Pokok</th>
                                    <th class="px-3 py-2 text-right">Selisih Pokok</th>
                                    <th class="px-3 py-2 text-right">Rencana Jasa</th>
                                    <th class="px-3 py-2 text-right">Realisasi Jasa</th>
                                    <th class="px-3 py-2 text-right">Selisih Jasa</th>
                                    <th class="px-3 py-2 text-center">Telat (hari)</th>
                                    <th class="px-3 py-2 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, idx) in loan.rows"
                                    :key="row.installment_number"
                                    class="border-t border-outline-variant/40"
                                >
                                    <td class="px-3 py-2">{{ idx + 1 }}</td>
                                    <td class="px-3 py-2 text-center">{{ row.due_date }}</td>
                                    <td class="px-3 py-2 text-center">
                                        {{ row.paid_at ? row.paid_at.substring(0, 10) : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">{{ row.installment_number }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.plan_principal) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.actual_principal) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums" :class="row.gap_principal > 0 ? 'text-error' : 'text-secondary'">
                                        {{ formatMoney(row.gap_principal) }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.plan_interest) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.actual_interest) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums" :class="row.gap_interest > 0 ? 'text-error' : 'text-secondary'">
                                        {{ formatMoney(row.gap_interest) }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span v-if="row.delay_days !== null" :class="row.delay_days > 0 ? 'text-error font-semibold' : 'text-secondary'">
                                            {{ row.delay_days }}
                                        </span>
                                        <span v-else class="text-on-surface-variant">—</span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                            :class="statusClass(row.realization_status)"
                                        >
                                            {{ row.realization_status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                    <td class="px-3 py-2" colspan="4">TOTAL {{ loan.loan_number }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(loan.totals.plan_principal) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(loan.totals.actual_principal) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-error">{{ formatMoney(loan.totals.gap_principal) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(loan.totals.plan_interest) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(loan.totals.actual_interest) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-error">{{ formatMoney(loan.totals.gap_interest) }}</td>
                                    <td class="px-3 py-2 text-center">{{ loan.totals.total_delay_days }}</td>
                                    <td class="px-3 py-2 text-center">{{ loan.totals.pct_realization }}%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </AppCard>
            </div>

            <AppCard v-if="loans.length > 0" class="overflow-hidden p-0">
                <div class="border-b border-outline-variant/40 p-4">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Rekap Seluruh Pinjaman ({{ totals.loan_count }} pinjaman)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-right">Rencana Pokok</th>
                                <th class="px-3 py-2 text-right">Realisasi Pokok</th>
                                <th class="px-3 py-2 text-right">Gap Pokok</th>
                                <th class="px-3 py-2 text-right">Rencana Jasa</th>
                                <th class="px-3 py-2 text-right">Realisasi Jasa</th>
                                <th class="px-3 py-2 text-right">Gap Jasa</th>
                                <th class="px-3 py-2 text-center">% Realisasi</th>
                                <th class="px-3 py-2 text-center">Telat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="font-semibold">
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.plan_principal) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.actual_principal) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-error">{{ formatMoney(totals.gap_principal) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.plan_interest) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.actual_interest) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-error">{{ formatMoney(totals.gap_interest) }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span :class="totals.pct_realization >= 90 ? 'text-secondary font-bold' : 'text-warning font-bold'">
                                        {{ totals.pct_realization }}%
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span v-if="totals.late_count > 0" class="text-error font-semibold">{{ totals.late_count }} ({{ totals.avg_delay_days }} hari)</span>
                                    <span v-else class="text-secondary">0</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
