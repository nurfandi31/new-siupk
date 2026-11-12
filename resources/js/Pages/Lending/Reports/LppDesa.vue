<script setup>
import { useI18n } from '../../../i18n';
const { t } = useI18n();
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    period_label: { type: String, required: true },
    identity: { type: Object, required: true },
    products: { type: Array, required: true },
    totals: { type: Object, required: true },
    filters: { type: Object, required: true },
    report_scope: { type: String, default: 'group' },
});

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));
const selectedProduct = ref(props.filters.product || 'all');
const isIndividu = computed(() => props.report_scope === 'member');
const routeBase = computed(() =>
    isIndividu.value ? '/lending/reports/lpp-desa-individu' : '/lending/reports/lpp-desa',
);

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

function apply() {
    router.get(
        routeBase.value,
        {
            year: selectedYear.value,
            month: selectedMonth.value,
            product: selectedProduct.value,
            scope: isIndividu.value ? 'member' : 'all',
        },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
        product: selectedProduct.value,
        scope: isIndividu.value ? 'member' : 'all',
    });
    return `${routeBase.value}/pdf?${q.toString()}`;
});
</script>

<template>
    <Head :title="isIndividu ? t('LPP Rekap Desa Individu') : t('LPP Rekap Desa')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">{{ t('Laporan Piutang') }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">
                        {{ isIndividu ? t('LPP Rekap Desa Individu') : t('LPP Rekap per Desa') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('Daftar perkembangan piutang') }} {{ isIndividu ? 'individu' : '' }} {{ t('rekapitulasi desa (Target, Realisasi, Saldo, dan Tunggakan)') }}
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
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-primary/10 text-primary">
                        {{ prod.villages.length }} {{ t('Desa Terdata') }}
                    </span>
                </div>

                <AppCard class="overflow-x-auto p-0">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-surface-variant/40 text-on-surface font-semibold">
                            <tr class="border-b border-outline-variant/30 text-center">
                                <th rowspan="2" class="p-3 text-left">{{ t('Desa') }}</th>
                                <th v-if="!isIndividu" rowspan="2" class="p-3">{{ t('Kel') }}</th>
                                <th v-if="!isIndividu" rowspan="2" class="p-3">{{ t('Pmf') }}</th>
                                <th rowspan="2" class="p-3 text-right">{{ t('Alokasi') }}</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">{{ t('Target') }}</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">{{ t('Real s.d. Lalu') }}</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">{{ t('Real Bulan Ini') }}</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">{{ t('Real Kumulatif') }}</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">{{ t('Saldo') }}</th>
                                <th colspan="2" class="p-3 border-l border-outline-variant/20">{{ t('Tunggakan') }}</th>
                            </tr>
                            <tr class="border-b border-outline-variant/30 text-center text-[11px] text-on-surface-variant">
                                <th class="p-2 border-l border-outline-variant/20 text-right">{{ t('Pokok') }}</th>
                                <th class="p-2 text-right">{{ t('Jasa') }}</th>
                                <th class="p-2 border-l border-outline-variant/20 text-right">{{ t('Pokok') }}</th>
                                <th class="p-2 text-right">{{ t('Jasa') }}</th>
                                <th class="p-2 border-l border-outline-variant/20 text-right">{{ t('Pokok') }}</th>
                                <th class="p-2 text-right">{{ t('Jasa') }}</th>
                                <th class="p-2 border-l border-outline-variant/20 text-right">{{ t('Pokok') }}</th>
                                <th class="p-2 text-right">{{ t('Jasa') }}</th>
                                <th class="p-2 border-l border-outline-variant/20 text-right">{{ t('Pokok') }}</th>
                                <th class="p-2 text-right">{{ t('Jasa') }}</th>
                                <th class="p-2 border-l border-outline-variant/20 text-right">{{ t('Pokok') }}</th>
                                <th class="p-2 text-right">{{ t('Jasa') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20">
                            <tr v-for="v in prod.villages" :key="v.village_name" class="hover:bg-surface-variant/20">
                                <td class="p-3 font-medium text-on-surface">{{ v.village_name }}</td>
                                <td v-if="!isIndividu" class="p-3 text-center">{{ v.kelompok_count }}</td>
                                <td v-if="!isIndividu" class="p-3 text-center">{{ v.pemanfaat_count }}</td>
                                <td class="p-3 text-right">{{ formatMoney(v.alokasi) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(v.target_pokok) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(v.target_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(v.real_lalu_pokok) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(v.real_lalu_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(v.real_ini_pokok) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(v.real_ini_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20 font-semibold">{{ formatMoney(v.real_kumulatif_pokok) }}</td>
                                <td class="p-3 text-right font-semibold">{{ formatMoney(v.real_kumulatif_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20 font-semibold text-primary">{{ formatMoney(v.saldo_pokok) }}</td>
                                <td class="p-3 text-right font-semibold text-primary">{{ formatMoney(v.saldo_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20 font-semibold" :class="v.tunggakan_pokok > 0 ? 'text-error' : ''">
                                    {{ formatMoney(v.tunggakan_pokok) }}
                                </td>
                                <td class="p-3 text-right font-semibold" :class="v.tunggakan_jasa > 0 ? 'text-error' : ''">
                                    {{ formatMoney(v.tunggakan_jasa) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                            <tr class="border-t border-outline-variant/40">
                                <td class="p-3">TOTAL {{ prod.product_code }}</td>
                                <td v-if="!isIndividu" class="p-3 text-center">{{ prod.totals.kelompok_count }}</td>
                                <td v-if="!isIndividu" class="p-3 text-center">{{ prod.totals.pemanfaat_count }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.alokasi) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(prod.totals.target_pokok) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.target_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(prod.totals.real_lalu_pokok) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.real_lalu_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(prod.totals.real_ini_pokok) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.real_ini_jasa) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(prod.totals.real_kumulatif_pokok) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.real_kumulatif_jasa) }}</td>
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
        </div>
    </AuthenticatedLayout>
</template>