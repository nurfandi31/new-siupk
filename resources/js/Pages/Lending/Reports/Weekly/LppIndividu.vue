<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '../../../../Components/AppButton.vue';
import AppCard from '../../../../Components/AppCard.vue';
import SmartSelect from '../../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps<{
    year: number;
    month: number;
    week: number;
    week_start?: string;
    week_end?: string;
    weekly_range_label?: string;
    period_label: string;
    period_subtitle?: string;
    subject_kind?: string;
    identity: { legal_name: string; short_name: string | null };
    products: Array<{
        product_code: string;
        product_name: string;
        villages: Array<{
            village_name: string;
            loans: Array<Record<string, unknown>>;
            subtotal: Record<string, number>;
        }>;
        totals: Record<string, number>;
    }>;
    totals: Record<string, number>;
    filters: { year: number; month: number; week: number; product: string };
}>();

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));
const selectedWeek = ref(String(props.filters.week));
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
    for (let y = current - 2; y <= current + 1; y += 1) {
        list.push({ value: String(y), label: String(y) });
    }
    return list;
});

const weekOptions = [
    { value: '1', label: 'Minggu ke-1 (tgl 1–7)' },
    { value: '2', label: 'Minggu ke-2 (tgl 8–14)' },
    { value: '3', label: 'Minggu ke-3 (tgl 15–21)' },
    { value: '4', label: 'Minggu ke-4 (tgl 22–28)' },
    { value: '5', label: 'Minggu ke-5 (tgl 29–akhir bulan)' },
];

const productOptions = computed(() => {
    const seen = new Map<string, { value: string; label: string }>();
    for (const prod of props.products) {
        seen.set(prod.product_code, { value: prod.product_code, label: `${prod.product_code} — ${prod.product_name}` });
    }
    return [{ value: 'all', label: 'Semua Produk' }, ...seen.values()];
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const formatMoney = (v: number | string | null | undefined): string => money.format(Number(v ?? 0));

function apply(): void {
    router.get(
        '/lending/reports/weekly/lpp-individu',
        {
            year: selectedYear.value,
            month: selectedMonth.value,
            week: selectedWeek.value,
            product: selectedProduct.value,
        },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
        week: selectedWeek.value,
        product: selectedProduct.value,
    });
    return `/lending/reports/weekly/lpp-individu/pdf?${q.toString()}`;
});

type LoanRow = {
    loan_id: number;
    member_name: string;
    nik: string | null;
    disbursed_at: string;
    alokasi: number;
    target_pokok: number;
    target_jasa: number;
    real_lalu_pokok: number;
    real_lalu_jasa: number;
    real_ini_pokok: number;
    real_ini_jasa: number;
    real_kumulatif_pokok: number;
    real_kumulatif_jasa: number;
    saldo_pokok: number;
    saldo_jasa: number;
    tunggakan_pokok: number;
    tunggakan_jasa: number;
};

function asLoanRow(r: Record<string, unknown>): LoanRow {
    return r as unknown as LoanRow;
}
</script>

