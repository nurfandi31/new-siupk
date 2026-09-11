<script setup>
import { Head } from '@inertiajs/vue3';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

defineProps({
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    header_lalu: { type: String, required: true },
    header_sekarang: { type: String, required: true },
    groups: { type: Array, required: true },
    summary: { type: Object, required: true },
    monthLabels: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Laba Rugi" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan</p>
                <h1 class="mt-1 text-2xl font-bold text-primary">Laporan Laba Rugi</h1>
                <p class="text-sm text-on-surface-variant">{{ period.period_label }}</p>
            </div>

            <AppCard class="p-4">
                <ReportPeriodFilter
                    :year="filters.year"
                    :month="filters.month"
                    base-url="/accounting/reports/income-statement"
                    pdf-url="/accounting/reports/income-statement/pdf"
                    excel-url="/accounting/reports/income-statement/excel"
                />
            </AppCard>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">Rekening</th>
                                <th class="px-3 py-2 text-right">s.d. {{ header_lalu }}</th>
                                <th class="px-3 py-2 text-right">{{ header_sekarang }}</th>
                                <th class="px-3 py-2 text-right">s.d. {{ header_sekarang }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="groups.length === 0">
                                <td colspan="4" class="px-3 py-8 text-center text-on-surface-variant">Belum ada pendapatan/beban.</td>
                            </tr>
                            <template v-for="group in groups" :key="group.code">
                                <tr class="bg-surface-container-high font-semibold">
                                    <td class="px-3 py-2" colspan="4">{{ group.code }}. {{ group.name }}</td>
                                </tr>
                                <tr
                                    v-for="row in group.children"
                                    :key="row.row_id"
                                    class="border-t border-outline-variant/30"
                                >
                                    <td class="px-3 py-1.5 pl-6">
                                        <span class="font-medium">{{ row.code }}</span>
                                        <span class="text-on-surface-variant"> · {{ row.name }}</span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(row.prior) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(row.current) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(row.ytd) }}</td>
                                </tr>
                                <tr class="bg-surface-container-low text-sm font-semibold">
                                    <td class="px-3 py-1.5">Jumlah {{ group.name }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(group.prior) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(group.current) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(group.ytd) }}</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="font-semibold">
                            <tr class="border-t-2 border-outline bg-surface-container">
                                <td class="px-3 py-2">Laba (Rugi) Operasional (A)</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.operating.prior) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.operating.current) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.operating.ytd) }}</td>
                            </tr>
                            <tr class="bg-surface-container">
                                <td class="px-3 py-2">Laba (Rugi) Non Operasional (B)</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.non_operating.prior) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.non_operating.current) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.non_operating.ytd) }}</td>
                            </tr>
                            <tr class="bg-surface-container">
                                <td class="px-3 py-2">Laba (Rugi) Sebelum Pajak (A+B)</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.before_tax.prior) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.before_tax.current) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.before_tax.ytd) }}</td>
                            </tr>
                            <tr class="bg-surface-container">
                                <td class="px-3 py-2">Beban Pajak</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.tax.prior) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.tax.current) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.tax.ytd) }}</td>
                            </tr>
                            <tr class="bg-primary/10 text-primary">
                                <td class="px-3 py-2">Laba (Rugi) Bersih</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.after_tax.prior) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.after_tax.current) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.after_tax.ytd) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
