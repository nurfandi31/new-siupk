<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface GroupRow {
    row_id: number;
    code: string;
    name: string;
    prior: number;
    current: number;
    ytd: number;
}

interface Group {
    code: string;
    name: string;
    account_type: string;
    bucket: string;
    ojk_letter: string;
    children: GroupRow[];
    prior: number;
    current: number;
    ytd: number;
}

const props = defineProps<{
    period: {
        year: number;
        month: number | null;
        as_of: string;
        period_label: string;
        is_monthly: boolean;
    };
    identity: { legal_name: string; short_name: string | null };
    header_lalu: string;
    header_sekarang: string;
    groups: Group[];
    summary: {
        operating: { prior: number; current: number; ytd: number };
        non_operating: { prior: number; current: number; ytd: number };
        before_tax: { prior: number; current: number; ytd: number };
        tax: { prior: number; current: number; ytd: number };
        after_tax: { prior: number; current: number; ytd: number };
    };
    monthLabels: Record<string, string>;
    filters: { year: number; month: number | string };
}>();

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        year: String(props.filters.year),
        month: props.filters.month === null || props.filters.month === undefined
            ? 'all'
            : String(props.filters.month),
    });
    return `/regulatory/ojk/income-statement/pdf?${q.toString()}`;
});
</script>

<template>
    <Head title="Laba Rugi OJK" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Laporan OJK · Laba Rugi
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Laporan Laba Rugi</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ period.period_label }}</p>
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

            <AppCard class="p-4">
                <ReportPeriodFilter
                    :year="filters.year"
                    :month="filters.month"
                    base-url="/regulatory/ojk/income-statement"
                    :pdf-url="pdfUrl"
                />
            </AppCard>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left w-12">Huruf</th>
                                <th class="px-3 py-2 text-left">Rekening</th>
                                <th class="px-3 py-2 text-right">s.d. {{ header_lalu }}</th>
                                <th class="px-3 py-2 text-right">{{ header_sekarang }}</th>
                                <th class="px-3 py-2 text-right">s.d. {{ header_sekarang }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="groups.length === 0">
                                <td colspan="5" class="px-3 py-8 text-center text-on-surface-variant">
                                    Belum ada pendapatan/beban.
                                </td>
                            </tr>
                            <template v-for="group in groups" :key="group.code">
                                <tr class="bg-surface-container-high font-semibold">
                                    <td class="px-3 py-2 text-center">{{ group.ojk_letter }}</td>
                                    <td class="px-3 py-2" colspan="4">{{ group.code }}. {{ group.name }}</td>
                                </tr>
                                <tr
                                    v-for="row in group.children"
                                    :key="row.row_id"
                                    class="border-t border-outline-variant/30"
                                >
                                    <td class="px-3 py-1.5"></td>
                                    <td class="px-3 py-1.5 pl-6">
                                        <span class="font-medium">{{ row.code }}</span>
                                        <span class="text-on-surface-variant"> · {{ row.name }}</span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(row.prior) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(row.current) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(row.ytd) }}</td>
                                </tr>
                                <tr class="bg-surface-container-low text-sm font-semibold">
                                    <td class="px-3 py-1.5 text-center">{{ group.ojk_letter }}</td>
                                    <td class="px-3 py-1.5">Jumlah {{ group.name }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(group.prior) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(group.current) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(group.ytd) }}</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="font-semibold">
                            <tr class="border-t-2 border-outline bg-surface-container">
                                <td class="px-3 py-2 text-center">A</td>
                                <td class="px-3 py-2">A. Laba (Rugi) Operasional</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.operating.prior) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.operating.current) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.operating.ytd) }}</td>
                            </tr>
                            <tr class="bg-surface-container">
                                <td class="px-3 py-2 text-center">B</td>
                                <td class="px-3 py-2">B. Laba (Rugi) Non Operasional</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.non_operating.prior) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.non_operating.current) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.non_operating.ytd) }}</td>
                            </tr>
                            <tr class="bg-surface-container">
                                <td class="px-3 py-2 text-center">C</td>
                                <td class="px-3 py-2">C. Laba (Rugi) Sebelum Pajak (A+B)</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.before_tax.prior) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.before_tax.current) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.before_tax.ytd) }}</td>
                            </tr>
                            <tr class="bg-surface-container">
                                <td class="px-3 py-2 text-center">D</td>
                                <td class="px-3 py-2">D. Beban Pajak</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.tax.prior) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.tax.current) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.tax.ytd) }}</td>
                            </tr>
                            <tr class="bg-primary/10 text-primary">
                                <td class="px-3 py-2 text-center">E</td>
                                <td class="px-3 py-2">E. Laba (Rugi) Bersih (C-D)</td>
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