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
    period_label: string;
    period_subtitle?: string;
    identity: { legal_name: string; short_name: string | null };
    products: Array<{
        product_code: string;
        product_name: string;
        villages: Array<{
            village_name: string;
            alokasi: number;
            saldo: number;
            tunggakan_pokok: number;
            tunggakan_jasa: number;
            kolek1_lancar: number;
            kolek2_diragukan: number;
            kolek3_macet: number;
        }>;
        totals: {
            alokasi: number;
            saldo: number;
            tunggakan_pokok: number;
            tunggakan_jasa: number;
            kolek1_lancar: number;
            kolek2_diragukan: number;
            kolek3_macet: number;
        };
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

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const formatMoney = (v: number | string | null | undefined): string => money.format(Number(v ?? 0));

function apply(): void {
    router.get(
        '/lending/reports/weekly/kolek-kelompok',
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
    return `/lending/reports/weekly/kolek-kelompok/pdf?${q.toString()}`;
});

function pct(alokasi: number, saldo: number): number {
    if (alokasi <= 0) return 0;
    return Math.round((saldo / alokasi) * 100);
}
</script>

<template>
    <Head title="Kolek Mingguan Kelompok" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan Lending — Mingguan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Kolektibilitas Mingguan — Kelompok (Rekap Desa)</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ period_label }}<span v-if="period_subtitle"> ({{ period_subtitle }})</span> — {{ identity.legal_name }}
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
                        <SmartSelect v-model="selectedProduct" :options="[{ value: 'all', label: 'Semua Produk' }]" hide-label disabled />
                    </div>
                </div>
            </AppCard>

            <AppCard class="p-4 bg-surface-container-low">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="text-sm">
                        <span class="font-bold text-primary">Periode Mingguan Aktif:</span>
                        <span class="text-on-surface">Minggu ke-{{ week }} • {{ week_start }} s.d. {{ week_end }}</span>
                    </div>
                    <div class="text-xs text-on-surface-variant">
                        Subjek: <span class="font-semibold text-primary">Kelompok (Rekap Desa)</span>
                    </div>
                </div>
            </AppCard>

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
                                <th rowspan="2" class="p-3 text-left">Desa</th>
                                <th rowspan="2" class="p-3 text-right">Alokasi</th>
                                <th rowspan="2" class="p-3 text-right">Saldo Pokok</th>
                                <th rowspan="2" class="p-3">Ratio %</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">Tunggakan</th>
                                <th class="p-3 border-l border-outline-variant/20">Lancar</th>
                                <th class="p-3">Diragukan</th>
                                <th class="p-3">Macet</th>
                            </tr>
                            <tr class="border-b border-outline-variant/30 text-center text-[11px] text-on-surface-variant">
                                <th class="p-2 border-l border-outline-variant/20 text-right">Pokok</th>
                                <th class="p-2 text-right">Jasa</th>
                                <th class="p-2 border-l border-outline-variant/20 text-right">1-3 Bln</th>
                                <th class="p-2 text-right">4-5 Bln</th>
                                <th class="p-2 text-right">6+ Bln</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20">
                            <tr v-for="v in prod.villages" :key="v.village_name" class="hover:bg-surface-variant/20">
                                <td class="p-3 font-medium text-on-surface">{{ v.village_name }}</td>
                                <td class="p-3 text-right">{{ formatMoney(v.alokasi) }}</td>
                                <td class="p-3 text-right font-semibold text-primary">{{ formatMoney(v.saldo) }}</td>
                                <td class="p-3 text-center font-medium">{{ pct(v.alokasi, v.saldo) }}%</td>
                                <td class="p-3 text-right border-l border-outline-variant/20" :class="v.tunggakan_pokok > 0 ? 'text-error font-medium' : ''">{{ formatMoney(v.tunggakan_pokok) }}</td>
                                <td class="p-3 text-right" :class="v.tunggakan_jasa > 0 ? 'text-error font-medium' : ''">{{ formatMoney(v.tunggakan_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20 font-medium text-secondary">{{ formatMoney(v.kolek1_lancar) }}</td>
                                <td class="p-3 text-right font-medium" :class="v.kolek2_diragukan > 0 ? 'text-tertiary' : ''">{{ formatMoney(v.kolek2_diragukan) }}</td>
                                <td class="p-3 text-right font-medium" :class="v.kolek3_macet > 0 ? 'text-error' : ''">{{ formatMoney(v.kolek3_macet) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                            <tr class="border-t border-outline-variant/40">
                                <td class="p-3">TOTAL {{ prod.product_code }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.alokasi) }}</td>
                                <td class="p-3 text-right text-primary">{{ formatMoney(prod.totals.saldo) }}</td>
                                <td class="p-3 text-center">{{ pct(prod.totals.alokasi, prod.totals.saldo) }}%</td>
                                <td class="p-3 text-right border-l border-outline-variant/20" :class="prod.totals.tunggakan_pokok > 0 ? 'text-error' : ''">{{ formatMoney(prod.totals.tunggakan_pokok) }}</td>
                                <td class="p-3 text-right" :class="prod.totals.tunggakan_jasa > 0 ? 'text-error' : ''">{{ formatMoney(prod.totals.tunggakan_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20 text-secondary">{{ formatMoney(prod.totals.kolek1_lancar) }}</td>
                                <td class="p-3 text-right" :class="prod.totals.kolek2_diragukan > 0 ? 'text-tertiary' : ''">{{ formatMoney(prod.totals.kolek2_diragukan) }}</td>
                                <td class="p-3 text-right" :class="prod.totals.kolek3_macet > 0 ? 'text-error' : ''">{{ formatMoney(prod.totals.kolek3_macet) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </AppCard>
            </div>

            <AppCard v-if="products.length === 0" class="p-8 text-center">
                <p class="text-sm text-on-surface-variant">Tidak ada pinjaman kelompok aktif pada periode ini.</p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>