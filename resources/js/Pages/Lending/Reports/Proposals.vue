<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface StageRow {
    no: number;
    loan_id: number;
    row_id: number;
    loan_number: string;
    borrower_name: string;
    borrower_code: string | null;
    borrower_kind: string;
    village_name: string;
    product_code: string;
    product_name: string;
    principal_amount: number;
    principal_label: string;
    stage_date: string;
    stage_date_label: string;
    stage_date_iso: string;
    status: string;
    status_label: string;
    collector: string;
}

interface StageTotals {
    count: number;
    principal_amount: number;
    beneficiary_count: number;
    group_count: number;
    member_count: number;
}

const props = defineProps<{
    stage: string;
    stage_title: string;
    stage_date_label: string;
    status: string;
    year: number;
    month: number;
    period_label: string;
    identity: { legal_name: string; short_name: string | null };
    rows: StageRow[];
    totals: StageTotals;
    filters: { year: number; month: number };
}>();

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const formatMoney = (v: number | string | null | undefined): string => money.format(Number(v || 0));

const baseUrl = '/lending/reports/proposals';
const pdfUrl = '/lending/reports/proposals/pdf';
const excelUrl = '/lending/reports/proposals/excel';

function openDetail(rowId: number): void {
    router.visit(`/lending/loans/${rowId}`);
}

const formattedPeriod = computed(() => props.period_label);
</script>

<template>
    <Head title="Daftar Proposal" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan Pinjaman</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Daftar Proposal Pinjaman</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Pinjaman yang baru di-submit, menunggu verifikasi awal.
                        Periode: <span class="font-semibold text-primary">{{ formattedPeriod }}</span>
                    </p>
                </div>
            </header>

            <AppCard class="p-4">
                <ReportPeriodFilter
                    :year="filters.year"
                    :month="filters.month"
                    :base-url="baseUrl"
                    :pdf-url="pdfUrl"
                    :excel-url="excelUrl"
                />
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jumlah Proposal</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Pokok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.principal_amount) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Kelompok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.group_count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Individu</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.member_count }}</p>
                </AppCard>
            </div>

            <AppCard class="overflow-x-auto p-0">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                        <tr>
                            <th class="px-3 py-2 text-left">No</th>
                            <th class="px-3 py-2 text-left">Nomor Pinjaman</th>
                            <th class="px-3 py-2 text-left">Peminjam / Kelompok</th>
                            <th class="px-3 py-2 text-left">Desa</th>
                            <th class="px-3 py-2 text-left">Produk</th>
                            <th class="px-3 py-2 text-center">Jenis</th>
                            <th class="px-3 py-2 text-right">Pokok Pinjaman</th>
                            <th class="px-3 py-2 text-center">{{ stage_date_label }}</th>
                            <th class="px-3 py-2 text-center">Status</th>
                            <th class="px-3 py-2 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="10" class="px-3 py-12 text-center text-on-surface-variant">
                                Tidak ada proposal pinjaman untuk periode ini.
                            </td>
                        </tr>
                        <tr
                            v-for="row in rows"
                            :key="row.row_id"
                            class="border-t border-outline-variant/40 hover:bg-surface-container-low/50"
                        >
                            <td class="px-3 py-2 text-on-surface-variant">{{ row.no }}</td>
                            <td class="px-3 py-2 font-medium text-primary">
                                <div>{{ row.loan_number || '—' }}</div>
                                <div class="text-[10px] uppercase tracking-wide text-on-surface-variant">#{{ row.loan_id }}</div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-semibold">{{ row.borrower_name }}</div>
                                <div v-if="row.borrower_code" class="text-[10px] uppercase tracking-wide text-on-surface-variant">{{ row.borrower_code }}</div>
                            </td>
                            <td class="px-3 py-2">{{ row.village_name }}</td>
                            <td class="px-3 py-2">
                                <AppBadge tone="neutral">{{ row.product_code }}</AppBadge>
                                <span class="ml-1 text-xs text-on-surface-variant">{{ row.product_name }}</span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <AppBadge :tone="row.borrower_kind === 'Kelompok' ? 'primary' : 'secondary'">{{ row.borrower_kind }}</AppBadge>
                            </td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ formatMoney(row.principal_amount) }}</td>
                            <td class="px-3 py-2 text-center">{{ row.stage_date_label }}</td>
                            <td class="px-3 py-2 text-center">
                                <AppBadge tone="warning">{{ row.status_label }}</AppBadge>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <AppButton variant="outline" size="compact" @click="openDetail(row.row_id)">Buka</AppButton>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length > 0" class="bg-surface-container-low">
                        <tr class="border-t-2 border-outline font-semibold">
                            <td colspan="6" class="px-3 py-2.5">TOTAL PROPOSAL ({{ totals.count }})</td>
                            <td class="px-3 py-2.5 text-right text-primary tabular-nums">{{ formatMoney(totals.principal_amount) }}</td>
                            <td colspan="3" />
                        </tr>
                    </tfoot>
                </table>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
