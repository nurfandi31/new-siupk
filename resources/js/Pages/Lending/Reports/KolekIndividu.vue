<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    period_label: { type: String, required: true },
    identity: { type: Object, required: true },
    levels: { type: Array, default: () => [] },
    products: { type: Array, required: true },
    totals: { type: Object, required: true },
    filters: { type: Object, required: true },
});

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
    const list = [];
    for (let y = current + 1; y >= current - 5; y--) {
        list.push({ value: String(y), label: String(y) });
    }
    return list;
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}

function apply() {
    router.get(
        '/lending/reports/kolek-individu',
        {
            year: selectedYear.value,
            month: selectedMonth.value,
            product: selectedProduct.value,
        },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
        product: selectedProduct.value,
    });
    return `/lending/reports/kolek-individu/pdf?${q.toString()}`;
});

function kolekLabel(k) {
    // Fallback untuk data yang tidak ikut config (legacy / 3 tingkat default)
    if (k === 1) return 'Lancar';
    if (k === 2) return 'Diragukan';
    return 'Macet';
}

// Levels list yang dipakai untuk badge warna kolek.
const levelColumns = computed(() => {
    if (props.levels && props.levels.length > 0) {
        return props.levels;
    }
    return [
        { level: 1, nama: 'Lancar', prosentase: 0.5, bucket_key: 'kolek1_lancar' },
        { level: 2, nama: 'Diragukan', prosentase: 50, bucket_key: 'kolek2_diragukan' },
        { level: 3, nama: 'Macet', prosentase: 100, bucket_key: 'kolek3_macet' },
    ];
});

function kolekBadgeClass(level) {
    if (!level) return 'text-on-surface-variant';
    if (level <= 1) return 'text-secondary';
    if (level === 2) return 'text-tertiary';
    if (level === 3) return 'text-error';
    return 'text-error font-bold'; // kolek 4 & 5 (lebih dari macet)
}

function kolekBadgeBgClass(level) {
    if (!level) return 'bg-surface-variant/40 text-on-surface-variant';
    if (level <= 1) return 'bg-secondary/15 text-secondary';
    if (level === 2) return 'bg-tertiary/15 text-tertiary';
    return 'bg-error/15 text-error font-bold';
}

function kolekLabelDynamic(level) {
    if (!level) return '-';
    const lvl = levelColumns.value.find((l) => l.level === level);
    return lvl ? lvl.nama : `Tingkat ${level}`;
}
</script>

<template>
    <Head title="Kolektibilitas Pinjaman Individu" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Laporan Piutang</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Kolektibilitas Pinjaman Individu</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Kualitas portofolio piutang per peminjam: Lancar, Diragukan, dan Macet
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
                <div class="flex flex-wrap items-center gap-2 text-xs text-on-surface-variant">
                    <span class="material-symbols-outlined text-base">rule</span>
                    <span>Aturan kolek aktif:</span>
                    <span v-for="lvl in levelColumns" :key="lvl.bucket_key" class="rounded-full bg-surface-variant/50 px-2 py-0.5">
                        Tingkat {{ lvl.level }} — <strong>{{ lvl.nama }}</strong> (CKPN {{ lvl.prosentase }}%)
                    </span>
                    <a href="/settings/sop?tab=kolek" class="ml-auto text-xs font-bold text-primary hover:underline">Ubah aturan →</a>
                </div>
            </AppCard>

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
                        <SmartSelect v-model="selectedProduct" :options="[{ value: 'all', label: 'Semua Produk' }]" hide-label disabled />
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
                                <th rowspan="2" class="p-3 text-left">Peminjam</th>
                                <th rowspan="2" class="p-3 text-right">Alokasi</th>
                                <th rowspan="2" class="p-3 text-right">Saldo Pokok</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">Tunggakan</th>
                                <th rowspan="2" class="p-3 border-l border-outline-variant/20">Kolek</th>
                            </tr>
                            <tr class="border-b border-outline-variant/30 text-center text-[11px] text-on-surface-variant">
                                <th class="p-2 border-l border-outline-variant/20 text-right">Pokok</th>
                                <th class="p-2 text-right">Jasa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20">
                            <template v-for="v in prod.villages" :key="v.village_name">
                                <tr class="bg-surface-variant/30 font-bold text-on-surface">
                                    <td colspan="7" class="p-2.5 px-3">DESA: {{ v.village_name.toUpperCase() }}</td>
                                </tr>
                                <tr v-for="loan in v.loans" :key="loan.loan_id" class="hover:bg-surface-variant/10">
                                    <td class="p-2.5">{{ v.village_name }}</td>
                                    <td class="p-2.5 font-medium text-on-surface">
                                        {{ loan.member_name }} <span class="text-on-surface-variant text-[11px]">(#{{ loan.loan_id }})</span>
                                        <div class="text-[10px] text-on-surface-variant">NIK: {{ loan.nik || '-' }}</div>
                                    </td>
                                    <td class="p-2.5 text-right">{{ formatMoney(loan.alokasi) }}</td>
                                    <td class="p-2.5 text-right font-semibold text-primary">{{ formatMoney(loan.saldo) }}</td>
                                    <td class="p-2.5 text-right border-l border-outline-variant/20" :class="loan.tunggakan_pokok > 0 ? 'text-error font-medium' : ''">
                                        {{ formatMoney(loan.tunggakan_pokok) }}
                                    </td>
                                    <td class="p-2.5 text-right" :class="loan.tunggakan_jasa > 0 ? 'text-error font-medium' : ''">
                                        {{ formatMoney(loan.tunggakan_jasa) }}
                                    </td>
                                    <td class="p-2.5 text-center border-l border-outline-variant/20">
                                        <span class="inline-block rounded-full px-2 py-0.5 text-[11px] font-bold" :class="kolekBadgeBgClass(loan.kolek)">
                                            {{ kolekLabelDynamic(loan.kolek) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr class="bg-surface-variant/20 font-semibold">
                                    <td colspan="2" class="p-2.5">Subtotal {{ v.village_name }}</td>
                                    <td class="p-2.5 text-right">{{ formatMoney(v.subtotal.alokasi) }}</td>
                                    <td class="p-2.5 text-right text-primary">{{ formatMoney(v.subtotal.saldo) }}</td>
                                    <td class="p-2.5 text-right border-l border-outline-variant/20">{{ formatMoney(v.subtotal.tunggakan_pokok) }}</td>
                                    <td class="p-2.5 text-right">{{ formatMoney(v.subtotal.tunggakan_jasa) }}</td>
                                    <td class="p-2.5 text-center border-l border-outline-variant/20">{{ v.subtotal.peminjam_count }} peminjam</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                            <tr class="border-t border-outline-variant/40">
                                <td colspan="2" class="p-3">TOTAL {{ prod.product_code }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.alokasi) }}</td>
                                <td class="p-3 text-right text-primary">{{ formatMoney(prod.totals.saldo) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20" :class="prod.totals.tunggakan_pokok > 0 ? 'text-error' : ''">
                                    {{ formatMoney(prod.totals.tunggakan_pokok) }}
                                </td>
                                <td class="p-3 text-right" :class="prod.totals.tunggakan_jasa > 0 ? 'text-error' : ''">
                                    {{ formatMoney(prod.totals.tunggakan_jasa) }}
                                </td>
                                <td class="p-3 text-center border-l border-outline-variant/20">{{ prod.totals.peminjam_count }} peminjam</td>
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
