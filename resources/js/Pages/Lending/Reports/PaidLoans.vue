<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface PaidRow {
    loan_id: number;
    loan_row_id: number;
    loan_number: string;
    borrower_kind: 'Individu' | 'Kelompok' | string;
    borrower_name: string;
    borrower_code: string | null;
    nik: string | null;
    group_name: string | null;
    village_name: string | null;
    product_code: string;
    product_name: string;
    principal_amount: number;
    disbursed_at: string | null;
    completed_at: string | null;
    loan_days: number | null;
    installment_count: number;
    total_paid: number;
}

interface PaidTotals {
    count: number;
    principal_total: number;
    group_count: number;
    member_count: number;
    installments_total: number;
    paid_amount: number;
}

const props = defineProps<{
    year: number;
    month: number;
    period_label: string;
    identity: { legal_name: string; short_name: string | null };
    borrower_scope: string | null;
    rows: PaidRow[];
    totals: PaidTotals;
    filters: { year: number; month: number; scope: string | null };
}>();

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));
const selectedScope = ref(props.filters.scope ?? 'all');

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

const scopeOptions = [
    { value: 'all', label: 'Semua' },
    { value: 'group', label: 'Kelompok' },
    { value: 'member', label: 'Individu' },
];

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const formatMoney = (v: number | string | null | undefined): string =>
    money.format(Number(v || 0));

function apply(): void {
    router.get(
        '/lending/reports/paid',
        {
            year: selectedYear.value,
            month: selectedMonth.value,
            scope: selectedScope.value,
        },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
        scope: selectedScope.value,
    });
    return `/lending/reports/paid/pdf?${q.toString()}`;
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
</script>

<template>
    <Head title="Daftar Pinjaman Lunas" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan Pinjaman</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Daftar Pinjaman Lunas</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Pinjaman yang telah divalidasi pelunasan (status completed) dalam periode
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
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Tahun</label>
                        <SmartSelect v-model="selectedYear" :options="yearOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Bulan</label>
                        <SmartSelect v-model="selectedMonth" :options="monthOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Jenis</label>
                        <SmartSelect v-model="selectedScope" :options="scopeOptions" @update:model-value="apply" hide-label />
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
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Kelompok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.group_count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Individu</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.member_count }}</p>
                </AppCard>
            </div>

            <!-- Table -->
            <AppCard class="overflow-x-auto p-0">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-surface-variant/40 text-on-surface font-semibold">
                        <tr class="border-b border-outline-variant/30">
                            <th class="p-3 text-left">No</th>
                            <th class="p-3 text-left">Nomor Pinjaman</th>
                            <th class="p-3 text-center">Jenis</th>
                            <th class="p-3 text-left">Peminjam / Kelompok</th>
                            <th class="p-3 text-center">NIK</th>
                            <th class="p-3 text-left">Desa</th>
                            <th class="p-3 text-center">Produk</th>
                            <th class="p-3 text-center">Tgl Cair</th>
                            <th class="p-3 text-center">Tgl Lunas</th>
                            <th class="p-3 text-center">Lama (hari)</th>
                            <th class="p-3 text-center">Angsur</th>
                            <th class="p-3 text-right">Pokok</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <tr v-for="(row, idx) in rows" :key="row.loan_row_id" class="hover:bg-surface-variant/10">
                            <td class="p-2.5 px-3 text-center">{{ idx + 1 }}</td>
                            <td class="p-2.5">
                                #{{ row.loan_id }}
                                <div v-if="row.loan_number" class="text-[10px] text-on-surface-variant">{{ row.loan_number }}</div>
                            </td>
                            <td class="p-2.5 text-center">
                                <span :class="row.borrower_kind === 'Individu' ? 'text-tertiary' : 'text-primary'" class="font-semibold">
                                    {{ row.borrower_kind }}
                                </span>
                            </td>
                            <td class="p-2.5 font-medium text-on-surface">
                                {{ row.borrower_name }}
                                <span v-if="row.borrower_code" class="text-[11px] text-on-surface-variant">({{ row.borrower_code }})</span>
                                <div v-if="row.borrower_kind === 'Individu' && row.group_name" class="text-[10px] text-on-surface-variant">
                                    Kel: {{ row.group_name }}
                                </div>
                            </td>
                            <td class="p-2.5 text-center text-on-surface-variant">{{ row.nik || '—' }}</td>
                            <td class="p-2.5">{{ row.village_name || '—' }}</td>
                            <td class="p-2.5 text-center">
                                <span class="text-[11px] font-semibold text-primary">{{ row.product_code }}</span>
                                <div class="text-[10px] text-on-surface-variant">{{ row.product_name }}</div>
                            </td>
                            <td class="p-2.5 text-center text-on-surface-variant">{{ formatDate(row.disbursed_at) }}</td>
                            <td class="p-2.5 text-center">{{ formatDate(row.completed_at) }}</td>
                            <td class="p-2.5 text-center">{{ row.loan_days !== null ? row.loan_days.toLocaleString('id-ID') : '—' }}</td>
                            <td class="p-2.5 text-center">{{ row.installment_count }}</td>
                            <td class="p-2.5 text-right font-semibold">{{ formatMoney(row.principal_amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                        <tr class="border-t border-outline-variant/40">
                            <td colspan="10" class="p-3">TOTAL</td>
                            <td class="p-3 text-center">{{ totals.installments_total }}</td>
                            <td class="p-3 text-right">{{ formatMoney(totals.principal_total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </AppCard>

            <AppCard v-if="rows.length === 0" class="p-8 text-center">
                <AppEmptyState
                    icon="inbox"
                    title="Tidak ada pinjaman lunas"
                    description="Belum ada pinjaman yang divalidasi pelunasan pada periode ini."
                />
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>