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

function apply() {
    router.get(
        '/lending/reports/kolek-kelompok',
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
    return `/lending/reports/kolek-kelompok/pdf?${q.toString()}`;
});

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
    <Head :title="t('Kolektibilitas Pinjaman Kelompok')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">{{ t('Laporan Piutang') }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ t('Kolektibilitas Pinjaman Kelompok') }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('Kualitas portofolio piutang per kelompok: Lancar, Diragukan, dan Macet') }}
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

            <AppCard class="p-4">
                <div class="flex flex-wrap items-center gap-2 text-xs text-on-surface-variant">
                    <span class="material-symbols-outlined text-base">rule</span>
                    <span>{{ t('Aturan kolek aktif:') }}</span>
                    <span v-for="lvl in levelColumns" :key="lvl.bucket_key" class="rounded-full bg-surface-variant/50 px-2 py-0.5">
                        {{ t('Tingkat') }} {{ lvl.level }} - <strong>{{ lvl.nama }}</strong> (CKPN {{ lvl.prosentase }}%)
                    </span>
                    <a href="/settings/sop?tab=kolek" class="ml-auto text-xs font-bold text-primary hover:underline">{{ t('Ubah aturan') }}</a>
                </div>
            </AppCard>

            <AppCard class="p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">{{ t('Tahun') }}</label>
                        <SmartSelect v-model="selectedYear" :options="yearOptions" hide-label @update:model-value="apply" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">{{ t('Bulan') }}</label>
                        <SmartSelect v-model="selectedMonth" :options="monthOptions" hide-label @update:model-value="apply" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">{{ t('Produk') }}</label>
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
                    <table class="w-full border-collapse text-left text-xs">
                        <thead class="bg-surface-variant/40 font-semibold text-on-surface">
                            <tr class="border-b border-outline-variant/30 text-center">
                                <th rowspan="2" class="p-3 text-left">{{ t('Desa') }}</th>
                                <th rowspan="2" class="p-3 text-left">{{ t('Kelompok') }}</th>
                                <th rowspan="2" class="p-3 text-right">{{ t('Alokasi') }}</th>
                                <th rowspan="2" class="p-3 text-right">{{ t('Saldo Pokok') }}</th>
                                <th colspan="2" class="border-l border-outline-variant/20 p-3">{{ t('Tunggakan') }}</th>
                                <th rowspan="2" class="border-l border-outline-variant/20 p-3">{{ t('Kolek') }}</th>
                            </tr>
                            <tr class="border-b border-outline-variant/30 text-center text-[11px] text-on-surface-variant">
                                <th class="border-l border-outline-variant/20 p-2 text-right">{{ t('Pokok') }}</th>
                                <th class="p-2 text-right">{{ t('Jasa') }}</th>
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
                                        {{ loan.group_name }}
                                        <span class="text-[11px] text-on-surface-variant">(#{{ loan.loan_id }})</span>
                                        <div class="text-[10px] text-on-surface-variant">
                                            {{ loan.group_code || loan.loan_number }}
                                        </div>
                                    </td>
                                    <td class="p-2.5 text-right">{{ formatMoney(loan.alokasi) }}</td>
                                    <td class="p-2.5 text-right font-semibold text-primary">{{ formatMoney(loan.saldo) }}</td>
                                    <td class="border-l border-outline-variant/20 p-2.5 text-right" :class="loan.tunggakan_pokok > 0 ? 'font-medium text-error' : ''">
                                        {{ formatMoney(loan.tunggakan_pokok) }}
                                    </td>
                                    <td class="p-2.5 text-right" :class="loan.tunggakan_jasa > 0 ? 'font-medium text-error' : ''">
                                        {{ formatMoney(loan.tunggakan_jasa) }}
                                    </td>
                                    <td class="border-l border-outline-variant/20 p-2.5 text-center">
                                        <span class="inline-block rounded-full px-2 py-0.5 text-[11px] font-bold" :class="kolekBadgeBgClass(loan.kolek)">
                                            {{ kolekLabelDynamic(loan.kolek) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr class="bg-surface-variant/20 font-semibold">
                                    <td colspan="2" class="p-2.5">{{ t('Subtotal') }} {{ v.village_name }}</td>
                                    <td class="p-2.5 text-right">{{ formatMoney(v.subtotal.alokasi) }}</td>
                                    <td class="p-2.5 text-right text-primary">{{ formatMoney(v.subtotal.saldo) }}</td>
                                    <td class="border-l border-outline-variant/20 p-2.5 text-right">{{ formatMoney(v.subtotal.tunggakan_pokok) }}</td>
                                    <td class="p-2.5 text-right">{{ formatMoney(v.subtotal.tunggakan_jasa) }}</td>
                                    <td class="border-l border-outline-variant/20 p-2.5 text-center">{{ v.subtotal.peminjam_count }} {{ t('pinjaman') }}</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                            <tr class="border-t border-outline-variant/40">
                                <td colspan="2" class="p-3">TOTAL {{ prod.product_code }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.alokasi) }}</td>
                                <td class="p-3 text-right text-primary">{{ formatMoney(prod.totals.saldo) }}</td>
                                <td class="border-l border-outline-variant/20 p-3 text-right" :class="prod.totals.tunggakan_pokok > 0 ? 'text-error' : ''">
                                    {{ formatMoney(prod.totals.tunggakan_pokok) }}
                                </td>
                                <td class="p-3 text-right" :class="prod.totals.tunggakan_jasa > 0 ? 'text-error' : ''">
                                    {{ formatMoney(prod.totals.tunggakan_jasa) }}
                                </td>
                                <td class="border-l border-outline-variant/20 p-3 text-center">{{ prod.totals.peminjam_count }} {{ t('pinjaman') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </AppCard>
            </div>

            <AppCard v-if="products.length === 0" class="p-8 text-center">
                <p class="text-sm text-on-surface-variant">{{ t('Tidak ada pinjaman kelompok aktif pada periode ini.') }}</p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
