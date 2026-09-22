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
        '/lending/reports/cadangan-penghapusan-individu',
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
    return `/lending/reports/cadangan-penghapusan-individu/pdf?${q.toString()}`;
});

// Hitung CKPN agregat per produk: Lancar (0.5%), Diragukan (50%), Macet (100%).
function ckpnForBucket(lancar, diragukan, macet) {
    const ckpn1 = round2(Number(lancar || 0) * 0.005);
    const ckpn2 = round2(Number(diragukan || 0) * 0.50);
    const ckpn3 = round2(Number(macet || 0) * 1.00);
    return { ckpn1, ckpn2, ckpn3, total: round2(ckpn1 + ckpn2 + ckpn3) };
}
function round2(n) {
    return Math.round(n * 100) / 100;
}
</script>

<template>
    <Head title="Cadangan Penghapusan (CKPN) — Pinjaman Individu" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Laporan Piutang</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Cadangan Penghapusan (CKPN) Individu</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Estimasi cadangan kerugian penurunan nilai piutang untuk pinjaman individu.
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
                <h2 class="text-lg font-bold text-on-surface">
                    {{ prod.product_name }} ({{ prod.product_code }})
                </h2>

                <AppCard class="overflow-x-auto p-0">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-surface-variant/40 text-on-surface font-semibold">
                            <tr class="border-b border-outline-variant/30 text-center">
                                <th rowspan="2" class="p-3 text-left">Desa</th>
                                <th rowspan="2" class="p-3 text-right">Peminjam</th>
                                <th rowspan="2" class="p-3 text-right">Saldo Pokok</th>
                                <th colspan="3" class="p-3 border-l border-outline-variant/20">Saldo per Kolek</th>
                                <th colspan="3" class="p-3 border-l border-outline-variant/20">CKPN</th>
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
                            <tr v-for="v in prod.villages" :key="v.village_name" class="hover:bg-surface-variant/10">
                                <td class="p-3 font-medium text-on-surface">{{ v.village_name }}</td>
                                <td class="p-3 text-right">{{ v.subtotal.peminjam_count }}</td>
                                <td class="p-3 text-right font-semibold text-primary">{{ formatMoney(v.subtotal.saldo) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20 text-secondary">{{ formatMoney(v.subtotal.kolek1_lancar) }}</td>
                                <td class="p-3 text-right" :class="v.subtotal.kolek2_diragukan > 0 ? 'text-tertiary' : ''">{{ formatMoney(v.subtotal.kolek2_diragukan) }}</td>
                                <td class="p-3 text-right" :class="v.subtotal.kolek3_macet > 0 ? 'text-error' : ''">{{ formatMoney(v.subtotal.kolek3_macet) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">
                                    {{ formatMoney(ckpnForBucket(v.subtotal.kolek1_lancar, 0, 0).ckpn1) }}
                                </td>
                                <td class="p-3 text-right">
                                    {{ formatMoney(ckpnForBucket(0, v.subtotal.kolek2_diragukan, 0).ckpn2) }}
                                </td>
                                <td class="p-3 text-right" :class="v.subtotal.kolek3_macet > 0 ? 'text-error font-medium' : ''">
                                    {{ formatMoney(ckpnForBucket(0, 0, v.subtotal.kolek3_macet).ckpn3) }}
                                </td>
                                <td class="p-3 text-right border-l border-outline-variant/20 font-semibold text-primary">
                                    {{ formatMoney(ckpnForBucket(v.subtotal.kolek1_lancar, v.subtotal.kolek2_diragukan, v.subtotal.kolek3_macet).total) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                            <tr class="border-t border-outline-variant/40">
                                <td class="p-3">TOTAL {{ prod.product_code }}</td>
                                <td class="p-3 text-right">{{ prod.totals.peminjam_count }}</td>
                                <td class="p-3 text-right text-primary">{{ formatMoney(prod.totals.saldo) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20 text-secondary">{{ formatMoney(prod.totals.kolek1_lancar) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.kolek2_diragukan) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(prod.totals.kolek3_macet) }}</td>
                                <td class="p-3 text-right border-l border-outline-variant/20">
                                    {{ formatMoney(ckpnForBucket(prod.totals.kolek1_lancar, 0, 0).ckpn1) }}
                                </td>
                                <td class="p-3 text-right">
                                    {{ formatMoney(ckpnForBucket(0, prod.totals.kolek2_diragukan, 0).ckpn2) }}
                                </td>
                                <td class="p-3 text-right">
                                    {{ formatMoney(ckpnForBucket(0, 0, prod.totals.kolek3_macet).ckpn3) }}
                                </td>
                                <td class="p-3 text-right border-l border-outline-variant/20 text-primary">
                                    {{ formatMoney(ckpnForBucket(prod.totals.kolek1_lancar, prod.totals.kolek2_diragukan, prod.totals.kolek3_macet).total) }}
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
