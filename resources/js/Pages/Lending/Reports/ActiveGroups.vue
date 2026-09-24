<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface ActiveGroupRow {
    group_row_id: number;
    group_code: string;
    group_name: string;
    village_name: string | null;
    ketua_name: string | null;
    member_count: number;
    active_loan_count: number;
    savings_total: number;
    outstanding_total: number;
    has_period_activity: boolean;
}

interface ActiveGroupTotals {
    count: number;
    member_total: number;
    active_loan_total: number;
    outstanding_total: number;
}

const props = defineProps<{
    year: number;
    month: number;
    period_label: string;
    identity: { legal_name: string; short_name: string | null };
    rows: ActiveGroupRow[];
    totals: ActiveGroupTotals;
    filters: { year: number; month: number };
}>();

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));

const monthOptions = [
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

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const formatMoney = (v: number | string | null | undefined): string =>
    money.format(Number(v || 0));

function apply(): void {
    router.get(
        '/lending/reports/groups/active',
        {
            year: selectedYear.value,
            month: selectedMonth.value,
        },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
    });
    return `/lending/reports/groups/active/pdf?${q.toString()}`;
});
</script>

<template>
    <Head title="Kelompok Aktif" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan Pinjaman</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Kelompok Aktif</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Daftar kelompok yang memiliki pinjaman aktif per
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
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Tahun</label>
                        <SmartSelect v-model="selectedYear" :options="yearOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Bulan</label>
                        <SmartSelect v-model="selectedMonth" :options="monthOptions" @update:model-value="apply" hide-label />
                    </div>
                </div>
            </AppCard>

            <!-- Summary tiles -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jumlah Kelompok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Anggota Aktif</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.member_total }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pinjaman Aktif</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.active_loan_total }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Outstanding</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.outstanding_total) }}</p>
                </AppCard>
            </div>

            <!-- Table -->
            <AppCard class="overflow-x-auto p-0">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-surface-variant/40 text-on-surface font-semibold">
                        <tr class="border-b border-outline-variant/30">
                            <th class="p-3 text-left">No</th>
                            <th class="p-3 text-left">Kode Kelompok</th>
                            <th class="p-3 text-left">Nama Kelompok</th>
                            <th class="p-3 text-left">Desa</th>
                            <th class="p-3 text-left">Ketua</th>
                            <th class="p-3 text-center">Aktivitas</th>
                            <th class="p-3 text-center">Anggota</th>
                            <th class="p-3 text-center">Pinjaman Aktif</th>
                            <th class="p-3 text-right">Total Simpanan</th>
                            <th class="p-3 text-right">Outstanding</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <tr v-for="(row, idx) in rows" :key="row.group_row_id" class="hover:bg-surface-variant/10">
                            <td class="p-2.5 px-3 text-center">{{ idx + 1 }}</td>
                            <td class="p-2.5 font-medium text-on-surface">{{ row.group_code }}</td>
                            <td class="p-2.5 font-medium text-on-surface">{{ row.group_name }}</td>
                            <td class="p-2.5">{{ row.village_name || '—' }}</td>
                            <td class="p-2.5">{{ row.ketua_name || '—' }}</td>
                            <td class="p-2.5 text-center">
                                <AppBadge v-if="row.has_period_activity" tone="success-soft">Aktif</AppBadge>
                                <AppBadge v-else tone="neutral">Nonaktif</AppBadge>
                            </td>
                            <td class="p-2.5 text-center">{{ row.member_count }}</td>
                            <td class="p-2.5 text-center">{{ row.active_loan_count }}</td>
                            <td class="p-2.5 text-right text-on-surface-variant">—</td>
                            <td class="p-2.5 text-right font-semibold text-primary">
                                {{ formatMoney(row.outstanding_total) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                        <tr class="border-t border-outline-variant/40">
                            <td colspan="6" class="p-3">TOTAL</td>
                            <td class="p-3 text-center">{{ totals.member_total }}</td>
                            <td class="p-3 text-center">{{ totals.active_loan_total }}</td>
                            <td class="p-3 text-right">—</td>
                            <td class="p-3 text-right text-primary">{{ formatMoney(totals.outstanding_total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </AppCard>

            <AppCard v-if="rows.length === 0" class="p-8 text-center">
                <AppEmptyState
                    icon="groups"
                    title="Tidak ada kelompok aktif"
                    description="Belum ada kelompok dengan pinjaman aktif pada periode ini."
                />
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>