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
const baseUrl = '/province/reports/balance-sheet';
</script>

<template>
    <Head :title="`Neraca Konsolidasi - Provinsi ${province_name}`" />
    <ProvinceLayout>
        <div class="space-y-6">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Neraca Konsolidasi Provinsi {{ province_name }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Posisi Aset, Kewajiban, dan Ekuitas gabungan ({{ report.period?.period_label }}).
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
                    <span class="text-sm font-semibold text-on-surface-variant">Total Aset</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.assets?.total) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Total Kewajiban</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.liabilities?.total) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Total Ekuitas</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.equity?.total) }}</p>
                </AppCard>
            </div>

            <AppCard :padded="false">
                <div class="border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary text-lg">NERACA KONSOLIDASI</h2>
                </div>
                <div class="p-6 overflow-x-auto">
                    <table class="w-full text-left text-sm divide-y divide-outline-variant">
                        <thead>
                            <tr class="bg-surface-container-low text-xs font-bold uppercase">
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-4 py-3">Nama Akun</th>
                                <th class="px-4 py-3 text-right">Saldo (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="font-bold bg-surface-container-lowest"><td colspan="3" class="px-4 py-2 text-secondary">ASET</td></tr>
                            <tr v-for="row in report.assets.rows" :key="row.code" class="hover:bg-surface-container-low/50">
                                <td class="px-4 py-2 font-mono text-xs">{{ row.code }}</td>
                                <td class="px-4 py-2" :class="row.level === 1 ? 'font-bold' : ''">{{ row.name }}</td>
                                <td class="px-4 py-2 text-right" :class="row.level === 1 ? 'font-bold' : ''">{{ money(row.balance) }}</td>
                            </tr>
                            <tr class="font-bold bg-secondary-container text-on-secondary-container">
                                <td colspan="2" class="px-4 py-3 text-right">TOTAL ASET</td>
                                <td class="px-4 py-3 text-right">{{ money(report.assets.total) }}</td>
                            </tr>

                            <tr class="font-bold bg-surface-container-lowest"><td colspan="3" class="px-4 py-2 text-secondary">KEWAJIBAN & EKUITAS</td></tr>
                            <tr v-for="row in report.liabilities.rows" :key="row.code" class="hover:bg-surface-container-low/50">
                                <td class="px-4 py-2 font-mono text-xs">{{ row.code }}</td>
                                <td class="px-4 py-2" :class="row.level === 1 ? 'font-bold' : ''">{{ row.name }}</td>
                                <td class="px-4 py-2 text-right" :class="row.level === 1 ? 'font-bold' : ''">{{ money(row.balance) }}</td>
                            </tr>
                            <tr class="font-bold bg-surface-container-low">
                                <td colspan="2" class="px-4 py-2 text-right">TOTAL KEWAJIBAN</td>
                                <td class="px-4 py-2 text-right">{{ money(report.liabilities.total) }}</td>
                            </tr>

                            <tr v-for="row in report.equity.rows" :key="row.code" class="hover:bg-surface-container-low/50">
                                <td class="px-4 py-2 font-mono text-xs">{{ row.code }}</td>
                                <td class="px-4 py-2" :class="row.level === 1 ? 'font-bold' : ''">{{ row.name }}</td>
                                <td class="px-4 py-2 text-right" :class="row.level === 1 ? 'font-bold' : ''">{{ money(row.balance) }}</td>
                            </tr>
                            <tr class="font-bold bg-surface-container-low">
                                <td colspan="2" class="px-4 py-2 text-right">TOTAL EKUITAS</td>
                                <td class="px-4 py-2 text-right">{{ money(report.equity.total) }}</td>
                            </tr>
                            <tr class="font-bold bg-secondary-container text-on-secondary-container">
                                <td colspan="2" class="px-4 py-3 text-right">TOTAL KEWAJIBAN & EKUITAS</td>
                                <td class="px-4 py-3 text-right">{{ money(report.total_liabilities_and_equity) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </ProvinceLayout>
</template>
