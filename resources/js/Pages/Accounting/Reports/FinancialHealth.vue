<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    period_label: { type: String, required: true },
    identity: { type: Object, required: true },
    financial_data: { type: Object, required: true },
    indicators: { type: Array, required: true },
    total_score: { type: Number, required: true },
    predicate: { type: String, required: true },
    predicate_class: { type: String, required: true },
    filters: { type: Object, required: true },
});

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));

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
        '/accounting/reports/financial-health',
        { year: selectedYear.value, month: selectedMonth.value },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
    });
    return `/accounting/reports/financial-health/pdf?${q.toString()}`;
});
</script>

<template>
    <Head title="Penilaian Kesehatan Keuangan" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Analisis Keuangan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Penilaian Tingkat Kesehatan Keuangan</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Evaluasi kelayakan kinerja keuangan BUMDesma / LKD (Permendesa / Kepmendesa No. 136/2022)
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

            <!-- Score Summary Card -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <AppCard class="p-6 md:col-span-1 flex flex-col items-center justify-center text-center">
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Skor Evaluasi</p>
                    <div class="mt-2 text-5xl font-black text-primary">
                        {{ total_score }}
                        <span class="text-lg font-normal text-on-surface-variant">/ 100</span>
                    </div>
                    <div class="mt-3 px-4 py-1.5 rounded-full text-sm font-bold border" :class="predicate_class">
                        PREDIKAT: {{ predicate }}
                    </div>
                </AppCard>

                <AppCard class="p-6 md:col-span-2 space-y-4">
                    <h3 class="text-sm font-bold text-on-surface">Data Pokok Finansial (Basis Perhitungan)</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div class="p-3 rounded-lg bg-surface-variant/30">
                            <span class="text-on-surface-variant block">Total Pendapatan</span>
                            <span class="font-bold text-sm text-on-surface">{{ formatMoney(financial_data.pendapatan) }}</span>
                        </div>
                        <div class="p-3 rounded-lg bg-surface-variant/30">
                            <span class="text-on-surface-variant block">Total Beban/Biaya</span>
                            <span class="font-bold text-sm text-on-surface">{{ formatMoney(financial_data.biaya) }}</span>
                        </div>
                        <div class="p-3 rounded-lg bg-surface-variant/30">
                            <span class="text-on-surface-variant block">Surplus Bersih</span>
                            <span class="font-bold text-sm text-secondary">{{ formatMoney(financial_data.surplus) }}</span>
                        </div>
                        <div class="p-3 rounded-lg bg-surface-variant/30">
                            <span class="text-on-surface-variant block">Total Aset</span>
                            <span class="font-bold text-sm text-primary">{{ formatMoney(financial_data.total_aset) }}</span>
                        </div>
                        <div class="p-3 rounded-lg bg-surface-variant/30">
                            <span class="text-on-surface-variant block">Saldo Pokok Pinjaman</span>
                            <span class="font-bold text-sm text-on-surface">{{ formatMoney(financial_data.saldo_pokok) }}</span>
                        </div>
                        <div class="p-3 rounded-lg bg-surface-variant/30">
                            <span class="text-on-surface-variant block">Tunggakan Pokok</span>
                            <span class="font-bold text-sm text-error">{{ formatMoney(financial_data.tunggakan_pokok) }}</span>
                        </div>
                        <div class="p-3 rounded-lg bg-surface-variant/30">
                            <span class="text-on-surface-variant block">Cadangan Piutang (CKPN)</span>
                            <span class="font-bold text-sm text-on-surface">{{ formatMoney(financial_data.ckpn) }}</span>
                        </div>
                        <div class="p-3 rounded-lg bg-surface-variant/30">
                            <span class="text-on-surface-variant block">Total Ekuitas</span>
                            <span class="font-bold text-sm text-on-surface">{{ formatMoney(financial_data.total_ekuitas) }}</span>
                        </div>
                    </div>
                </AppCard>
            </div>

            <!-- Indicators Detail Table -->
            <AppCard class="overflow-x-auto p-0">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-surface-variant/40 text-on-surface font-semibold">
                        <tr class="border-b border-outline-variant/30">
                            <th class="p-3 w-10 text-center">No</th>
                            <th class="p-3">Indikator Kinerja Keuangan</th>
                            <th class="p-3">Formula / Rasio</th>
                            <th class="p-3 text-right">Nilai Rasio</th>
                            <th class="p-3 text-center">Bobot</th>
                            <th class="p-3 text-right">Skor</th>
                            <th class="p-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <tr v-for="(ind, idx) in indicators" :key="ind.name" class="hover:bg-surface-variant/10">
                            <td class="p-3 text-center text-on-surface-variant">{{ idx + 1 }}</td>
                            <td class="p-3">
                                <div class="font-bold text-on-surface">{{ ind.name }}</div>
                                <div class="text-[11px] text-on-surface-variant">{{ ind.desc }}</div>
                            </td>
                            <td class="p-3 text-on-surface-variant font-mono text-[11px]">{{ ind.formula }}</td>
                            <td class="p-3 text-right font-bold text-on-surface">{{ ind.value }} {{ ind.unit }}</td>
                            <td class="p-3 text-center">{{ ind.weight }}</td>
                            <td class="p-3 text-right font-bold text-primary">{{ ind.score }}</td>
                            <td class="p-3 text-center">
                                <AppBadge
                                    :tone="ind.status === 'Sehat' ? 'success-soft' : (ind.status === 'Cukup Sehat' ? 'warning-soft' : 'error-soft')"
                                >
                                    {{ ind.status }}
                                </AppBadge>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                        <tr class="border-t border-outline-variant/40">
                            <td colspan="4" class="p-3 text-right">TOTAL BOBOT & SKOR</td>
                            <td class="p-3 text-center">100</td>
                            <td class="p-3 text-right text-primary text-sm">{{ total_score }}</td>
                            <td class="p-3 text-center font-bold text-sm">{{ predicate }}</td>
                        </tr>
                    </tfoot>
                </table>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>