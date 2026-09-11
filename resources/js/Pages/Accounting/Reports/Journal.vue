<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    rows: { type: Array, required: true },
    pagination: { type: Object, required: true },
    totals: { type: Object, required: true },
    page_totals: { type: Object, required: true },
    balanced: { type: Boolean, required: true },
    truncated: { type: Boolean, default: false },
    monthLabels: { type: Object, required: true },
    filters: { type: Object, required: true },
    day: { type: String, default: null },
});

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function goPage(page) {
    router.get(
        '/accounting/reports/journals',
        {
            year: props.filters.year,
            month: props.filters.month,
            day: props.filters.day || undefined,
            page,
        },
        { preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head title="Jurnal Transaksi" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Jurnal Transaksi</h1>
                    <p class="text-sm text-on-surface-variant">{{ period.period_label }}</p>
                </div>
                <AppBadge :tone="balanced ? 'success' : 'error'">
                    {{ balanced ? 'Debit = Kredit' : 'Tidak seimbang' }}
                </AppBadge>
            </div>

            <AppCard class="p-4">
                <ReportPeriodFilter
                    :year="filters.year"
                    :month="filters.month"
                    :day="filters.day || null"
                    show-day
                    base-url="/accounting/reports/journals"
                    pdf-url="/accounting/reports/journals/pdf"
                    excel-url="/accounting/reports/journals/excel"
                />
            </AppCard>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-left text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2">No</th>
                                <th class="px-3 py-2">Tanggal</th>
                                <th class="px-3 py-2">No. Jurnal</th>
                                <th class="px-3 py-2">Kode</th>
                                <th class="px-3 py-2">Keterangan</th>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Kredit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="7" class="px-3 py-8 text-center text-on-surface-variant">Tidak ada jurnal posted pada periode ini.</td>
                            </tr>
                            <tr v-for="row in rows" :key="`${row.entry_row_id}-${row.no}`" class="border-t border-outline-variant/40">
                                <td class="px-3 py-2 tabular-nums">{{ row.no }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ row.date }}</td>
                                <td class="px-3 py-2">{{ row.journal_number }}</td>
                                <td class="px-3 py-2 font-medium">{{ row.account_code }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-on-surface-variant">{{ row.account_name }}</span>
                                    <span v-if="row.description"> — {{ row.description }}</span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ row.debit ? money.format(row.debit) : '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ row.credit ? money.format(row.credit) : '' }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td colspan="5" class="px-3 py-2">Total periode</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.credit) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div v-if="pagination.last_page > 1" class="flex items-center justify-between border-t border-outline-variant/40 px-4 py-3 text-sm">
                    <span class="text-on-surface-variant">
                        Halaman {{ pagination.page }} / {{ pagination.last_page }} · {{ pagination.total }} baris
                    </span>
                    <div class="flex gap-2">
                        <AppButton
                            type="button"
                            variant="secondary"
                            size="compact"
                            icon="chevron_left"
                            :disabled="pagination.page <= 1"
                            @click="goPage(pagination.page - 1)"
                        >
                            Prev
                        </AppButton>
                        <AppButton
                            type="button"
                            variant="secondary"
                            size="compact"
                            class="!flex-row-reverse"
                            icon="chevron_right"
                            :disabled="pagination.page >= pagination.last_page"
                            @click="goPage(pagination.page + 1)"
                        >
                            Next
                        </AppButton>
                    </div>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
