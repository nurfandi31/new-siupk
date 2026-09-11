<script setup>
import { Head } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

defineProps({
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    sections: { type: Array, required: true },
    totals: { type: Object, required: true },
    balanced: { type: Boolean, required: true },
    monthLabels: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function fmt(value) {
    if (value < 0) return `(${money.format(Math.abs(value))})`;
    return money.format(value);
}

function sectionTotalLabel(l1) {
    if (l1.account_type === 'asset') return 'Jumlah Aset';
    if (l1.account_type === 'liability') return 'Jumlah Utang';
    if (l1.account_type === 'equity') return 'Jumlah Modal';
    return `Jumlah ${l1.name}`;
}
</script>

<template>
    <Head title="Neraca" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Neraca</h1>
                    <p class="text-sm text-on-surface-variant">{{ period.period_label }} · per {{ period.as_of }}</p>
                </div>
                <AppBadge :tone="balanced ? 'success' : 'error'">
                    {{ balanced ? 'Aset = Liabilitas+Ekuitas' : 'Tidak seimbang' }}
                </AppBadge>
            </div>

            <AppCard class="p-4">
                <ReportPeriodFilter
                    :year="filters.year"
                    :month="filters.month"
                    base-url="/accounting/reports/balance-sheet"
                    pdf-url="/accounting/reports/balance-sheet/pdf"
                    excel-url="/accounting/reports/balance-sheet/excel"
                />
            </AppCard>

            <AppCard class="overflow-hidden p-0">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                        <tr>
                            <th class="px-3 py-2 text-left w-28">Kode</th>
                            <th class="px-3 py-2 text-left">Nama Akun</th>
                            <th class="px-3 py-2 text-right w-40">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="sections.length === 0">
                            <td colspan="3" class="px-3 py-8 text-center text-on-surface-variant">Belum ada data neraca.</td>
                        </tr>
                        <template v-for="l1 in sections" :key="l1.code">
                            <tr class="bg-primary text-on-primary">
                                <td class="px-3 py-2 font-bold" colspan="3">{{ l1.code }}. {{ l1.name }}</td>
                            </tr>
                            <template v-for="l2 in l1.children" :key="l2.code">
                                <tr class="bg-surface-container-high font-semibold">
                                    <td class="px-3 py-1.5">{{ l2.code }}</td>
                                    <td class="px-3 py-1.5" colspan="2">{{ l2.name }}</td>
                                </tr>
                                <tr
                                    v-for="l3 in l2.children"
                                    :key="l3.code"
                                    class="border-t border-outline-variant/30"
                                >
                                    <td class="px-3 py-1.5 pl-6 tabular-nums">{{ l3.code }}</td>
                                    <td class="px-3 py-1.5">{{ l3.name }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums" :class="l3.balance < 0 ? 'text-error' : ''">
                                        {{ fmt(l3.balance) }}
                                    </td>
                                </tr>
                            </template>
                            <tr class="border-t border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2" colspan="2">{{ sectionTotalLabel(l1) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums" :class="l1.balance < 0 ? 'text-error' : ''">
                                    {{ fmt(l1.balance) }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-outline bg-surface-container font-bold">
                            <td class="px-3 py-2" colspan="2">Jumlah Liabilitas + Ekuitas</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ fmt(totals.liabilities_equity) }}</td>
                        </tr>
                    </tfoot>
                </table>
                <p class="border-t border-outline-variant/40 px-4 py-3 text-xs text-on-surface-variant">
                    Laba/Rugi tahun berjalan ({{ '3.2.02.01' }}):
                    <span class="font-semibold text-on-surface">{{ fmt(totals.net_income) }}</span>
                </p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
