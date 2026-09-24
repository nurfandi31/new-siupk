<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface WriteOffIndividuRow {
    no: number;
    loan_id: number;
    loan_row_id: number;
    loan_number: string;
    product_code: string;
    product_name: string;
    member_number: string;
    member_name: string;
    nik: string | null;
    village_name: string | null;
    group_code: string | null;
    group_name: string | null;
    principal_amount: number;
    sisa_pokok: number;
    ckpn: number;
    nilai_bersih: number;
    written_off_at: string | null;
    written_off_at_label: string;
    written_off_at_iso: string;
    reason: string;
    borrower_kind: 'Individu' | string;
}

interface WriteOffIndividuTotals {
    count: number;
    principal_total: number;
    sisa_pokok_total: number;
    ckpn_total: number;
    nilai_bersih_total: number;
}

const props = defineProps<{
    year: number;
    month: number;
    period_label: string;
    identity: { legal_name: string; short_name: string | null };
    rows: WriteOffIndividuRow[];
    totals: WriteOffIndividuTotals;
    filters: { year: number; month: number; product: string };
}>();

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));
const selectedProduct = ref(props.filters.product || 'all');

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

const productOptions = [
    { value: 'all', label: 'Semua Produk' },
];

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const formatMoney = (v: number | string | null | undefined): string =>
    money.format(Number(v || 0));

function apply(): void {
    router.get(
        '/lending/reports/write-offs-individu',
        {
            year: selectedYear.value,
            month: selectedMonth.value,
            product: selectedProduct.value,
        },
        { preserveState: true, replace: true },
    );
}

const baseParams = computed(() => ({
    year: selectedYear.value,
    month: selectedMonth.value,
    product: selectedProduct.value,
}));

const pdfUrl = computed(() => {
    const q = new URLSearchParams(
        Object.fromEntries(
            Object.entries(baseParams.value).map(([k, v]) => [k, String(v)]),
        ),
    );
    return `/lending/reports/write-offs-individu/pdf?${q.toString()}`;
});

const excelUrl = computed(() => {
    const q = new URLSearchParams(
        Object.fromEntries(
            Object.entries(baseParams.value).map(([k, v]) => [k, String(v)]),
        ),
    );
    return `/lending/reports/write-offs-individu/excel?${q.toString()}`;
});

function openDetail(rowId: number): void {
    router.visit(`/lending/loans/${rowId}`);
}
</script>

<template>
    <Head title="Pinjaman Dihapusbukukan — Individu" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Laporan Piutang</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Pinjaman Dihapusbukukan — Individu</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Daftar pinjaman perorangan (<span class="font-semibold text-primary">legacy_source = member_loan</span>)
                        yang berstatus <AppBadge tone="error">Dihapusbukukan</AppBadge>
                        per <span class="font-semibold text-primary">{{ period_label }}</span>
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
                    <a :href="excelUrl" class="inline-flex">
                        <AppButton variant="outline">
                            <span class="material-symbols-outlined mr-1.5 text-base">table_view</span>
                            Export Excel
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
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Produk</label>
                        <SmartSelect v-model="selectedProduct" :options="productOptions" @update:model-value="apply" hide-label />
                    </div>
                </div>
            </AppCard>

            <!-- KPI cards -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jumlah Pinjaman Hapus</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Pokok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.principal_total) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sisa Pokok Saat Hapus</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.sisa_pokok_total) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Cadangan CKPN</p>
                    <p class="mt-2 text-2xl font-bold text-error">{{ formatMoney(totals.ckpn_total) }}</p>
                </AppCard>
            </div>

            <!-- Table -->
            <AppCard class="overflow-x-auto p-0">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-surface-variant/40 text-on-surface font-semibold">
                        <tr class="border-b border-outline-variant/30">
                            <th class="p-3 text-center" style="width: 4%;">No</th>
                            <th class="p-3 text-left" style="width: 12%;">Nomor Pinjaman</th>
                            <th class="p-3 text-left" style="width: 16%;">Peminjam (NIK)</th>
                            <th class="p-3 text-left" style="width: 9%;">Desa</th>
                            <th class="p-3 text-left" style="width: 11%;">Kelompok</th>
                            <th class="p-3 text-right" style="width: 10%;">Pokok</th>
                            <th class="p-3 text-right" style="width: 10%;">Sisa Pokok</th>
                            <th class="p-3 text-right" style="width: 9%;">CKPN</th>
                            <th class="p-3 text-right" style="width: 9%;">Nilai Bersih</th>
                            <th class="p-3 text-center" style="width: 7%;">Tgl Hapus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <tr v-if="rows.length === 0">
                            <td colspan="10" class="px-3 py-12 text-center text-on-surface-variant">
                                Tidak ada pinjaman individu yang dihapusbukukan pada periode ini.
                            </td>
                        </tr>
                        <tr
                            v-for="row in rows"
                            :key="row.loan_row_id"
                            class="hover:bg-surface-variant/10"
                        >
                            <td class="p-3 text-center text-on-surface-variant">{{ row.no }}</td>
                            <td class="p-3 font-medium text-primary">
                                <button
                                    type="button"
                                    class="text-left underline-offset-2 hover:underline"
                                    @click="openDetail(row.loan_row_id)"
                                >
                                    {{ row.loan_number || '—' }}
                                </button>
                                <div class="text-[10px] uppercase tracking-wide text-on-surface-variant">#{{ row.loan_id }}</div>
                            </td>
                            <td class="p-3">
                                <div class="font-semibold">{{ row.member_name }}</div>
                                <div class="text-[10px] text-on-surface-variant">
                                    <span v-if="row.nik">{{ row.nik }}</span>
                                    <span v-else-if="row.member_number">{{ row.member_number }}</span>
                                </div>
                            </td>
                            <td class="p-3">{{ row.village_name || '—' }}</td>
                            <td class="p-3">
                                <div>{{ row.group_name || '—' }}</div>
                                <div v-if="row.group_code" class="text-[10px] uppercase tracking-wide text-on-surface-variant">{{ row.group_code }}</div>
                            </td>
                            <td class="p-3 text-right tabular-nums">{{ formatMoney(row.principal_amount) }}</td>
                            <td class="p-3 text-right tabular-nums">{{ formatMoney(row.sisa_pokok) }}</td>
                            <td class="p-3 text-right tabular-nums text-error font-semibold">{{ formatMoney(row.ckpn) }}</td>
                            <td class="p-3 text-right tabular-nums">{{ formatMoney(row.nilai_bersih) }}</td>
                            <td class="p-3 text-center">{{ row.written_off_at_label }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length > 0" class="bg-surface-variant/40 font-bold text-on-surface">
                        <tr class="border-t-2 border-outline">
                            <td colspan="5" class="p-3">TOTAL ({{ totals.count }} pinjaman)</td>
                            <td class="p-3 text-right tabular-nums">{{ formatMoney(totals.principal_total) }}</td>
                            <td class="p-3 text-right tabular-nums">{{ formatMoney(totals.sisa_pokok_total) }}</td>
                            <td class="p-3 text-right tabular-nums text-error">{{ formatMoney(totals.ckpn_total) }}</td>
                            <td class="p-3 text-right tabular-nums">{{ formatMoney(totals.nilai_bersih_total) }}</td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </AppCard>

            <AppCard v-if="rows.length === 0" class="p-8 text-center">
                <AppEmptyState
                    icon="description"
                    title="Belum ada pinjaman dihapusbukukan"
                    description="Tidak ada pinjaman individu dengan status hapus buku pada periode yang dipilih."
                />
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
