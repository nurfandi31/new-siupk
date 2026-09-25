<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface BeneficiaryMember {
    member_row_id: number;
    member_number: string | null;
    member_name: string;
    nik: string | null;
    allocated_amount: number;
    tunggakan_pokok: number;
    tunggakan_jasa: number;
    loan_number: string | null;
    disbursed_at: string | null;
}

interface BeneficiaryGroup {
    group_row_id: number;
    group_code: string | null;
    group_name: string | null;
    village_name: string | null;
    village_row_id: number | null;
    members: BeneficiaryMember[];
    subtotal_alokasi: number;
    subtotal_tunggakan_pokok: number;
    subtotal_tunggakan_jasa: number;
}

interface BeneficiaryTotals {
    group_count: number;
    member_count: number;
    alokasi_total: number;
    tunggakan_pokok_total: number;
    tunggakan_jasa_total: number;
}

const props = defineProps<{
    year: number;
    month: number;
    period_label: string;
    identity: { legal_name: string; short_name: string | null };
    groups: BeneficiaryGroup[];
    totals: BeneficiaryTotals;
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
        '/lending/reports/beneficiaries/active',
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
    return `/lending/reports/beneficiaries/active/pdf?${q.toString()}`;
});

const formatDate = (v: string | null): string => {
    if (!v) return '—';
    try {
        return new Date(v).toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch {
        return v;
    }
};
</script>

<template>
    <Head title="Pemanfaat Aktif (Kelompok)" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Laporan Piutang</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Pemanfaat Aktif (Kelompok)</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Daftar anggota (pemanfaat) yang sedang terdaftar pada pinjaman kelompok aktif per periode.
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
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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

            <!-- Summary cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <AppCard class="p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jumlah Kelompok</p>
                    <p class="mt-1 text-2xl font-bold text-primary">{{ totals.group_count }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jumlah Pemanfaat</p>
                    <p class="mt-1 text-2xl font-bold text-primary">{{ totals.member_count }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Alokasi</p>
                    <p class="mt-1 text-2xl font-bold text-primary">{{ formatMoney(totals.alokasi_total) }}</p>
                </AppCard>
            </div>

            <!-- Per kelompok -->
            <div v-if="groups.length === 0">
                <AppEmptyState
                    title="Belum ada data"
                    description="Tidak ada pinjaman kelompok aktif pada periode ini."
                />
            </div>
            <div v-else class="space-y-4">
                <AppCard v-for="group in groups" :key="group.group_row_id" class="overflow-x-auto p-0">
                    <div class="flex items-center justify-between border-b border-outline-variant/60 bg-surface-variant/20 px-5 py-3">
                        <div>
                            <h3 class="text-base font-bold text-on-surface">
                                {{ group.group_name || '—' }}
                                <span v-if="group.group_code" class="text-xs font-normal text-on-surface-variant">({{ group.group_code }})</span>
                            </h3>
                            <p class="text-xs text-on-surface-variant">{{ group.village_name || '—' }}</p>
                        </div>
                        <div class="text-right text-xs">
                            <p><strong>{{ group.members.length }}</strong> pemanfaat</p>
                            <p>Alokasi: <strong>{{ formatMoney(group.subtotal_alokasi) }}</strong></p>
                        </div>
                    </div>
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-surface-variant/30 text-on-surface font-semibold">
                            <tr class="border-b border-outline-variant/30">
                                <th class="p-2.5">NIK</th>
                                <th class="p-2.5">No. Anggota</th>
                                <th class="p-2.5">Nama</th>
                                <th class="p-2.5">Tgl Cair</th>
                                <th class="p-2.5">No. Pinjaman</th>
                                <th class="p-2.5 text-right">Alokasi</th>
                                <th class="p-2.5 text-right">Tunggakan Pokok</th>
                                <th class="p-2.5 text-right">Tunggakan Jasa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20">
                            <tr v-for="m in group.members" :key="m.member_row_id" class="hover:bg-surface-variant/10">
                                <td class="p-2.5 text-on-surface-variant">{{ m.nik || '—' }}</td>
                                <td class="p-2.5 text-on-surface-variant">{{ m.member_number || '—' }}</td>
                                <td class="p-2.5 font-medium text-on-surface">{{ m.member_name }}</td>
                                <td class="p-2.5">{{ formatDate(m.disbursed_at) }}</td>
                                <td class="p-2.5">{{ m.loan_number || '—' }}</td>
                                <td class="p-2.5 text-right">{{ formatMoney(m.allocated_amount) }}</td>
                                <td class="p-2.5 text-right font-semibold" :class="m.tunggakan_pokok > 0 ? 'text-error' : ''">{{ formatMoney(m.tunggakan_pokok) }}</td>
                                <td class="p-2.5 text-right font-semibold" :class="m.tunggakan_jasa > 0 ? 'text-error' : ''">{{ formatMoney(m.tunggakan_jasa) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                            <tr class="border-t border-outline-variant/40">
                                <td class="p-2.5" colspan="5">Subtotal {{ group.group_name }}</td>
                                <td class="p-2.5 text-right">{{ formatMoney(group.subtotal_alokasi) }}</td>
                                <td class="p-2.5 text-right" :class="group.subtotal_tunggakan_pokok > 0 ? 'text-error' : ''">{{ formatMoney(group.subtotal_tunggakan_pokok) }}</td>
                                <td class="p-2.5 text-right" :class="group.subtotal_tunggakan_jasa > 0 ? 'text-error' : ''">{{ formatMoney(group.subtotal_tunggakan_jasa) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </AppCard>
            </div>

            <!-- Grand total -->
            <AppCard v-if="groups.length > 0" class="bg-primary-container/10 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="font-bold uppercase tracking-wider text-primary">Grand Total</span>
                    <div class="flex flex-wrap items-center gap-4 text-xs">
                        <span>Kelompok: <strong>{{ totals.group_count }}</strong></span>
                        <span>Pemanfaat: <strong>{{ totals.member_count }}</strong></span>
                        <span>Alokasi: <strong>{{ formatMoney(totals.alokasi_total) }}</strong></span>
                        <span>Tunggakan Pokok: <strong class="text-error">{{ formatMoney(totals.tunggakan_pokok_total) }}</strong></span>
                        <span>Tunggakan Jasa: <strong class="text-error">{{ formatMoney(totals.tunggakan_jasa_total) }}</strong></span>
                    </div>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
