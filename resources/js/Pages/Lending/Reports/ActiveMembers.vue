<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface ActiveMemberRow {
    member_row_id: number;
    nik: string | null;
    member_number: string | null;
    member_name: string;
    village_name: string | null;
    group_name: string | null;
    group_code: string | null;
    status: string;
    loan_count: number;
    savings_total: number;
    outstanding_total: number;
    tunggakan_pokok: number;
    tunggakan_jasa: number;
}

interface ActiveMemberTotals {
    count: number;
    loan_total: number;
    outstanding_total: number;
    tunggakan_pokok_total: number;
    tunggakan_jasa_total: number;
}

const props = defineProps<{
    year: number;
    month: number;
    period_label: string;
    identity: { legal_name: string; short_name: string | null };
    rows: ActiveMemberRow[];
    totals: ActiveMemberTotals;
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
        '/lending/reports/members/active',
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
    return `/lending/reports/members/active/pdf?${q.toString()}`;
});

function statusTone(status: string): 'success' | 'warning' | 'error' | 'neutral' {
    if (status === 'active') return 'success';
    if (status === 'suspended') return 'warning';
    if (status === 'inactive' || status === 'closed') return 'error';
    return 'neutral';
}

function statusLabel(status: string): string {
    if (status === 'active') return 'Aktif';
    if (status === 'suspended') return 'Ditangguhkan';
    if (status === 'inactive') return 'Nonaktif';
    if (status === 'closed') return 'Tutup';
    return status;
}
</script>

<template>
    <Head title="Pemanfaat Aktif" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan Pinjaman</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Pemanfaat Aktif</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Daftar anggota yang memiliki pinjaman individu aktif per
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
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jumlah Pemanfaat</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pinjaman Aktif</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.loan_total }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Outstanding</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.outstanding_total) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Tunggakan Pokok</p>
                    <p class="mt-2 text-2xl font-bold text-error">{{ formatMoney(totals.tunggakan_pokok_total) }}</p>
                </AppCard>
            </div>

            <!-- Table -->
            <AppCard class="overflow-x-auto p-0">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-surface-variant/40 text-on-surface font-semibold">
                        <tr class="border-b border-outline-variant/30">
                            <th class="p-3 text-left">No</th>
                            <th class="p-3 text-left">NIK</th>
                            <th class="p-3 text-left">No. Anggota</th>
                            <th class="p-3 text-left">Nama Anggota</th>
                            <th class="p-3 text-left">Desa</th>
                            <th class="p-3 text-left">Kelompok</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3 text-center">Pinj. Aktif</th>
                            <th class="p-3 text-right">Simpanan</th>
                            <th class="p-3 text-right">Outstanding</th>
                            <th class="p-3 text-right">Tungg. Pokok</th>
                            <th class="p-3 text-right">Tungg. Jasa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <tr v-for="(row, idx) in rows" :key="row.member_row_id" class="hover:bg-surface-variant/10">
                            <td class="p-2.5 px-3 text-center">{{ idx + 1 }}</td>
                            <td class="p-2.5 text-on-surface-variant">{{ row.nik || '—' }}</td>
                            <td class="p-2.5 text-on-surface-variant">{{ row.member_number || '—' }}</td>
                            <td class="p-2.5 font-medium text-on-surface">{{ row.member_name }}</td>
                            <td class="p-2.5">{{ row.village_name || '—' }}</td>
                            <td class="p-2.5">
                                <div>{{ row.group_name || '—' }}</div>
                                <div v-if="row.group_code" class="text-[10px] text-on-surface-variant">{{ row.group_code }}</div>
                            </td>
                            <td class="p-2.5 text-center">
                                <AppBadge :tone="statusTone(row.status)">
                                    {{ statusLabel(row.status) }}
                                </AppBadge>
                            </td>
                            <td class="p-2.5 text-center">{{ row.loan_count }}</td>
                            <td class="p-2.5 text-right text-on-surface-variant">—</td>
                            <td class="p-2.5 text-right font-semibold text-primary">
                                {{ formatMoney(row.outstanding_total) }}
                            </td>
                            <td class="p-2.5 text-right font-semibold" :class="row.tunggakan_pokok > 0 ? 'text-error' : ''">
                                {{ formatMoney(row.tunggakan_pokok) }}
                            </td>
                            <td class="p-2.5 text-right font-semibold" :class="row.tunggakan_jasa > 0 ? 'text-error' : ''">
                                {{ formatMoney(row.tunggakan_jasa) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                        <tr class="border-t border-outline-variant/40">
                            <td colspan="7" class="p-3">TOTAL</td>
                            <td class="p-3 text-center">{{ totals.loan_total }}</td>
                            <td class="p-3 text-right">—</td>
                            <td class="p-3 text-right text-primary">{{ formatMoney(totals.outstanding_total) }}</td>
                            <td class="p-3 text-right" :class="totals.tunggakan_pokok_total > 0 ? 'text-error' : ''">
                                {{ formatMoney(totals.tunggakan_pokok_total) }}
                            </td>
                            <td class="p-3 text-right" :class="totals.tunggakan_jasa_total > 0 ? 'text-error' : ''">
                                {{ formatMoney(totals.tunggakan_jasa_total) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </AppCard>

            <AppCard v-if="rows.length === 0" class="p-8 text-center">
                <AppEmptyState
                    icon="person_off"
                    title="Tidak ada pemanfaat aktif"
                    description="Belum ada anggota dengan pinjaman individu aktif pada periode ini."
                />
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>