<script setup>
import { Head } from '@inertiajs/vue3';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

defineProps({
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    rows: { type: Array, required: true },
    summary: { type: Object, required: true },
    bridge: { type: Array, required: true },
    monthLabels: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}
</script>

<template>
    <Head title="Perubahan Ekuitas" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan</p>
                <h1 class="mt-1 text-2xl font-bold text-primary">Laporan Perubahan Ekuitas</h1>
                <p class="text-sm text-on-surface-variant">
                    {{ period.period_label }} · opening → laba/mutasi → closing (bukan daftar saldo saja)
                </p>
            </div>

            <AppCard class="p-4">
                <ReportPeriodFilter
                    :year="filters.year"
                    :month="filters.month"
                    base-url="/accounting/reports/equity-change"
                    pdf-url="/accounting/reports/equity-change/pdf"
                    excel-url="/accounting/reports/equity-change/excel"
                />
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard v-for="item in bridge" :key="item.key">
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ item.label }}</p>
                    <p
                        class="mt-2 text-xl font-bold"
                        :class="item.amount < 0 ? 'text-error' : 'text-primary'"
                    >
                        {{ formatMoney(item.amount) }}
                    </p>
                </AppCard>
            </div>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">Rekening</th>
                                <th class="px-3 py-2 text-right">Awal</th>
                                <th class="px-3 py-2 text-right">Mutasi</th>
                                <th class="px-3 py-2 text-right">Akhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="4" class="px-3 py-8 text-center text-on-surface-variant">
                                    Belum ada akun ekuitas postable.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.row_id + row.code"
                                class="border-t border-outline-variant/40"
                            >
                                <td class="px-3 py-2">
                                    <span class="font-medium">{{ row.code }}</span>
                                    <span class="text-on-surface-variant"> · {{ row.name }}</span>
                                    <span v-if="row.is_earnings" class="ml-1 text-[10px] font-bold uppercase text-primary">laba</span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.opening) }}</td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums"
                                    :class="row.movement < 0 ? 'text-error' : ''"
                                >
                                    {{ formatMoney(row.movement) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ formatMoney(row.closing) }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="rows.length > 0">
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2">Total ekuitas</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(summary.opening_total) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(summary.movement_total) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(summary.closing_total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="border-t border-outline-variant/40 px-4 py-3 text-xs text-on-surface-variant">
                    Laba periode = perubahan akun laba berjalan (atau plug net income jika COA tidak punya 3.2.02.01).
                    Mutasi lain = setoran/penyesuaian modal.
                </p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
