<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface OverdueRow {
    loan_row_id: number;
    loan_id: number;
    loan_number: string;
    legacy_source: string;
    borrower_name: string;
    borrower_code: string;
    village_name: string;
    product_code: string;
    product_name: string;
    disbursed_at: string | null;
    principal_disbursed: number;
    principal_remaining: number;
    overdue_principal: number;
    overdue_interest: number;
    overdue_penalty: number;
    overdue_amount: number;
    overdue_installment_count: number;
    oldest_due_date: string;
    days_overdue: number;
    aging_bucket: string;
    collectibility: string;
}

interface OverdueTotals {
    loan_count: number;
    principal_disbursed: number;
    principal_remaining: number;
    overdue_principal: number;
    overdue_interest: number;
    overdue_penalty: number;
    overdue_amount: number;
    overdue_installment_count: number;
    max_days_overdue: number;
    avg_days_overdue: number;
}

interface AgingBucket {
    key: string;
    label: string;
    count: number;
    principal: number;
    overdue: number;
}

interface Identity {
    legal_name: string;
    short_name?: string | null;
}

interface Period {
    period_label: string;
    as_of: string;
}

interface Filters {
    as_of: string | null;
    scope: string | null;
}

const props = defineProps<{
    as_of: string;
    period: Period;
    identity: Identity;
    rows: OverdueRow[];
    totals: OverdueTotals;
    aging: AgingBucket[];
    filters: Filters;
}>();

const selectedDate = ref<string>(props.filters.as_of ?? props.as_of ?? new Date().toISOString().slice(0, 10));
const selectedScope = ref<string>(props.filters.scope ?? 'all');

const scopeOptions = [
    { value: 'all', label: 'Semua (Kelompok + Individu)' },
    { value: 'group', label: 'Pinjaman Kelompok' },
    { value: 'member', label: 'Pinjaman Individu' },
];

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v: number | string | null | undefined): string {
    return money.format(Number(v ?? 0));
}

function apply(): void {
    const params = new URLSearchParams();
    if (selectedDate.value) params.set('as_of', selectedDate.value);
    if (selectedScope.value && selectedScope.value !== 'all') params.set('scope', selectedScope.value);
    router.get(`/lending/reports/overdue?${params.toString()}`, {}, { preserveScroll: true, replace: true });
}

const pdfHref = computed<string>(() => {
    const params = new URLSearchParams();
    if (selectedDate.value) params.set('as_of', selectedDate.value);
    if (selectedScope.value && selectedScope.value !== 'all') params.set('scope', selectedScope.value);
    return `/lending/reports/overdue/pdf?${params.toString()}`;
});

const excelHref = computed<string>(() => {
    const params = new URLSearchParams();
    if (selectedDate.value) params.set('as_of', selectedDate.value);
    if (selectedScope.value && selectedScope.value !== 'all') params.set('scope', selectedScope.value);
    return `/lending/reports/overdue/excel?${params.toString()}`;
});

function kolekClass(k: string): string {
    if (k === 'Lancar') return 'bg-secondary/10 text-secondary';
    if (k === 'Kurang Lancar') return 'bg-tertiary/10 text-tertiary';
    if (k === 'Diragukan') return 'bg-warning/10 text-warning';
    if (k === 'Macet') return 'bg-error/10 text-error';
    return 'bg-surface-container text-on-surface-variant';
}
</script>

