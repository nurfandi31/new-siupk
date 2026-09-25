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

// Levels list yang dipakai untuk render header kolom.
// Default fallback: 3 tingkat standar.
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

function apply() {
    router.get(
        '/lending/reports/kolek-desa-individu',
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
    return `/lending/reports/kolek-desa-individu/pdf?${q.toString()}`;
});

// Format short number untuk header kolek (mis. "Kolek 1 (< 3 bln)")
function formatLevelHeader(lvl) {
    return `${lvl.nama}`;
}
</script>

<template>
    <Head title="Kolektibilitas Pinjaman" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Laporan Piutang</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Kolektibilitas Pinjaman Individu Rekap Desa</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Klasifikasi kualitas portofolio piutang individu per desa berdasarkan aturan kolektabilitas aktif di SOP Lembaga.
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

            <!-- Active kolek config summary -->
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
                                <th rowspan="2" class="p-3 text-left">Desa</th>
                                <th rowspan="2" class="p-3 text-right">Alokasi</th>
                                <th rowspan="2" class="p-3 text-right">Saldo Pokok</th>
                                <th rowspan="2" class="p-3">Ratio %</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">Tunggakan</th>
                                <th
                                    v-for="lvl in levelColumns"
                                    :key="lvl.bucket_key"
                                    class="p-3 border-l border-outline-variant/20"
                                    :class="{ 'border-l-0': $index === 0 }"
                                >
                                    {{ formatLevelHeader(lvl) }}
                                </th>
                            </tr>
                            <tr class="border-b border-outline-variant/30 text-center text-[11px] text-on-surface-variant">
                                <th class="p-2 border-l border-outline-variant/20 text-right">Pokok</th>
                                <th class="p-2 text-right">Jasa</th>
                                <th v-for="lvl in levelColumns" :key="`sub-${lvl.bucket_key}`" class="p-2 text-right" :class="{ 'border-l border-outline-variant/20': $index === 0 }">
                                    <span class="text-on-surface-variant">Tingkat {{ lvl.level }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20">
                            <tr v-for="v in prod.villages" :key="v.village_name" class="hover:bg-surface-variant/20">
                                <td class="p-3 font-medium text-on-surface">{{ v.village_name }}</td>
                                <td class="p-3 text-right">{{ formatMoney(v.alokasi) }}</td>
                                <td class="p-3 text-right font-semibold text-primary">{{ formatMoney(v.saldo) }}</td>
                                <td class="p-3 text-center font-medium">{{ v.alokasi > 0 ? Math.round((v.saldo / v.alokasi) * 100) : 0 }}%</td>
                                <td class="p-3 text-right border-l border-outline-variant/20" :class="v.tunggakan_pokok > 0 ? 'text-error font-medium' : ''">{{ formatMoney(v.tunggakan_pokok) }}</td>
                                <td class="p-3 text-right" :class="v.tunggakan_jasa > 0 ? 'text-error font-medium' : ''">{{ formatMoney(v.tunggakan_jasa) }}</td>
                                <td
                                    v-for="lvl in levelColumns"
                                    :key="`v-${lvl.bucket_key}-${v.village_name}`"
                                    class="p-3 text-right"
                                    :class="{ 'border-l border-outline-variant/20': $index === 0, 'text-error': lvl.level >= 4 && (v[lvl.bucket_key] || 0) > 0, 'text-tertiary': lvl.level === 3 && (v[lvl.bucket_key] || 0) > 0, 'text-secondary': lvl.level <= 2 && (v[lvl.bucket_key] || 0) > 0 }"
                                >
                                    {{ formatMoney(v[lvl.bucket_key]) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                            <tr class="border-t border-outline-variant/40">
                                <td class="p-3">TOTAL {{ prod.product_code }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.alokasi) }}</td>
                                <td class="p-3 text-right text-primary">{{ formatMoney(prod.totals.saldo) }}</td>
                                <td class="p-3 text-center">{{ prod.totals.alokasi > 0 ? Math.round((prod.totals.saldo / prod.totals.alokasi) * 100) : 0 }}%</td>
                                <td class="p-3 text-right border-l border-outline-variant/20" :class="prod.totals.tunggakan_pokok > 0 ? 'text-error' : ''">{{ formatMoney(prod.totals.tunggakan_pokok) }}</td>
                                <td class="p-3 text-right" :class="prod.totals.tunggakan_jasa > 0 ? 'text-error' : ''">{{ formatMoney(prod.totals.tunggakan_jasa) }}</td>
                                <td
                                    v-for="lvl in levelColumns"
                                    :key="`f-${lvl.bucket_key}`"
                                    class="p-3 text-right"
                                    :class="{ 'border-l border-outline-variant/20': $index === 0 }"
                                >
                                    {{ formatMoney(prod.totals[lvl.bucket_key]) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </AppCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
