<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    identity: { type: Object, required: true },
    year: { type: Number, required: true },
    month: { type: Number, default: null },
    period_label: { type: String, required: true },
    rates: { type: Object, required: true },
    revenue_accounts: { type: Array, required: true },
    expense_total: { type: Number, required: true },
    totals: { type: Object, required: true },
    monthLabels: { type: Object, required: true },
    onlySelected: { type: Boolean, default: false },
    error: { type: String, default: null },
});

const selectedYear = ref(String(props.year));
const selectedMonth = ref(props.month === null ? 'all' : String(props.month));
const syncing = ref(false);
const selected = reactive({});

function hydrateSelection() {
    Object.keys(selected).forEach((key) => delete selected[key]);
    for (const account of props.revenue_accounts) {
        selected[account.row_id] = Boolean(account.selected);
    }
}

hydrateSelection();

watch(
    () => [props.year, props.month, props.revenue_accounts],
    () => {
        syncing.value = true;
        selectedYear.value = String(props.year);
        selectedMonth.value = props.month === null ? 'all' : String(props.month);
        hydrateSelection();
        queueMicrotask(() => {
            syncing.value = false;
        });
    },
    { deep: true },
);

const monthOptions = computed(() =>
    Object.entries(props.monthLabels).map(([value, label]) => ({
        value: String(value),
        label,
    })),
);

const live = computed(() => {
    let revenue = 0;
    for (const account of props.revenue_accounts) {
        if (selected[account.row_id]) revenue += Number(account.amount || 0);
    }
    const expense = Number(props.expense_total || 0);
    const profit = revenue - expense;
    const pphFinal = revenue * (Number(props.rates.final) / 100);
    const pphCorporate = Math.max(profit, 0) * (Number(props.rates.corporate) / 100);

    return {
        revenue,
        expense,
        profit,
        pph_final: pphFinal,
        pph_corporate: pphCorporate,
    };
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(value) {
    return money.format(Math.round(Number(value || 0)));
}

function go(year, month) {
    const params = { year };
    params.month = month === null || month === 'all' ? 'all' : month;
    router.get('/accounting/tax-estimate', params, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    });
}

watch(selectedYear, (value) => {
    if (syncing.value) return;
    const year = Number(value);
    if (!Number.isFinite(year) || year === props.year) return;
    go(year, selectedMonth.value);
});

watch(selectedMonth, (value) => {
    if (syncing.value) return;
    const current = props.month === null ? 'all' : String(props.month);
    if (String(value) === current) return;
    go(props.year, value);
});

function selectAllNonZero() {
    for (const account of props.revenue_accounts) {
        selected[account.row_id] = Number(account.amount) !== 0;
    }
}

function clearSelection() {
    for (const account of props.revenue_accounts) {
        selected[account.row_id] = false;
    }
}

const orgName = computed(() => props.identity.short_name || props.identity.legal_name || '—');
</script>

<template>
    <Head title="Taksiran Pajak" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Taksiran Pajak</h1>
                    <p class="mt-1 text-on-surface-variant">
                        Hitung PPh dari mutasi jurnal posted. Pilih akun pendapatan yang masuk basis pajak.
                    </p>
                </div>
                <AppBadge tone="neutral">{{ period_label }}</AppBadge>
            </header>

            <div v-if="error" class="rounded-xl border border-outline-variant bg-error-container px-4 py-3 text-sm text-on-error-container">
                {{ error }}
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Lembaga</p>
                    <p class="mt-2 text-lg font-bold text-primary">{{ orgName }}</p>
                    <p class="mt-1 text-sm text-on-surface-variant">NPWP: {{ identity.tax_number || 'Belum diisi (Pengaturan)' }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">PPh Final {{ rates.final }}%</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(live.pph_final) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">Dari total pendapatan terpilih</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">PPh Badan {{ rates.corporate }}%</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(live.pph_corporate) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">Dari laba (min. 0)</p>
                </AppCard>
            </div>

            <AppCard>
                <div class="grid gap-4 md:grid-cols-2">
                    <AppDatePicker v-model="selectedYear" mode="year" label="Tahun Pajak" />
                    <SmartSelect
                        v-model="selectedMonth"
                        :options="monthOptions"
                        label="Masa Pajak"
                        placeholder="Pilih masa"
                    />
                </div>
            </AppCard>

            <div class="grid gap-4 md:grid-cols-3">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pendapatan terpilih</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(live.revenue) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Beban (non-pajak)</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(live.expense) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Laba sebelum pajak</p>
                    <p class="mt-2 text-2xl font-bold" :class="live.profit >= 0 ? 'text-primary' : 'text-error'">
                        {{ formatMoney(live.profit) }}
                    </p>
                </AppCard>
            </div>

            <AppCard :padded="false">
                <div class="flex flex-col gap-3 border-b border-outline-variant px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-primary">Akun Pendapatan</h2>
                        <p class="text-sm text-on-surface-variant">Centang akun yang masuk perhitungan PPh.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <AppButton variant="secondary" size="compact" @click="selectAllNonZero">Pilih non-nol</AppButton>
                        <AppButton variant="ghost" size="compact" @click="clearSelection">Kosongkan</AppButton>
                    </div>
                </div>

                <div v-if="revenue_accounts.length === 0" class="px-6 py-8 text-sm text-on-surface-variant">
                    Belum ada akun pendapatan postable.
                </div>

                <div v-else class="divide-y divide-outline-variant">
                    <div
                        v-for="account in revenue_accounts"
                        :key="account.row_id"
                        class="grid gap-3 px-6 py-4 md:grid-cols-[1fr_160px_auto] md:items-center"
                    >
                        <div>
                            <p class="font-semibold text-primary">{{ account.code }} · {{ account.name }}</p>
                            <p class="text-xs text-on-surface-variant">Mutasi periode ini</p>
                        </div>
                        <p class="text-right font-semibold text-primary md:text-left">{{ formatMoney(account.amount) }}</p>
                        <div class="md:justify-self-end">
                            <AppSwitch v-model="selected[account.row_id]" :label="`Sertakan ${account.code}`" />
                        </div>
                    </div>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
