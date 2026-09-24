<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface DueTodayRow {
    installment_row_id: number;
    installment_number: number;
    loan_row_id: number;
    loan_id: number;
    loan_number: string;
    legacy_source: string;
    borrower_name: string;
    borrower_code: string;
    village_name: string;
    product_code: string;
    product_name: string;
    due_date: string;
    days_overdue: number;
    installment_status: string;
    principal_due: number;
    interest_due: number;
    penalty_due: number;
    total_due: number;
    principal_paid: number;
    interest_paid: number;
    remaining_principal: number;
    remaining_interest: number;
}

interface DueTodayTotals {
    count: number;
    loan_count: number;
    principal_due: number;
    interest_due: number;
    penalty_due: number;
    total_due: number;
    principal_paid: number;
    interest_paid: number;
    remaining_principal: number;
    remaining_interest: number;
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
    rows: DueTodayRow[];
    totals: DueTodayTotals;
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
    router.get(`/lending/reports/due-today?${params.toString()}`, {}, { preserveScroll: true, replace: true });
}

const pdfHref = computed<string>(() => {
    const params = new URLSearchParams();
    if (selectedDate.value) params.set('as_of', selectedDate.value);
    if (selectedScope.value && selectedScope.value !== 'all') params.set('scope', selectedScope.value);
    return `/lending/reports/due-today/pdf?${params.toString()}`;
});

const excelHref = computed<string>(() => {
    const params = new URLSearchParams();
    if (selectedDate.value) params.set('as_of', selectedDate.value);
    if (selectedScope.value && selectedScope.value !== 'all') params.set('scope', selectedScope.value);
    return `/lending/reports/due-today/excel?${params.toString()}`;
});

function statusLabel(s: string): string {
    if (s === 'pending') return 'Belum Bayar';
    if (s === 'partial') return 'Sebagian';
    return s;
}
</script>

<template>
    <Head title="Tagihan Jatuh Tempo Hari Ini" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan Piutang</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Tagihan Jatuh Tempo</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Daftar angsuran yang due_date = {{ period.period_label }} — {{ identity.legal_name }}
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
                    <AppDatePicker v-model="selectedDate" label="Tanggal Jatuh Tempo" mode="date" />
                    <SmartSelect v-model="selectedScope" :options="scopeOptions" label="Scope Pinjaman" />
                    <div class="flex items-end">
                        <AppButton type="button" class="!min-h-11 h-11 px-5" @click="apply">Tampilkan</AppButton>
                    </div>
                </div>
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Angsuran</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ formatMoney(totals.total_due) }}</p>
                    <p class="text-xs text-on-surface-variant">{{ totals.count }} angsuran · {{ totals.loan_count }} pinjaman</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pokok Jatuh Tempo</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ formatMoney(totals.principal_due) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Bunga Jatuh Tempo</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ formatMoney(totals.interest_due) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sisa Pokok Belum Bayar</p>
                    <p class="mt-2 text-xl font-bold text-error">{{ formatMoney(totals.remaining_principal) }}</p>
                </AppCard>
            </div>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">No</th>
                                <th class="px-3 py-2 text-left">Pinjaman</th>
                                <th class="px-3 py-2 text-left">Peminjam / Kelompok</th>
                                <th class="px-3 py-2 text-left">Desa</th>
                                <th class="px-3 py-2 text-left">Produk</th>
                                <th class="px-3 py-2 text-center">Angsuran</th>
                                <th class="px-3 py-2 text-center">Jatuh Tempo</th>
                                <th class="px-3 py-2 text-center">Hari Telat</th>
                                <th class="px-3 py-2 text-right">Pokok</th>
                                <th class="px-3 py-2 text-right">Bunga</th>
                                <th class="px-3 py-2 text-right">Denda</th>
                                <th class="px-3 py-2 text-right">Total</th>
                                <th class="px-3 py-2 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="13" class="px-3 py-8 text-center text-on-surface-variant">
                                    Tidak ada tagihan jatuh tempo pada tanggal ini.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.installment_row_id"
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
                                <td class="px-3 py-2">
                                    <div>{{ row.product_code }}</div>
                                    <div class="text-[10px] text-on-surface-variant">{{ row.product_name }}</div>
                                </td>
                                <td class="px-3 py-2 text-center">{{ row.installment_number }}</td>
                                <td class="px-3 py-2 text-center">{{ row.due_date }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="row.days_overdue > 0 ? 'bg-error/10 text-error' : 'bg-secondary/10 text-secondary'"
                                    >
                                        {{ row.days_overdue }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.principal_due) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.interest_due) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.penalty_due) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ formatMoney(row.total_due) }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex rounded-full bg-surface-container px-2 py-0.5 text-xs">
                                        {{ statusLabel(row.installment_status) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="rows.length > 0">
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2" colspan="8">TOTAL ({{ totals.count }} angsuran · {{ totals.loan_count }} pinjaman)</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.principal_due) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.interest_due) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.penalty_due) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.total_due) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
