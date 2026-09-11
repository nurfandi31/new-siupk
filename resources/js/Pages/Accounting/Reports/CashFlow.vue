<script setup>
import { Head } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

defineProps({
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    cash_accounts: { type: Array, required: true },
    opening_cash: { type: Number, required: true },
    closing_cash: { type: Number, required: true },
    implied_closing: { type: Number, required: true },
    net_change: { type: Number, required: true },
    reconciled: { type: Boolean, required: true },
    sections: { type: Array, required: true },
    monthLabels: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}
</script>

<template>
    <Head title="Arus Kas" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Laporan Arus Kas</h1>
                    <p class="text-sm text-on-surface-variant">
                        {{ period.period_label }} · metode langsung dari jurnal akun kas (1.1.01*)
                    </p>
                </div>
                <AppBadge :tone="reconciled ? 'success' : 'error'">
                    {{ reconciled ? 'Rekonsiliasi OK' : 'Selisih vs saldo kas' }}
                </AppBadge>
            </div>

            <AppCard class="p-4">
                <ReportPeriodFilter
                    :year="filters.year"
                    :month="filters.month"
                    base-url="/accounting/reports/cash-flow"
                    pdf-url="/accounting/reports/cash-flow/pdf"
                    excel-url="/accounting/reports/cash-flow/excel"
                />
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-3">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Saldo kas awal</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(opening_cash) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Perubahan bersih</p>
                    <p
                        class="mt-2 text-2xl font-bold"
                        :class="net_change >= 0 ? 'text-primary' : 'text-error'"
                    >
                        {{ formatMoney(net_change) }}
                    </p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Saldo kas akhir</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(closing_cash) }}</p>
                    <p v-if="!reconciled" class="mt-1 text-xs text-error">
                        Implied {{ formatMoney(implied_closing) }}
                    </p>
                </AppCard>
            </div>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">Uraian</th>
                                <th class="px-3 py-2 text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-outline-variant/40 bg-surface-container-low font-semibold">
                                <td class="px-3 py-2">Saldo kas awal periode</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(opening_cash) }}</td>
                            </tr>

                            <template v-for="section in sections" :key="section.key">
                                <tr class="bg-surface-container-high font-semibold">
                                    <td class="px-3 py-2" colspan="2">{{ section.label }}</td>
                                </tr>
                                <tr v-if="section.lines.length === 0">
                                    <td class="px-3 py-1.5 pl-6 text-on-surface-variant" colspan="2">Tidak ada mutasi</td>
                                </tr>
                                <tr
                                    v-for="(line, idx) in section.lines"
                                    :key="`${section.key}-${idx}`"
                                    class="border-t border-outline-variant/30"
                                >
                                    <td class="px-3 py-1.5 pl-6">
                                        {{ line.label }}
                                        <span v-if="line.count > 1" class="text-xs text-on-surface-variant">
                                            · {{ line.count }} jurnal
                                        </span>
                                    </td>
                                    <td
                                        class="px-3 py-1.5 text-right tabular-nums"
                                        :class="line.amount < 0 ? 'text-error' : ''"
                                    >
                                        {{ formatMoney(line.amount) }}
                                    </td>
                                </tr>
                                <tr class="bg-surface-container-low text-sm font-semibold">
                                    <td class="px-3 py-1.5">Jumlah {{ section.label.toLowerCase() }}</td>
                                    <td
                                        class="px-3 py-1.5 text-right tabular-nums"
                                        :class="section.total < 0 ? 'text-error' : ''"
                                    >
                                        {{ formatMoney(section.total) }}
                                    </td>
                                </tr>
                            </template>

                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2">Kenaikan (penurunan) bersih kas</td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums"
                                    :class="net_change < 0 ? 'text-error' : ''"
                                >
                                    {{ formatMoney(net_change) }}
                                </td>
                            </tr>
                            <tr class="font-semibold">
                                <td class="px-3 py-2">Saldo kas akhir periode</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(closing_cash) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-outline-variant/40 px-4 py-3 text-xs text-on-surface-variant">
                    Klasifikasi dari lawan akun kas di tiap jurnal (operasi / investasi / pendanaan).
                    Sumber pinjaman digabung ke operasi. Bukan salinan tabel mapping legacy.
                    <span v-if="cash_accounts.length">
                        · Kas:
                        <template v-for="(a, i) in cash_accounts" :key="a.row_id">
                            {{ a.code }}<span v-if="i < cash_accounts.length - 1">, </span>
                        </template>
                    </span>
                </p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
