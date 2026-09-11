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
    <Head title="Cadangan Penghapusan Piutang" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Laporan Piutang</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Cadangan Kerugian Penurunan Nilai (CKPN)</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Perhitungan penyisihan cadangan risiko piutang: 0.5% (Lancar), 50% (Diragukan), dan 100% (Macet)
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
                    <div>
                        <label class="block text-xs font-medium text-on-surface-variant mb-1">Tahun</label>
                        <SmartSelect v-model="selectedYear" :options="yearOptions" @update:model-value="apply" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-on-surface-variant mb-1">Bulan</label>
                        <SmartSelect v-model="selectedMonth" :options="monthOptions" @update:model-value="apply" />
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
                                <th rowspan="2" class="p-3 text-right">Saldo Pokok</th>
                                <th colspan="3" class="p-3 border-l border-outline-variant/20">Klasifikasi Kolektibilitas</th>
                                <th colspan="3" class="p-3 border-l border-outline-variant/20">Penyisihan Cadangan (CKPN)</th>
                                <th rowspan="2" class="p-3 border-l border-outline-variant/20 text-right">Total CKPN</th>
                            </tr>
                            <tr class="border-b border-outline-variant/30 text-center text-[11px] text-on-surface-variant">
                                <th class="p-2 border-l border-outline-variant/20 text-right">Lancar</th>
                                <th class="p-2 text-right">Diragukan</th>
                                <th class="p-2 text-right">Macet</th>
                                <th class="p-2 border-l border-outline-variant/20 text-right">0.5% Lancar</th>
                                <th class="p-2 text-right">50% Diragukan</th>
                                <th class="p-2 text-right">100% Macet</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20">
                            <tr v-for="v in prod.villages" :key="v.village_name" class="hover:bg-surface-variant/20">
                                <td class="p-3 font-medium text-on-surface">{{ v.village_name }}</td>
                                <td class="p-3 text-right font-semibold text-primary">{{ formatMoney(v.saldo) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20 text-secondary">{{ formatMoney(v.kolek1_lancar) }}</td>
                                <td class="p-3 text-right" :class="v.kolek2_diragukan > 0 ? 'text-tertiary' : ''">{{ formatMoney(v.kolek2_diragukan) }}</td>
                                <td class="p-3 text-right" :class="v.kolek3_macet > 0 ? 'text-error' : ''">{{ formatMoney(v.kolek3_macet) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(v.ckpn_lancar) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(v.ckpn_diragukan) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(v.ckpn_macet) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20 font-bold text-on-surface">{{ formatMoney(v.total_ckpn) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                            <tr class="border-t border-outline-variant/40">
                                <td class="p-3">TOTAL {{ prod.product_code }}</td>
                                <td class="p-3 text-right text-primary">{{ formatMoney(prod.totals.saldo) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20 text-secondary">{{ formatMoney(prod.totals.kolek1_lancar) }}</td>
                                <td class="p-3 text-right" :class="prod.totals.kolek2_diragukan > 0 ? 'text-tertiary' : ''">{{ formatMoney(prod.totals.kolek2_diragukan) }}</td>
                                <td class="p-3 text-right" :class="prod.totals.kolek3_macet > 0 ? 'text-error' : ''">{{ formatMoney(prod.totals.kolek3_macet) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(prod.totals.ckpn_lancar) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.ckpn_diragukan) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.ckpn_macet) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">{{ formatMoney(prod.totals.total_ckpn) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </AppCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>