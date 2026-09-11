<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppCurrencyInput from '../../Components/AppCurrencyInput.vue';
import AppDatePicker from '../../Components/AppDatePicker.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../composables/useCan';

const { can } = useCan();

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    budget: { type: Object, required: true },
    months: { type: Array, required: true },
    sheet: { type: Object, required: true },
    monthLabels: { type: Object, required: true },
});

const selectedYear = ref(String(props.year));
const syncingYear = ref(false);
const amounts = reactive({});

function hydrateAmounts() {
    Object.keys(amounts).forEach((key) => delete amounts[key]);
    for (const group of props.sheet.groups ?? []) {
        for (const account of group.accounts) {
            amounts[account.row_id] = account.amount > 0 ? account.amount : '';
        }
    }
}

hydrateAmounts();

watch(
    () => [props.year, props.month, props.sheet],
    () => {
        syncingYear.value = true;
        selectedYear.value = String(props.year);
        hydrateAmounts();
        queueMicrotask(() => {
            syncingYear.value = false;
        });
    },
    { deep: true },
);

watch(selectedYear, (value) => {
    if (syncingYear.value) return;
    const year = Number(value);
    if (!Number.isFinite(year) || year < 2000 || year > 2100 || year === props.year) return;
    go(year, props.month);
});

const form = useForm({ amounts: {} });
const editable = computed(() => props.sheet.editable);
const statusTone = computed(() => (props.budget.status === 'approved' ? 'success' : 'warning'));
const statusLabel = computed(() => (props.budget.status === 'approved' ? 'Disetujui' : 'Draft'));

const liveTotals = computed(() => {
    const totals = { revenue: 0, expense: 0, surplus: 0 };
    for (const group of props.sheet.groups ?? []) {
        let total = 0;
        for (const account of group.accounts) {
            const value = Number(amounts[account.row_id] || 0);
            total += Number.isFinite(value) ? value : 0;
        }
        totals[group.type] = total;
    }
    totals.surplus = totals.revenue - totals.expense;
    return totals;
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(value) {
    return money.format(Number(value || 0));
}

function go(year, month) {
    router.get(
        '/budgeting',
        { year, month },
        {
            // Must replace props.year/month/sheet — preserveState keeps stale month.
            preserveState: false,
            preserveScroll: true,
            replace: true,
        },
    );
}

function selectMonth(month) {
    if (month === props.month) return;
    go(props.year, month);
}

function submit() {
    form.amounts = { ...amounts };
    form.put(`/budgeting/${props.year}/${props.month}`, { preserveScroll: true });
}

function copyPrevious() {
    router.post(`/budgeting/${props.year}/${props.month}/copy-previous`, {}, { preserveScroll: true });
}

function approve() {
    router.post(`/budgeting/${props.year}/approve`, {}, { preserveScroll: true });
}

function reopen() {
    router.post(`/budgeting/${props.year}/reopen`, {}, { preserveScroll: true });
}

function monthMeta(month) {
    return props.months.find((item) => item.month === month) || { line_count: 0, revenue: 0, expense: 0, surplus: 0 };
}
</script>

<template>
    <Head title="E-Budgeting" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 pb-28">
            <header class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <h1 class="text-2xl font-bold text-primary">E-Budgeting</h1>
                    <p class="mt-1 text-on-surface-variant">
                        Rencana anggaran per tahun &amp; bulan. Hanya akun pendapatan/beban yang postable.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <AppBadge :tone="statusTone">{{ statusLabel }}</AppBadge>
                    <AppButton
                        v-if="can('budgeting.manage') && budget.status === 'draft'"
                        variant="secondary"
                        icon="verified"
                        size="compact"
                        @click="approve"
                    >
                        Setujui Tahun
                    </AppButton>
                    <AppButton
                        v-else-if="can('budgeting.manage')"
                        variant="secondary"
                        icon="lock_open"
                        size="compact"
                        @click="reopen"
                    >
                        Buka Draft
                    </AppButton>
                </div>
            </header>

            <AppDatePicker v-model="selectedYear" mode="year" label="Tahun" />

            <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-6 xl:grid-cols-12">
                <AppButton
                    v-for="m in 12"
                    :key="m"
                    type="button"
                    :variant="m === month ? 'primary' : 'secondary'"
                    class="!min-h-0 !flex-col !items-start !gap-0 !rounded-xl !px-2 !py-3 !text-left"
                    @click="selectMonth(m)"
                >
                    <span class="text-xs font-bold uppercase tracking-wide opacity-80">{{ monthLabels[m]?.slice(0, 3) }}</span>
                    <span class="mt-1 text-sm font-semibold">
                        {{ monthMeta(m).line_count > 0 ? formatMoney(monthMeta(m).surplus) : '—' }}
                    </span>
                </AppButton>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pendapatan</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(liveTotals.revenue) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Beban</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(liveTotals.expense) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Surplus / Defisit</p>
                    <p class="mt-2 text-2xl font-bold" :class="liveTotals.surplus >= 0 ? 'text-primary' : 'text-error'">
                        {{ formatMoney(liveTotals.surplus) }}
                    </p>
                </AppCard>
            </div>

            <div v-if="!editable" class="rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3 text-sm text-on-surface-variant">
                Anggaran tahun {{ year }} sudah disetujui. Buka draft untuk mengubah.
            </div>

            <AppCard v-for="group in sheet.groups" :key="group.type" :padded="false">
                <div class="flex items-center justify-between border-b border-outline-variant px-6 py-4">
                    <h2 class="text-lg font-bold text-primary">{{ group.label }}</h2>
                    <p class="text-sm font-semibold text-on-surface-variant">
                        Total: {{ formatMoney(liveTotals[group.type]) }}
                    </p>
                </div>

                <div v-if="group.accounts.length === 0" class="px-6 py-8 text-sm text-on-surface-variant">
                    Belum ada akun {{ group.label.toLowerCase() }} postable.
                </div>

                <div v-else class="divide-y divide-outline-variant">
                    <div
                        v-for="account in group.accounts"
                        :key="account.row_id"
                        class="grid gap-3 px-6 py-4 md:grid-cols-[1fr_220px] md:items-center"
                    >
                        <div>
                            <p class="font-semibold text-primary">{{ account.code }} · {{ account.name }}</p>
                            <p class="text-xs text-on-surface-variant">Akun {{ group.label.toLowerCase() }}</p>
                        </div>
                        <AppCurrencyInput
                            v-model="amounts[account.row_id]"
                            :label="`Nominal ${account.code}`"
                            hide-label
                            :readonly="!editable"
                            :min="0"
                        />
                    </div>
                </div>
            </AppCard>
        </div>

        <div
            v-if="editable"
            class="fixed bottom-0 left-0 right-0 z-30 border-t border-outline-variant bg-surface-container-lowest/95 backdrop-blur lg:left-64"
        >
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 sm:px-6 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <div class="min-w-0 text-sm text-on-surface-variant">
                    <span class="font-semibold text-primary">{{ monthLabels[month] }} {{ year }}</span>
                    <span class="mx-2 text-outline">·</span>
                    Surplus {{ formatMoney(liveTotals.surplus) }}
                </div>
                <div v-if="can('budgeting.manage')" class="flex flex-wrap items-center gap-2">
                    <AppButton
                        v-if="sheet.has_previous"
                        variant="secondary"
                        icon="content_copy"
                        size="compact"
                        :disabled="form.processing"
                        @click="copyPrevious"
                    >
                        Salin bulan lalu
                    </AppButton>
                    <AppButton icon="save" :loading="form.processing" @click="submit">
                        Simpan {{ monthLabels[month] }}
                    </AppButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
