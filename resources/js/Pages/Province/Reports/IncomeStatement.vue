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
const baseUrl = '/province/reports/income-statement';
</script>

<template>
    <Head :title="`Laba Rugi Konsolidasi - Provinsi ${province_name}`" />
    <ProvinceLayout>
        <div class="space-y-6">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Laba Rugi Konsolidasi Provinsi {{ province_name }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Pendapatan, Beban, dan Hasil Usaha Konsolidasi ({{ report.period?.period_label }}).
                    </p>
                </div>

                <ReportPeriodFilter
                    :year="year"
                    :month="month"
                    :base-url="baseUrl"
                    pdf-url="/province/reports/pdf"
                />
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Pendapatan Operasional</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.revenue_ops?.total) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Beban Operasional</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.expense_ops?.total) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Laba Bersih YTD</span>
                    <p class="mt-2 text-2xl font-bold text-secondary">{{ money(report.summary?.after_tax?.ytd) }}</p>
                </AppCard>
            </div>

            <AppCard :padded="false">
                <div class="border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary text-lg">LAPORAN LABA RUGI KONSOLIDASI</h2>
                </div>
                <div class="p-6 overflow-x-auto">
                    <table class="w-full text-left text-sm divide-y divide-outline-variant">
                        <thead>
                            <tr class="bg-surface-container-low text-xs font-bold uppercase">
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-4 py-3">Uraian</th>
                                <th class="px-4 py-3 text-right">Jumlah (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="font-bold bg-surface-container-lowest"><td colspan="3" class="px-4 py-2 text-secondary">PENDAPATAN OPERASIONAL</td></tr>
                            <tr v-for="row in report.revenue_ops.rows" :key="row.code">
                                <td class="px-4 py-2 font-mono text-xs">{{ row.code }}</td>
                                <td class="px-4 py-2">{{ row.name }}</td>
                                <td class="px-4 py-2 text-right">{{ money(row.amount) }}</td>
                            </tr>
                            <tr class="font-bold bg-surface-container-low"><td colspan="2" class="px-4 py-2 text-right">SUBTOTAL PENDAPATAN OPERASIONAL</td><td class="px-4 py-2 text-right">{{ money(report.revenue_ops.total) }}</td></tr>

                            <tr class="font-bold bg-surface-container-lowest"><td colspan="3" class="px-4 py-2 text-secondary">BEBAN OPERASIONAL</td></tr>
                            <tr v-for="row in report.expense_ops.rows" :key="row.code">
                                <td class="px-4 py-2 font-mono text-xs">{{ row.code }}</td>
                                <td class="px-4 py-2">{{ row.name }}</td>
                                <td class="px-4 py-2 text-right">{{ money(row.amount) }}</td>
                            </tr>
                            <tr class="font-bold bg-surface-container-low"><td colspan="2" class="px-4 py-2 text-right">SUBTOTAL BEBAN OPERASIONAL</td><td class="px-4 py-2 text-right">{{ money(report.expense_ops.total) }}</td></tr>

                            <tr class="font-bold bg-secondary-container text-on-secondary-container"><td colspan="2" class="px-4 py-3 text-right">LABA OPERASIONAL</td><td class="px-4 py-3 text-right">{{ money(report.summary.operating_profit.ytd) }}</td></tr>
                            <tr class="font-bold bg-secondary text-on-secondary text-base"><td colspan="2" class="px-4 py-3 text-right">LABA BERSIH (NET PROFIT)</td><td class="px-4 py-3 text-right">{{ money(report.summary.after_tax.ytd) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </ProvinceLayout>
</template>
