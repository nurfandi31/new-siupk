<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    rows: { type: Array, required: true },
    totals: { type: Object, required: true },
    monthLabels: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));
const syncing = ref(false);

watch(
    () => [props.filters.year, props.filters.month],
    () => {
        syncing.value = true;
        selectedYear.value = String(props.filters.year);
        selectedMonth.value = String(props.filters.month);
        queueMicrotask(() => {
            syncing.value = false;
        });
    },
);

const monthOptions = computed(() =>
    Object.entries(props.monthLabels).map(([value, label]) => ({
        value: String(value),
        label: String(label),
    })),
);

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}

function apply() {
    if (syncing.value) return;
    router.get(
        '/lending/reports/schedule-vs-actual',
        { year: selectedYear.value, month: selectedMonth.value },
        { preserveState: false, preserveScroll: true, replace: true },
    );
}

watch([selectedYear, selectedMonth], () => {
    if (syncing.value) return;
    apply();
});

const pdfHref = computed(() => {
    const q = new URLSearchParams({
        year: selectedYear.value || String(props.year),
        month: selectedMonth.value || String(props.month),
    });
    return `/lending/reports/schedule-vs-actual/pdf?${q.toString()}`;
});
</script>

<template>
    <Head title="Rencana vs Realisasi" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Rencana vs Realisasi</h1>
                    <p class="text-sm text-on-surface-variant">
                        Angsuran jatuh tempo {{ period.period_label }} · due vs paid di jadwal
                    </p>
                </div>
                <a :href="pdfHref" target="_blank" rel="noopener">
                    <AppButton variant="secondary" icon="picture_as_pdf" size="compact">PDF</AppButton>
                </a>
            </div>

            <AppCard class="p-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <AppDatePicker v-model="selectedYear" mode="year" label="Tahun" />
                    <SmartSelect v-model="selectedMonth" :options="monthOptions" label="Bulan" />
                </div>
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Rencana Pokok</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ formatMoney(totals.plan_principal) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Realisasi Pokok</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ formatMoney(totals.actual_principal) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Gap Pokok</p>
                    <p
                        class="mt-2 text-xl font-bold"
                        :class="totals.gap_principal > 0 ? 'text-error' : 'text-primary'"
                    >
                        {{ formatMoney(totals.gap_principal) }}
                    </p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Gap Jasa</p>
                    <p
                        class="mt-2 text-xl font-bold"
                        :class="totals.gap_interest > 0 ? 'text-error' : 'text-primary'"
                    >
                        {{ formatMoney(totals.gap_interest) }}
                    </p>
                </AppCard>
            </div>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">ID</th>
                                <th class="px-3 py-2 text-left">Kelompok</th>
                                <th class="px-3 py-2 text-left">Produk</th>
                                <th class="px-3 py-2 text-right">Rencana Pokok</th>
                                <th class="px-3 py-2 text-right">Realisasi Pokok</th>
                                <th class="px-3 py-2 text-right">Gap Pokok</th>
                                <th class="px-3 py-2 text-right">Rencana Jasa</th>
                                <th class="px-3 py-2 text-right">Realisasi Jasa</th>
                                <th class="px-3 py-2 text-right">Gap Jasa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="9" class="px-3 py-8 text-center text-on-surface-variant">
                                    Tidak ada angsuran jatuh tempo di bulan ini.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.row_id"
                                class="border-t border-outline-variant/40"
                            >
                                <td class="px-3 py-2">
                                    <a
                                        :href="`/lending/loans/${row.row_id}`"
                                        class="font-semibold text-primary hover:underline"
                                    >#{{ row.id }}</a>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ row.group_name }}</div>
                                    <div class="text-[10px] uppercase text-on-surface-variant">{{ row.loan_number }}</div>
                                </td>
                                <td class="px-3 py-2">{{ row.product_code }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.plan_principal) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.actual_principal) }}</td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums font-semibold"
                                    :class="row.gap_principal > 0 ? 'text-error' : ''"
                                >
                                    {{ formatMoney(row.gap_principal) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.plan_interest) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.actual_interest) }}</td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums font-semibold"
                                    :class="row.gap_interest > 0 ? 'text-error' : ''"
                                >
                                    {{ formatMoney(row.gap_interest) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="rows.length > 0">
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2" colspan="3">Jumlah ({{ totals.count }})</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.plan_principal) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.actual_principal) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-error">{{ formatMoney(totals.gap_principal) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.plan_interest) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(totals.actual_interest) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-error">{{ formatMoney(totals.gap_interest) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