<template>
    <Head title="Daftar Tunggakan" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan Piutang</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Daftar Tunggakan</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Pinjaman aktif dengan angsuran lewat jatuh tempo — {{ period.period_label }} — {{ identity.legal_name }}
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
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <AppDatePicker v-model="selectedDate" label="Posisi Tanggal" mode="date" />
                    <SmartSelect v-model="selectedScope" :options="scopeOptions" label="Scope Pinjaman" />
                    <div class="flex items-end">
                        <AppButton type="button" class="!min-h-11 h-11 px-5" @click="apply">Tampilkan</AppButton>
                    </div>
                </div>
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pinjaman Menunggak</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ totals.loan_count }}</p>
                    <p class="text-xs text-on-surface-variant">{{ totals.overdue_installment_count }} angsuran lewat tempo</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Tunggakan</p>
                    <p class="mt-2 text-xl font-bold text-error">{{ formatMoney(totals.overdue_amount) }}</p>
                    <p class="text-xs text-on-surface-variant">Pokok {{ formatMoney(totals.overdue_principal) }} · Bunga {{ formatMoney(totals.overdue_interest) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Rata-rata Keterlambatan</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ totals.avg_days_overdue }} hari</p>
                    <p class="text-xs text-on-surface-variant">Maks: {{ totals.max_days_overdue }} hari</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sisa Pokok</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ formatMoney(totals.principal_remaining) }}</p>
                    <p class="text-xs text-on-surface-variant">dari alokasi {{ formatMoney(totals.principal_disbursed) }}</p>
                </AppCard>
            </div>

            <AppCard class="overflow-hidden p-0">
                <div class="border-b border-outline-variant/40 p-4">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Distribusi Kolektibilitas (Aging)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">Bucket</th>
                                <th class="px-3 py-2 text-center">Jumlah Pinjaman</th>
                                <th class="px-3 py-2 text-right">Sisa Pokok</th>
                                <th class="px-3 py-2 text-right">Total Tunggakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="bucket in aging"
                                :key="bucket.key"
                                class="border-t border-outline-variant/40"
                            >
                                <td class="px-3 py-2 font-medium">{{ bucket.label }}</td>
                                <td class="px-3 py-2 text-center tabular-nums">{{ bucket.count }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(bucket.principal) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-error">{{ formatMoney(bucket.overdue) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <AppCard class="overflow-hidden p-0">
                <div class="border-b border-outline-variant/40 p-4">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Rincian Tunggakan per Pinjaman</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">No</th>
                                <th class="px-3 py-2 text-left">Pinjaman</th>
                                <th class="px-3 py-2 text-left">Peminjam / Kelompok</th>
                                <th class="px-3 py-2 text-left">Desa</th>
                                <th class="px-3 py-2 text-right">Pokok Pinjaman</th>
                                <th class="px-3 py-2 text-right">Sisa Pokok</th>
                                <th class="px-3 py-2 text-right">Tunggakan Pokok</th>
                                <th class="px-3 py-2 text-right">Tunggakan Bunga</th>
                                <th class="px-3 py-2 text-right">Total Tunggakan</th>
                                <th class="px-3 py-2 text-center"># Angsuran</th>
                                <th class="px-3 py-2 text-center">Hari Telat</th>
                                <th class="px-3 py-2 text-center">Kolektibilitas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="12" class="px-3 py-8 text-center text-on-surface-variant">
                                    Tidak ada pinjaman menunggak pada posisi tanggal ini.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.loan_row_id"
                                class="border-t border-outline-variant/40"
                            >
                                <td class="px-3 py-2">{{ rows.indexOf(row) + 1 }}</td>
                                <td class="px-3 py-2">
                                    <a
                                        :href="`/lending/loans/${row.loan_row_id}`"
                                        class="font-semibold text-primary hover:underline"
                                    >#{{ row.loan_id }}</a>
                                    <div class="text-[10px] uppercase text-on-surface-variant">{{ row.loan_number }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ row.borrower_name }}</div>
                                    <div v-if="row.borrower_code" class="text-[10px] text-on-surface-variant">({{ row.borrower_code }})</div>
                                </td>
                                <td class="px-3 py-2">{{ row.village_name }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.principal_disbursed) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.principal_remaining) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.overdue_principal) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.overdue_interest) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold text-error">{{ formatMoney(row.overdue_amount) }}</td>
                                <td class="px-3 py-2 text-center">{{ row.overdue_installment_count }}</td>
                                <td class="px-3 py-2 text-center font-semibold">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs"
                                        :class="row.days_overdue > 90 ? 'bg-error/10 text-error' : (row.days_overdue > 30 ? 'bg-warning/10 text-warning' : 'bg-secondary/10 text-secondary')"
                                    >
                                        {{ row.days_overdue }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="kolekClass(row.collectibility)"
                                    >
                                        {{ row.collectibility }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="rows.length > 0">
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2" colspan="4">TOTAL ({{ totals.loan_count }} pinjaman)</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.principal_disbursed) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.principal_remaining) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.overdue_principal) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.overdue_interest) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-error">{{ formatMoney(totals.overdue_amount) }}</td>
                                <td class="px-3 py-2 text-center">{{ totals.overdue_installment_count }}</td>
                                <td class="px-3 py-2 text-center">maks {{ totals.max_days_overdue }}</td>
                                <td class="px-3 py-2 text-center">rerata {{ totals.avg_days_overdue }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
