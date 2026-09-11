<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    account: { type: Object, default: null },
    opening: { type: Object, default: null },
    rows: { type: Array, required: true },
    totals: { type: Object, required: true },
    account_options: { type: Array, required: true },
    monthLabels: { type: Object, required: true },
    filters: { type: Object, required: true },
    error: { type: String, default: null },
});

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const selectedAccount = ref(props.filters.account ? String(props.filters.account) : '');

watch(
    () => props.filters.account,
    (v) => {
        selectedAccount.value = v ? String(v) : '';
    },
);

const accountOptions = computed(() =>
    props.account_options.map((a) => ({
        value: String(a.row_id),
        label: a.label,
    })),
);

const extraFilters = computed(() => ({
    account: selectedAccount.value || undefined,
}));

function onAccountChange() {
    router.get(
        '/accounting/reports/general-ledger',
        {
            year: props.filters.year,
            month: props.filters.month,
            day: props.filters.day || undefined,
            account: selectedAccount.value || undefined,
        },
        { preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head title="Buku Besar" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan</p>
                <h1 class="mt-1 text-2xl font-bold text-primary">
                    Buku Besar
                    <span v-if="account" class="text-lg font-semibold text-on-surface-variant">— {{ account.name }}</span>
                </h1>
                <p class="text-sm text-on-surface-variant">
                    {{ period.period_label }}
                    <span v-if="account"> · {{ account.code }}</span>
                </p>
            </div>

            <AppCard class="space-y-4 p-4">
                <ReportPeriodFilter
                    :year="filters.year"
                    :month="filters.month"
                    :day="filters.day || null"
                    show-day
                    :extra="extraFilters"
                    base-url="/accounting/reports/general-ledger"
                    :pdf-url="account ? '/accounting/reports/general-ledger/pdf' : null"
                    :excel-url="account ? '/accounting/reports/general-ledger/excel' : null"
                >
                    <template #extra>
                        <div class="w-full min-w-0 flex-1 lg:max-w-sm">
                            <SmartSelect
                                v-model="selectedAccount"
                                label="Akun"
                                :options="accountOptions"
                                placeholder="Pilih akun postable"
                                searchable
                                @update:model-value="onAccountChange"
                            />
                        </div>
                    </template>
                </ReportPeriodFilter>
                <p v-if="error" class="text-sm text-error">{{ error }}</p>
            </AppCard>

            <AppCard v-if="!account" class="p-8 text-center text-on-surface-variant">
                Pilih akun untuk menampilkan buku besar.
            </AppCard>

            <AppCard v-else class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">No</th>
                                <th class="px-3 py-2 text-left">Tanggal</th>
                                <th class="px-3 py-2 text-left">No. Jurnal</th>
                                <th class="px-3 py-2 text-left">Keterangan</th>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Kredit</th>
                                <th class="px-3 py-2 text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="opening" class="bg-surface-container-low/60">
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2 tabular-nums">{{ opening.year.date }}</td>
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2 font-medium">{{ opening.year.label }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(opening.year.debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(opening.year.credit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ money.format(opening.year.balance) }}</td>
                            </tr>
                            <tr v-if="opening" class="bg-surface-container-low/40">
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2 tabular-nums">{{ opening.prior.date }}</td>
                                <td class="px-3 py-2"></td>
                                <td class="px-3 py-2 font-medium">{{ opening.prior.label }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(opening.prior.debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(opening.prior.credit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ money.format(opening.prior.balance) }}</td>
                            </tr>
                            <tr v-for="row in rows" :key="`${row.entry_row_id}-${row.no}`" class="border-t border-outline-variant/40">
                                <td class="px-3 py-2 tabular-nums">{{ row.no }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ row.date }}</td>
                                <td class="px-3 py-2">{{ row.journal_number }}</td>
                                <td class="px-3 py-2">{{ row.description }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ row.debit ? money.format(row.debit) : '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ row.credit ? money.format(row.credit) : '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-medium">{{ money.format(row.balance) }}</td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="7" class="px-3 py-6 text-center text-on-surface-variant">Tidak ada mutasi pada periode.</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2" colspan="4">{{ totals.period?.label || 'Total Transaksi Periode' }}</td>
                                <!-- gl-footer-v2 -->
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.period?.debit ?? totals.debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.period?.credit ?? totals.credit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums"></td>
                            </tr>
                            <tr class="bg-surface-container-low font-semibold">
                                <td class="px-3 py-2" colspan="4">{{ totals.ytd?.label || 'Total Transaksi s/d Periode' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.ytd?.debit ?? 0) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.ytd?.credit ?? 0) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.ytd?.balance ?? totals.closing_balance) }}</td>
                            </tr>
                            <tr class="bg-surface-container font-bold">
                                <td class="px-3 py-2" colspan="4">{{ totals.cumulative?.label || 'Total Transaksi Kumulatif Tahun' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.cumulative?.debit ?? 0) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.cumulative?.credit ?? 0) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
