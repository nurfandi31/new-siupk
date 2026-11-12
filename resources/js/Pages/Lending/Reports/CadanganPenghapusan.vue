<script setup>
import { useI18n } from '../../../i18n';
const { t } = useI18n();
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

const levelColumns = computed(() => {
    if (props.levels && props.levels.length > 0) {
        return props.levels;
    }
    return [
        { level: 1, nama: t('Lancar'), prosentase: 0.5, bucket_key: 'kolek1_lancar' },
        { level: 2, nama: t('Diragukan'), prosentase: 50, bucket_key: 'kolek2_diragukan' },
        { level: 3, nama: t('Macet'), prosentase: 100, bucket_key: 'kolek3_macet' },
    ];
});

function formatPct(value) {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(Number(value || 0));
}

function apply() {
    router.get(
        '/lending/reports/cadangan-penghapusan',
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
    return `/lending/reports/cadangan-penghapusan/pdf?${q.toString()}`;
});
</script>

<template>
    <Head :title="t('Cadangan Penghapusan Piutang')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">{{ t('Laporan Piutang') }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ t('Cadangan Kerugian Penurunan Nilai (CKPN)') }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('Perhitungan penyisihan cadangan risiko piutang: 0.5% (Lancar), 50% (Diragukan), dan 100% (Macet)') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="pdfUrl" target="_blank" class="inline-flex">
                        <AppButton variant="outline">
                            <span class="material-symbols-outlined mr-1.5 text-base">picture_as_pdf</span>
                            {{ t('Cetak PDF') }}
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
                                <th rowspan="2" class="p-3 text-left">{{ t('Desa') }}</th>
                                <th rowspan="2" class="p-3 text-right">{{ t('Saldo Pokok') }}</th>
                                <th :colspan="levelColumns.length" class="p-3 border-l border-outline-variant/20">{{ t('Klasifikasi Kolektibilitas') }}</th>
                                <th :colspan="levelColumns.length" class="p-3 border-l border-outline-variant/20">{{ t('Penyisihan Cadangan (CKPN)') }}</th>
                                <th rowspan="2" class="p-3 border-l border-outline-variant/20 text-right">{{ t('Total CKPN') }}</th>
                            </tr>
                            <tr class="border-b border-outline-variant/30 text-center text-[11px] text-on-surface-variant">
                                <th
                                    v-for="lvl in levelColumns"
                                    :key="`kolek-${lvl.bucket_key}`"
                                    class="p-2 border-l border-outline-variant/20 text-right"
                                >
                                    {{ lvl.nama }}
                                </th>
                                <th
                                    v-for="lvl in levelColumns"
                                    :key="`ckpn-${lvl.bucket_key}`"
                                    class="p-2 border-l border-outline-variant/20 text-right"
                                >
                                    {{ formatPct(lvl.prosentase) }}% {{ lvl.nama }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20">
                            <tr v-for="v in prod.villages" :key="v.village_name" class="hover:bg-surface-variant/20">
                                <td class="p-3 font-medium text-on-surface">{{ v.village_name }}</td>
                                <td class="p-3 text-right font-semibold text-primary">{{ formatMoney(v.saldo) }}</td>
                                <td
                                    v-for="lvl in levelColumns"
                                    :key="`v-${lvl.bucket_key}-${v.village_name}`"
                                    class="p-3 border-l border-outline-variant/20 text-right"
                                    :class="(v[lvl.bucket_key] || 0) > 0 ? (lvl.level === levelColumns.length ? 'text-error' : 'text-secondary') : ''"
                                >
                                    {{ formatMoney(v[lvl.bucket_key]) }}
                                </td>
                                <td
                                    v-for="lvl in levelColumns"
                                    :key="`v-ckpn-${lvl.bucket_key}-${v.village_name}`"
                                    class="p-3 text-right"
                                >
                                    {{ formatMoney(v[`ckpn_${lvl.level}`]) }}
                                </td>
                                <td class="p-3 text-right border-l border-outline-variant/20 font-bold text-on-surface">{{ formatMoney(v.total_ckpn) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                            <tr class="border-t border-outline-variant/40">
                                <td class="p-3">TOTAL {{ prod.product_code }}</td>
                                <td class="p-3 text-right text-primary">{{ formatMoney(prod.totals.saldo) }}</td>
                                <td
                                    v-for="lvl in levelColumns"
                                    :key="`f-${lvl.bucket_key}`"
                                    class="p-3 border-l border-outline-variant/20 text-right"
                                    :class="(prod.totals[lvl.bucket_key] || 0) > 0 ? (lvl.level === levelColumns.length ? 'text-error' : 'text-secondary') : ''"
                                >
                                    {{ formatMoney(prod.totals[lvl.bucket_key]) }}
                                </td>
                                <td v-for="lvl in levelColumns" :key="`f-ckpn-${lvl.bucket_key}`" class="p-3 text-right">
                                    {{ formatMoney(prod.totals[`ckpn_${lvl.level}`]) }}
                                </td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(prod.totals.total_ckpn) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </AppCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>