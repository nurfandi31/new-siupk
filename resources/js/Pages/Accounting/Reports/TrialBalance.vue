<script setup>
import { Head } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

defineProps({
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    rows: { type: Array, required: true },
    totals: { type: Object, required: true },
    net_income: { type: Number, required: true },
    balanced: { type: Boolean, required: true },
    monthLabels: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Neraca Saldo" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Neraca Saldo</h1>
                    <p class="text-sm text-on-surface-variant">{{ period.period_label }} · per {{ period.as_of }}</p>
                </div>
                <AppBadge :tone="balanced ? 'success' : 'error'">
                    {{ balanced ? 'Seimbang' : 'Tidak seimbang' }}
                </AppBadge>
            </div>

            <AppCard class="p-4">
                <ReportPeriodFilter
                    :year="filters.year"
                    :month="filters.month"
                    base-url="/accounting/reports/trial-balance"
                    pdf-url="/accounting/reports/trial-balance/pdf"
                    excel-url="/accounting/reports/trial-balance/excel"
                />
            </AppCard>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left" rowspan="2">Rekening</th>
                                <th class="px-3 py-2 text-center" colspan="2">Neraca Saldo</th>
                                <th class="px-3 py-2 text-center" colspan="2">Laba Rugi</th>
                                <th class="px-3 py-2 text-center" colspan="2">Neraca</th>
                            </tr>
                            <tr>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Kredit</th>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Kredit</th>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Kredit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="7" class="px-3 py-8 text-center text-on-surface-variant">Belum ada mutasi.</td>
                            </tr>
                            <tr v-for="row in rows" :key="row.row_id" class="border-t border-outline-variant/40">
                                <td class="px-3 py-2">
                                    <span class="font-medium">{{ row.code }}</span>
                                    <span class="text-on-surface-variant"> · {{ row.name }}</span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(row.ns_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(row.ns_credit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(row.lr_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(row.lr_credit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(row.bs_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(row.bs_credit) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2">Jumlah</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.ns_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.ns_credit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.lr_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.lr_credit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.bs_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.bs_credit) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="border-t border-outline-variant/40 px-4 py-3 text-xs text-on-surface-variant">
                    Laba/Rugi berjalan: <span class="font-semibold text-on-surface">{{ money.format(net_income) }}</span>
                    (plug ke footer agar kolom seimbang, seperti legacy)
                </p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
