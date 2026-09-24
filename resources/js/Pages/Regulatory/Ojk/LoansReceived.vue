<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface LoanRow {
    row_id: number;
    id: number;
    creditor_name: string;
    creditor_type: string;
    creditor_type_label: string;
    contract_number: string;
    contract_date: string | null;
    principal_amount: number;
    principal_remaining: number;
    interest_rate: number;
    interest_type: string;
    tenor_months: number;
    tenor_remaining_months: number;
    start_date: string | null;
    due_date: string | null;
    purpose: string;
    status: string;
    status_label: string;
    status_tone: string;
    notes: string;
}

const props = defineProps<{
    year: number;
    month: number | null;
    period_label: string;
    as_of: string;
    identity: { legal_name: string; short_name: string | null };
    rows: LoanRow[];
    totals: {
        count: number;
        principal_total: number;
        principal_remaining_total: number;
        active_count: number;
        paid_count: number;
        written_off_count: number;
    };
    filters: {
        year: number;
        month: number | null;
        status: string;
        creditor_type: string;
    };
}>();

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(props.filters.month === null ? 'all' : String(props.filters.month));
const selectedStatus = ref(props.filters.status ?? 'all');
const selectedCreditorType = ref(props.filters.creditor_type ?? 'all');

const monthOptions = [
    { value: 'all', label: 'Semua bulan' },
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    const list: { value: string; label: string }[] = [];
    for (let y = current + 1; y >= current - 5; y -= 1) {
        list.push({ value: String(y), label: String(y) });
    }
    return list;
});

const statusOptions = [
    { value: 'all', label: 'Semua Status' },
    { value: 'active', label: 'Aktif' },
    { value: 'paid', label: 'Lunas' },
    { value: 'written_off', label: 'Dihapusbukukan' },
    { value: 'restructured', label: 'Restrukturisasi' },
];

const creditorTypeOptions = [
    { value: 'all', label: 'Semua Kreditur' },
    { value: 'bank', label: 'Bank Umum' },
    { value: 'lembaga_keuangan', label: 'Lembaga Keuangan' },
    { value: 'donor', label: 'Donor / Hibah' },
    { value: 'pemerintah', label: 'Pemerintah' },
    { value: 'lainnya', label: 'Lainnya' },
];

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const formatMoney = (v: number | string | null | undefined): string =>
    money.format(Number(v || 0));

function apply(): void {
    router.get(
        '/regulatory/ojk/loans-received',
        {
            year: selectedYear.value,
            month: selectedMonth.value,
            status: selectedStatus.value,
            creditor_type: selectedCreditorType.value,
        },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
        status: selectedStatus.value,
        creditor_type: selectedCreditorType.value,
    });
    return `/regulatory/ojk/loans-received/pdf?${q.toString()}`;
});

function formatDate(value: string | null): string {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    } catch {
        return value;
    }
}

function badgeTone(tone: string): string {
    return tone === 'error' ? 'error' : (tone === 'warning' ? 'warning' : 'success');
}
</script>

<template>
    <Head title="DRPY — Pinjaman Diterima (OJK)" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Laporan OJK · DRPY
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Rincian Pinjaman Diterima</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Daftar pinjaman dari pihak ketiga yang diterima pada periode
                        <span class="font-semibold text-primary">{{ period_label }}</span>
                        — {{ identity.legal_name }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="pdfUrl" target="_blank" class="inline-flex">
                        <AppButton variant="outline">
                            <span class="material-symbols-outlined mr-1.5 text-base">picture_as_pdf</span>
                            Cetak PDF
                        </AppButton>
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <AppCard class="p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Tahun</label>
                        <SmartSelect v-model="selectedYear" :options="yearOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Bulan</label>
                        <SmartSelect v-model="selectedMonth" :options="monthOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Status</label>
                        <SmartSelect v-model="selectedStatus" :options="statusOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Jenis Kreditur</label>
                        <SmartSelect v-model="selectedCreditorType" :options="creditorTypeOptions" @update:model-value="apply" hide-label />
                    </div>
                </div>
            </AppCard>

            <!-- Summary tiles -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jumlah Pinjaman</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Pokok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.principal_total) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sisa Pokok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.principal_remaining_total) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Status</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                        <AppBadge tone="success">{{ totals.active_count }} Aktif</AppBadge>
                        <AppBadge tone="neutral">{{ totals.paid_count }} Lunas</AppBadge>
                        <AppBadge tone="error">{{ totals.written_off_count }} Hapusbuku</AppBadge>
                    </div>
                </AppCard>
            </div>

            <!-- Table -->
            <AppCard v-if="rows.length > 0" class="overflow-x-auto p-0">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-surface-variant/40 text-on-surface font-semibold">
                        <tr class="border-b border-outline-variant/30">
                            <th class="p-3 text-center w-12">No</th>
                            <th class="p-3 text-left">Kreditur</th>
                            <th class="p-3 text-left">No. Kontrak</th>
                            <th class="p-3 text-center">Tgl Kontrak</th>
                            <th class="p-3 text-right">Pokok</th>
                            <th class="p-3 text-right">Sisa Pokok</th>
                            <th class="p-3 text-center">Bunga</th>
                            <th class="p-3 text-center">Tenor</th>
                            <th class="p-3 text-center">Jatuh Tempo</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3 text-left">Tujuan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <tr v-for="(row, idx) in rows" :key="row.row_id" class="hover:bg-surface-variant/10">
                            <td class="p-2.5 px-3 text-center">{{ idx + 1 }}</td>
                            <td class="p-2.5">
                                <div class="font-semibold text-on-surface">{{ row.creditor_name }}</div>
                                <div v-if="row.creditor_type_label" class="text-[10px] text-on-surface-variant">
                                    {{ row.creditor_type_label }}
                                </div>
                            </td>
                            <td class="p-2.5 font-mono text-xs">{{ row.contract_number }}</td>
                            <td class="p-2.5 text-center text-on-surface-variant">{{ formatDate(row.contract_date) }}</td>
                            <td class="p-2.5 text-right font-semibold">{{ formatMoney(row.principal_amount) }}</td>
                            <td class="p-2.5 text-right">{{ formatMoney(row.principal_remaining) }}</td>
                            <td class="p-2.5 text-center">{{ row.interest_rate.toFixed(2) }}% ({{ row.interest_type }})</td>
                            <td class="p-2.5 text-center">{{ row.tenor_remaining_months }}/{{ row.tenor_months }} bln</td>
                            <td class="p-2.5 text-center text-on-surface-variant">{{ formatDate(row.due_date) }}</td>
                            <td class="p-2.5 text-center">
                                <AppBadge :tone="badgeTone(row.status_tone)">{{ row.status_label }}</AppBadge>
                            </td>
                            <td class="p-2.5 text-on-surface-variant">{{ row.purpose || '—' }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                        <tr class="border-t border-outline-variant/40">
                            <td colspan="4" class="p-3">TOTAL</td>
                            <td class="p-3 text-right">{{ formatMoney(totals.principal_total) }}</td>
                            <td class="p-3 text-right">{{ formatMoney(totals.principal_remaining_total) }}</td>
                            <td colspan="5" class="p-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </AppCard>

            <AppCard v-else class="p-8 text-center">
                <AppEmptyState
                    icon="account_balance"
                    title="Belum ada pinjaman diterima"
                    description="Belum ada data pinjaman dari pihak ketiga pada periode ini."
                />
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>