<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface Section {
    code: string;
    name: string;
    ojk_label: string;
    letter: string;
    account_type: string;
    balance: number;
    children: Array<{
        code: string;
        name: string;
        level: number;
        children: Array<{
            code: string;
            name: string;
            level: number;
            balance: number;
        }>;
    }>;
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
    sections: Section[];
    totals: {
        assets: number;
        liabilities_equity: number;
        net_income: number;
        liabilities: number;
    };
    kas_dan_setara_kas: number;
    liabilitas_lancar: number;
    rasio_likuiditas: number | null;
    rasio_solvabilitas: number | null;
    net_income: number;
    balanced: boolean;
    monthLabels: Record<string, string>;
    filters: { year: number; month: number | string };
}>();

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function fmt(value: number): string {
    if (value < 0) return `(${money.format(Math.abs(value))})`;
    return money.format(value);
}

function sectionLabel(s: Section): string {
    if (s.account_type === 'asset') return `Jumlah ${s.ojk_label}`;
    if (s.account_type === 'liability') return `Jumlah ${s.ojk_label}`;
    if (s.account_type === 'equity') return `Jumlah ${s.ojk_label}`;
    return `Jumlah ${s.name}`;
}

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        year: String(props.filters.year),
        month: props.filters.month === null || props.filters.month === undefined
            ? 'all'
            : String(props.filters.month),
    });
    return `/regulatory/ojk/balance-sheet/pdf?${q.toString()}`;
});
</script>

<template>
    <Head title="Neraca OJK" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Laporan OJK · Neraca
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Laporan Posisi Keuangan (Neraca)</h1>
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
                    base-url="/regulatory/ojk/balance-sheet"
                    :pdf-url="pdfUrl"
                />
            </AppCard>

            <AppCard class="overflow-hidden p-0">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                        <tr>
                            <th class="px-3 py-2 text-left w-12">{{ '{Huruf}' }}</th>
                            <th class="px-3 py-2 text-left w-28">Kode</th>
                            <th class="px-3 py-2 text-left">Nama Akun</th>
                            <th class="px-3 py-2 text-right w-44">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="sections.length === 0">
                            <td colspan="4" class="px-3 py-8 text-center text-on-surface-variant">
                                Belum ada data neraca.
                            </td>
                        </tr>
                        <template v-for="l1 in sections" :key="l1.code">
                            <tr class="bg-primary text-on-primary">
                                <td class="px-3 py-2 text-center font-bold">{{ l1.letter }}</td>
                                <td class="px-3 py-2 font-bold">{{ l1.code }}</td>
                                <td class="px-3 py-2 font-bold" colspan="2">{{ l1.ojk_label }} — {{ l1.name }}</td>
                            </tr>
                            <template v-for="l2 in l1.children" :key="l2.code">
                                <tr class="bg-surface-container-high font-semibold">
                                    <td class="px-3 py-1.5"></td>
                                    <td class="px-3 py-1.5">{{ l2.code }}</td>
                                    <td class="px-3 py-1.5" colspan="2">{{ l2.name }}</td>
                                </tr>
                                <tr
                                    v-for="l3 in l2.children"
                                    :key="l3.code"
                                    class="border-t border-outline-variant/30"
                                >
                                    <td class="px-3 py-1.5"></td>
                                    <td class="px-3 py-1.5 pl-6 tabular-nums">{{ l3.code }}</td>
                                    <td class="px-3 py-1.5">{{ l3.name }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums" :class="l3.balance < 0 ? 'text-error' : ''">
                                        {{ fmt(l3.balance) }}
                                    </td>
                                </tr>
                            </template>
                            <tr class="border-t border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2 text-center">{{ l1.letter }}</td>
                                <td class="px-3 py-2" colspan="2">{{ sectionLabel(l1) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums" :class="l1.balance < 0 ? 'text-error' : ''">
                                    {{ fmt(l1.balance) }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-outline bg-surface-container font-bold">
                            <td class="px-3 py-2" colspan="3">Jumlah Liabilitas + Ekuitas</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ fmt(totals.liabilities_equity) }}</td>
                        </tr>
                    </tfoot>
                </table>
                <p class="border-t border-outline-variant/40 px-4 py-3 text-xs text-on-surface-variant">
                    Laba/Rugi tahun berjalan ({{ '3.2.02.01' }}):
                    <span class="font-semibold text-on-surface">{{ fmt(net_income) }}</span>
                </p>
            </AppCard>

            <!-- Rasio OJK -->
            <AppCard class="p-5">
                <h3 class="mb-3 border-b border-outline-variant pb-2 text-base font-bold uppercase tracking-wide text-primary">
                    Rasio Keuangan (Standar OJK)
                </h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border border-outline-variant/40 p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-primary">Rasio Likuiditas</p>
                        <p class="mt-1 text-xs text-on-surface-variant">Kas dan Setara Kas / Liabilitas Lancar</p>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-on-surface-variant">Kas & Setara Kas</p>
                                <p class="font-semibold tabular-nums">{{ fmt(kas_dan_setara_kas) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-on-surface-variant">Liabilitas Lancar</p>
                                <p class="font-semibold tabular-nums">{{ fmt(liabilitas_lancar) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-on-surface-variant">Rasio</p>
                                <p class="font-bold text-primary tabular-nums">
                                    {{ rasio_likuiditas !== null ? rasio_likuiditas.toFixed(2) + '%' : '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-outline-variant/40 p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-primary">Rasio Solvabilitas</p>
                        <p class="mt-1 text-xs text-on-surface-variant">Total Aset / Total Liabilitas</p>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-on-surface-variant">Total Aset</p>
                                <p class="font-semibold tabular-nums">{{ fmt(totals.assets) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-on-surface-variant">Total Liabilitas</p>
                                <p class="font-semibold tabular-nums">{{ fmt(totals.liabilities) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-on-surface-variant">Rasio</p>
                                <p class="font-bold text-primary tabular-nums">
                                    {{ rasio_solvabilitas !== null ? rasio_solvabilitas.toFixed(2) + '%' : '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </AppCard>

            <div class="flex justify-end">
                <a :href="pdfUrl" target="_blank" class="inline-flex">
                    <AppButton variant="outline">
                        <span class="material-symbols-outlined mr-1.5 text-base">picture_as_pdf</span>
                        Cetak PDF
                    </AppButton>
                </a>
            </div>
        </div>
    </AuthenticatedLayout>
</template>