<template>
    <Head title="LPP Mingguan Individu" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan Lending — Mingguan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">LPP Mingguan — Rincian Individu</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ period_label }}<span v-if="period_subtitle"> ({{ period_subtitle }})</span> — {{ identity.legal_name }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
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
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Tahun</label>
                        <SmartSelect v-model="selectedYear" :options="yearOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Bulan</label>
                        <SmartSelect v-model="selectedMonth" :options="monthOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Minggu ke-</label>
                        <SmartSelect v-model="selectedWeek" :options="weekOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Produk</label>
                        <SmartSelect v-model="selectedProduct" :options="productOptions" @update:model-value="apply" hide-label />
                    </div>
                </div>
            </AppCard>

            <!-- Weekly context banner -->
            <AppCard class="p-4 bg-surface-container-low">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="text-sm">
                        <span class="font-bold text-primary">Periode Mingguan Aktif:</span>
                        <span class="text-on-surface">Minggu ke-{{ week }} • {{ week_start }} s.d. {{ week_end }}</span>
                    </div>
                    <div class="text-xs text-on-surface-variant">
                        Subjek: <span class="font-semibold text-primary">Individu</span>
                    </div>
                </div>
            </AppCard>

            <!-- Table per Product -->
            <div v-for="prod in products" :key="prod.product_code" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-on-surface">
                        {{ prod.product_name }} ({{ prod.product_code }})
                    </h2>
                </div>

                <AppCard class="overflow-x-auto p-0">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-surface-variant/40 text-on-surface font-semibold">
                            <tr class="border-b border-outline-variant/30 text-center">
                                <th rowspan="2" class="p-3 text-left">Peminjam / Loan ID</th>
                                <th rowspan="2" class="p-3">NIK</th>
                                <th rowspan="2" class="p-3">Pencairan</th>
                                <th rowspan="2" class="p-3 text-right">Alokasi</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">Target</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">Real s.d. Lalu</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">Real Bulan Ini</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">Real Kumulatif</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">Saldo</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">Tunggakan</th>
                            </tr>
                            <tr class="border-b border-outline-variant/30 text-center text-[11px] text-on-surface-variant">
                                <th class="p-2 border-l border-outline-variant/20 text-right">Pokok</th>
                                <th class="p-2 text-right">Jasa</th>
                                <th class="p-2 border-l border-outline-variant/20 text-right">Pokok</th>
                                <th class="p-2 text-right">Jasa</th>
                                <th class="p-2 border-l border-outline-variant/20 text-right">Pokok</th>
                                <th class="p-2 text-right">Jasa</th>
                                <th class="p-2 border-l border-outline-variant/20 text-right">Pokok</th>
                                <th class="p-2 text-right">Jasa</th>
                                <th class="p-2 border-l border-outline-variant/20 text-right">Pokok</th>
                                <th class="p-2 text-right">Jasa</th>
                                <th class="p-2 border-l border-outline-variant/20 text-right">Pokok</th>
                                <th class="p-2 text-right">Jasa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20">
                            <template v-for="v in prod.villages" :key="v.village_name">
                                <tr class="bg-surface-variant/30 font-bold text-on-surface">
                                    <td colspan="15" class="p-2.5 px-3">DESA: {{ v.village_name.toUpperCase() }}</td>
                                </tr>
                                <tr v-for="loanRaw in v.loans" :key="(loanRaw as Record<string, unknown>).loan_id as number" class="hover:bg-surface-variant/10">
                                    <td class="p-2.5 px-3 font-medium text-on-surface">
                                        {{ asLoanRow(loanRaw).member_name }}
                                        <span class="text-on-surface-variant text-[11px]">(#{{ asLoanRow(loanRaw).loan_id }})</span>
                                    </td>
                                    <td class="p-2.5 text-center text-on-surface-variant">{{ asLoanRow(loanRaw).nik || '-' }}</td>
                                    <td class="p-2.5 text-center text-on-surface-variant">{{ asLoanRow(loanRaw).disbursed_at || '-' }}</td>
                                    <td class="p-2.5 text-right">{{ formatMoney(asLoanRow(loanRaw).alokasi) }}</td>
                                    <td class="p-2.5 text-right border-l border-outline-variant/20">{{ formatMoney(asLoanRow(loanRaw).target_pokok) }}</td>
                                    <td class="p-2.5 text-right">{{ formatMoney(asLoanRow(loanRaw).target_jasa) }}</td>
                                    <td class="p-2.5 text-right border-l border-outline-variant/20">{{ formatMoney(asLoanRow(loanRaw).real_lalu_pokok) }}</td>
                                    <td class="p-2.5 text-right">{{ formatMoney(asLoanRow(loanRaw).real_lalu_jasa) }}</td>
                                    <td class="p-2.5 text-right border-l border-outline-variant/20">{{ formatMoney(asLoanRow(loanRaw).real_ini_pokok) }}</td>
                                    <td class="p-2.5 text-right">{{ formatMoney(asLoanRow(loanRaw).real_ini_jasa) }}</td>
                                    <td class="p-2.5 text-right border-l border-outline-variant/20 font-semibold">{{ formatMoney(asLoanRow(loanRaw).real_kumulatif_pokok) }}</td>
                                    <td class="p-2.5 text-right font-semibold">{{ formatMoney(asLoanRow(loanRaw).real_kumulatif_jasa) }}</td>
                                    <td class="p-2.5 text-right border-l border-outline-variant/20 font-semibold text-primary">{{ formatMoney(asLoanRow(loanRaw).saldo_pokok) }}</td>
                                    <td class="p-2.5 text-right font-semibold text-primary">{{ formatMoney(asLoanRow(loanRaw).saldo_jasa) }}</td>
                                    <td class="p-2.5 text-right border-l border-outline-variant/20 font-semibold" :class="asLoanRow(loanRaw).tunggakan_pokok > 0 ? 'text-error' : ''">
                                        {{ formatMoney(asLoanRow(loanRaw).tunggakan_pokok) }}
                                    </td>
                                    <td class="p-2.5 text-right font-semibold" :class="asLoanRow(loanRaw).tunggakan_jasa > 0 ? 'text-error' : ''">
                                        {{ formatMoney(asLoanRow(loanRaw).tunggakan_jasa) }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                            <tr class="border-t border-outline-variant/40">
                                <td colspan="3" class="p-3">TOTAL {{ prod.product_code }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.alokasi) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(prod.totals.target_pokok) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.target_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(prod.totals.real_lalu_pokok) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.real_lalu_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(prod.totals.real_ini_pokok) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.real_ini_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20 font-semibold">{{ formatMoney(prod.totals.real_kumulatif_pokok) }}</td>
                                <td class="p-3 text-right font-semibold">{{ formatMoney(prod.totals.real_kumulatif_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20 text-primary">{{ formatMoney(prod.totals.saldo_pokok) }}</td>
                                <td class="p-3 text-right text-primary">{{ formatMoney(prod.totals.saldo_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20" :class="prod.totals.tunggakan_pokok > 0 ? 'text-error' : ''">
                                    {{ formatMoney(prod.totals.tunggakan_pokok) }}
                                </td>
                                <td class="p-3 text-right" :class="prod.totals.tunggakan_jasa > 0 ? 'text-error' : ''">
                                    {{ formatMoney(prod.totals.tunggakan_jasa) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </AppCard>
            </div>

            <AppCard v-if="products.length === 0" class="p-8 text-center">
                <p class="text-sm text-on-surface-variant">Tidak ada pinjaman individu aktif pada periode ini.</p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>