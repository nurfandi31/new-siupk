<script setup lang="ts">
import { useI18n } from '../../../i18n';
const { t } = useI18n();
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface WriteOffRow {
    no: number;
    loan_id: number;
    loan_row_id: number;
    loan_number: string;
    product_code: string;
    product_name: string;
    group_code: string;
    group_name: string;
    village_name: string | null;
    principal_amount: number;
    sisa_pokok: number;
    ckpn: number;
    nilai_bersih: number;
    written_off_at: string | null;
    written_off_at_label: string;
    written_off_at_iso: string;
    reason: string;
    borrower_kind: 'Kelompok' | string;
}

interface WriteOffTotals {
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
    rows: WriteOffRow[];
    totals: WriteOffTotals;
    filters: { year: number; month: number; product: string };
}>();

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));
const selectedProduct = ref(props.filters.product || 'all');

const monthOptions = [
    { value: '1', label: t('Januari') },
    { value: '2', label: t('Februari') },
    { value: '3', label: t('Maret') },
    { value: '4', label: t('April') },
    { value: '5', label: t('Mei') },
    { value: '6', label: t('Juni') },
    { value: '7', label: t('Juli') },
    { value: '8', label: t('Agustus') },
    { value: '9', label: t('September') },
    { value: '10', label: t('Oktober') },
    { value: '11', label: t('November') },
    { value: '12', label: t('Desember') },
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
    { value: 'all', label: t('Semua Produk') },
];

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const formatMoney = (v: number | string | null | undefined): string =>
    money.format(Number(v || 0));

function apply(): void {
    router.get(
        '/lending/reports/write-offs/beneficiaries',
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
    return `/lending/reports/write-offs/pdf?${q.toString()}`;
});

const excelUrl = computed(() => {
    const q = new URLSearchParams(
        Object.fromEntries(
            Object.entries(baseParams.value).map(([k, v]) => [k, String(v)]),
        ),
    );
    return `/lending/reports/write-offs/excel?${q.toString()}`;
});

function openDetail(rowId: number): void {
    router.visit(`/lending/loans/${rowId}`);
}
</script>

<template>
    <Head :title="t('Pinjaman Dihapusbukukan — Anggota Kelompok')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">{{ t('Laporan Piutang') }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ t('Pinjaman Dihapusbukukan — Anggota Kelompok') }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Daftar pinjaman kelompok (<span class="font-semibold text-primary">legacy_source = group_loan</span>)
                        yang berstatus <AppBadge tone="error">{{ t('Dihapusbukukan') }}</AppBadge>
                        per <span class="font-semibold text-primary">{{ period_label }}</span>
                        — {{ identity.legal_name }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="pdfUrl" target="_blank" class="inline-flex">
                        <AppButton variant="outline">
                            <span class="material-symbols-outlined mr-1.5 text-base">picture_as_pdf</span>
                            {{ t('Cetak PDF') }}
                        </AppButton>
                    </a>
                    <a :href="excelUrl" class="inline-flex">
                        <AppButton variant="outline">
                            <span class="material-symbols-outlined mr-1.5 text-base">table_view</span>
                            {{ t('Export Excel') }}
                        </AppButton>
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <AppCard class="p-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">{{ t('Tahun') }}</label>
                        <SmartSelect v-model="selectedYear" :options="yearOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">{{ t('Bulan') }}</label>
                        <SmartSelect v-model="selectedMonth" :options="monthOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">{{ t('Produk') }}</label>
                        <SmartSelect v-model="selectedProduct" :options="productOptions" @update:model-value="apply" hide-label />
                    </div>
                </div>
            </AppCard>

            <!-- KPI cards -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('Jumlah Pinjaman Hapus') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('Total Pokok') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.principal_total) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('Sisa Pokok Saat Hapus') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.sisa_pokok_total) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('Cadangan CKPN') }}</p>
                    <p class="mt-2 text-2xl font-bold text-error">{{ formatMoney(totals.ckpn_total) }}</p>
                </AppCard>
            </div>

            <!-- Table -->
            <AppCard class="overflow-x-auto p-0">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-surface-variant/40 text-on-surface font-semibold">
                        <tr class="border-b border-outline-variant/30">
                            <th class="p-3 text-center" style="width: 4%;">{{ t('No') }}</th>
                            <th class="p-3 text-left" style="width: 13%;">{{ t('Nomor Pinjaman') }}</th>
                            <th class="p-3 text-left" style="width: 17%;">{{ t('Kelompok') }}</th>
                            <th class="p-3 text-left" style="width: 10%;">{{ t('Desa') }}</th>
                            <th class="p-3 text-right" style="width: 11%;">{{ t('Pokok') }}</th>
                            <th class="p-3 text-right" style="width: 11%;">{{ t('Sisa Pokok') }}</th>
                            <th class="p-3 text-right" style="width: 11%;">CKPN</th>
                            <th class="p-3 text-right" style="width: 11%;">{{ t('Nilai Bersih') }}</th>
                            <th class="p-3 text-center" style="width: 8%;">{{ t('Tgl Hapus') }}</th>
                            <th class="p-3 text-left" style="width: 15%;">{{ t('Alasan') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <tr v-if="rows.length === 0">
                            <td colspan="10" class="px-3 py-12 text-center text-on-surface-variant">
                                {{ t('Tidak ada pinjaman kelompok yang dihapusbukukan pada periode ini.') }}
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
                                <div class="font-semibold">{{ row.group_name || '—' }}</div>
                                <div v-if="row.group_code" class="text-[10px] uppercase tracking-wide text-on-surface-variant">{{ row.group_code }}</div>
                            </td>
                            <td class="p-3">{{ row.village_name || '—' }}</td>
                            <td class="p-3 text-right tabular-nums">{{ formatMoney(row.principal_amount) }}</td>
                            <td class="p-3 text-right tabular-nums">{{ formatMoney(row.sisa_pokok) }}</td>
                            <td class="p-3 text-right tabular-nums text-error font-semibold">{{ formatMoney(row.ckpn) }}</td>
                            <td class="p-3 text-right tabular-nums">{{ formatMoney(row.nilai_bersih) }}</td>
                            <td class="p-3 text-center">{{ row.written_off_at_label }}</td>
                            <td class="p-3 text-on-surface-variant">{{ row.reason || '—' }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length > 0" class="bg-surface-variant/40 font-bold text-on-surface">
                        <tr class="border-t-2 border-outline">
                            <td colspan="4" class="p-3">TOTAL ({{ totals.count }} pinjaman)</td>
                            <td class="p-3 text-right tabular-nums">{{ formatMoney(totals.principal_total) }}</td>
                            <td class="p-3 text-right tabular-nums">{{ formatMoney(totals.sisa_pokok_total) }}</td>
                            <td class="p-3 text-right tabular-nums text-error">{{ formatMoney(totals.ckpn_total) }}</td>
                            <td class="p-3 text-right tabular-nums">{{ formatMoney(totals.nilai_bersih_total) }}</td>
                            <td colspan="2" />
                        </tr>
                    </tfoot>
                </table>
            </AppCard>

            <AppCard v-if="rows.length === 0" class="p-8 text-center">
                <AppEmptyState
                    icon="description"
                    :title="t('Belum ada pinjaman dihapusbukukan')"
                    :description="t('Tidak ada pinjaman kelompok dengan status hapus buku pada periode yang dipilih.')"
                />
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
