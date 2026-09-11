<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppIcon from '../../Components/AppIcon.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import { useMoney } from '../../composables/useMoney';
import { usePeriodOptions } from '../../composables/usePeriodOptions';
import ProvinceLayout from '../../Layouts/ProvinceLayout.vue';

const props = defineProps({
    metrics: { type: Object, required: true },
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    province_name: { type: String, default: 'Provinsi' },
});

const { money } = useMoney();
const { monthOptions, yearOptions } = usePeriodOptions();

const selectedYear = ref(props.year);
const selectedMonth = ref(props.month || '');

function applyFilter() {
    router.get('/province/dashboard', {
        year: selectedYear.value,
        month: selectedMonth.value || '',
    }, { preserveState: true });
}
</script>

<template>
    <Head :title="`Dashboard - Provinsi ${province_name}`" />
    <ProvinceLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Dashboard Provinsi {{ province_name }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Monitoring & konsolidasi laporan keuangan gabungan lintas Kabupaten se-Provinsi {{ province_name }}.
                    </p>
                </div>

                <!-- Filters & PDF Pack -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="w-48">
                        <SmartSelect
                            v-model="selectedMonth"
                            :options="monthOptions"
                            label="Bulan"
                            value-key="value"
                            label-key="label"
                            hide-label
                            @update:model-value="applyFilter"
                        />
                    </div>

                    <div class="w-32">
                        <SmartSelect
                            v-model="selectedYear"
                            :options="yearOptions"
                            label="Tahun"
                            value-key="value"
                            label-key="label"
                            hide-label
                            @update:model-value="applyFilter"
                        />
                    </div>

                    <a
                        :href="`/province/reports/pdf?year=${selectedYear}&month=${selectedMonth || ''}`"
                        target="_blank"
                        class="inline-flex items-center"
                    >
                        <AppButton variant="primary" icon="picture_as_pdf">Cetak Paket 5 Laporan (PDF)</AppButton>
                    </a>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard class="relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-on-surface-variant">Total Kas & Bank</span>
                        <AppIcon name="account_balance_wallet" tone="success" :container-size="9" />
                    </div>
                    <p class="mt-3 text-2xl font-bold text-primary">{{ money(metrics.summary.total_cash) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">Gabungan {{ metrics.summary.total_kecamatans }} Kecamatan</p>
                </AppCard>

                <AppCard class="relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-on-surface-variant">Pinjaman Aktif (Pokok)</span>
                        <AppIcon name="credit_score" tone="info" :container-size="9" />
                    </div>
                    <p class="mt-3 text-2xl font-bold text-primary">{{ money(metrics.summary.active_loan_principal) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ metrics.summary.active_loans_count }} Pinjaman Berjalan</p>
                </AppCard>

                <AppCard class="relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-on-surface-variant">Laba Bersih YTD</span>
                        <AppIcon name="trending_up" tone="warning" :container-size="9" />
                    </div>
                    <p class="mt-3 text-2xl font-bold text-primary">{{ money(metrics.summary.net_income_ytd) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">Hasil Usaha Konsolidasi</p>
                </AppCard>

                <AppCard class="relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-on-surface-variant">Cakupan Wilayah</span>
                        <AppIcon name="map" tone="primary" :container-size="9" />
                    </div>
                    <p class="mt-3 text-2xl font-bold text-primary">{{ metrics.summary.total_regencies }} Kabupaten</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ metrics.summary.total_kecamatans }} Unit Pengelola</p>
                </AppCard>
            </div>

            <!-- Quick Access Laporan -->
            <AppCard>
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="font-bold text-primary">Paket Laporan Keuangan Konsolidasi (5 Standar Finansial)</h2>
                        <p class="text-xs text-on-surface-variant">Buka per-halaman atau langsung download Paket 5 Laporan Keuangan lengkap dalam bentuk PDF.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="`/province/reports/pack?year=${selectedYear}&month=${selectedMonth || ''}`">
                            <AppButton variant="primary" icon="layers">Paket 5 Laporan</AppButton>
                        </Link>
                        <Link :href="`/province/reports/balance-sheet?year=${selectedYear}&month=${selectedMonth || ''}`">
                            <AppButton variant="secondary" icon="account_balance">Neraca</AppButton>
                        </Link>
                        <Link :href="`/province/reports/income-statement?year=${selectedYear}&month=${selectedMonth || ''}`">
                            <AppButton variant="secondary" icon="trending_up">Laba Rugi</AppButton>
                        </Link>
                        <Link :href="`/province/reports/cash-flow?year=${selectedYear}&month=${selectedMonth || ''}`">
                            <AppButton variant="secondary" icon="payments">Arus Kas</AppButton>
                        </Link>
                        <Link :href="`/province/reports/equity-changes?year=${selectedYear}&month=${selectedMonth || ''}`">
                            <AppButton variant="secondary" icon="balance">Perubahan Ekuitas</AppButton>
                        </Link>
                        <Link :href="`/province/reports/calk?year=${selectedYear}&month=${selectedMonth || ''}`">
                            <AppButton variant="secondary" icon="description">CALK</AppButton>
                        </Link>
                    </div>
                </div>
            </AppCard>

            <!-- Table Recap per Regency -->
            <AppCard :padded="false">
                <div class="border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary">Rekapitulasi Kinerja per Kabupaten</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs text-on-surface-variant uppercase">
                            <tr>
                                <th class="px-6 py-3">Nama Kabupaten</th>
                                <th class="px-6 py-3 text-right">Jumlah Kecamatan</th>
                                <th class="px-6 py-3 text-right">Kas & Bank</th>
                                <th class="px-6 py-3 text-right">Pinjaman Aktif</th>
                                <th class="px-6 py-3 text-right">Total Pokok</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="reg in metrics.regency_recap" :key="reg.regency_name" class="hover:bg-surface-container-low/50">
                                <td class="px-6 py-3 font-bold text-primary">{{ reg.regency_name }}</td>
                                <td class="px-6 py-3 text-right">{{ reg.kecamatans_count }}</td>
                                <td class="px-6 py-3 text-right font-semibold text-primary">{{ money(reg.cash) }}</td>
                                <td class="px-6 py-3 text-right">{{ reg.active_loans }}</td>
                                <td class="px-6 py-3 text-right">{{ money(reg.active_principal) }}</td>
                            </tr>
                            <tr v-if="!metrics.regency_recap.length">
                                <td colspan="5" class="px-6 py-8 text-center text-on-surface-variant">
                                    Belum ada data kabupaten terdaftar pada provinsi ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </ProvinceLayout>
</template>
