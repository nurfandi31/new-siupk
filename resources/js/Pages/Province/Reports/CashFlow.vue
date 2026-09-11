<script setup>
import { Head } from '@inertiajs/vue3';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import { useMoney } from '../../../composables/useMoney';
import ProvinceLayout from '../../../Layouts/ProvinceLayout.vue';

const props = defineProps({
    report: { type: Object, required: true },
    year: { type: Number, required: true },
    month: { type: [Number, String], default: '' },
    province_name: { type: String, default: 'Provinsi' },
});

const { money } = useMoney();
const baseUrl = '/province/reports/cash-flow';
</script>

<template>
    <Head :title="`Arus Kas Konsolidasi - Provinsi ${province_name}`" />
    <ProvinceLayout>
        <div class="space-y-6">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Arus Kas Konsolidasi Provinsi {{ province_name }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Arus Kas Operasional, Investasi, dan Pendanaan ({{ report.period?.period_label }}).
                    </p>
                </div>

                <ReportPeriodFilter
                    :year="year"
                    :month="month"
                    :base-url="baseUrl"
                    pdf-url="/province/reports/pdf"
                />
            </div>

            <AppCard :padded="false">
                <div class="border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary text-lg">LAPORAN ARUS KAS KONSOLIDASI</h2>
                </div>
                <div class="p-6 overflow-x-auto">
                    <table class="w-full text-left text-sm divide-y divide-outline-variant">
                        <thead>
                            <tr class="bg-surface-container-low text-xs font-bold uppercase">
                                <th class="px-4 py-3">Aktivitas</th>
                                <th class="px-4 py-3 text-right">Jumlah (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td class="px-4 py-3">Arus Kas Aktivitas Operasional</td><td class="px-4 py-3 text-right">{{ money(report.operating_activities) }}</td></tr>
                            <tr><td class="px-4 py-3">Arus Kas Aktivitas Investasi</td><td class="px-4 py-3 text-right">{{ money(report.investing_activities) }}</td></tr>
                            <tr><td class="px-4 py-3">Arus Kas Aktivitas Pendanaan</td><td class="px-4 py-3 text-right">{{ money(report.financing_activities) }}</td></tr>
                            <tr class="font-bold bg-surface-container-low"><td class="px-4 py-3">Kenaikan / (Penurunan) Kas Bersih</td><td class="px-4 py-3 text-right">{{ money(report.net_cash_change) }}</td></tr>
                            <tr class="font-bold bg-secondary-container text-on-secondary-container"><td class="px-4 py-3">SALDO KAS & BANK AKHIR PERIODE</td><td class="px-4 py-3 text-right">{{ money(report.ending_cash) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </ProvinceLayout>
</template>
