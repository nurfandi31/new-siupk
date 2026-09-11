<script setup>
import { useConfirm } from '../../../composables/useConfirm';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import AppFilterPill from '../../../Components/AppFilterPill.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';

const { can } = useCan();

const props = defineProps({
    year: { type: Number, required: true },
    months: { type: Array, required: true },
    open_count: { type: Number, required: true },
    closed_count: { type: Number, required: true },
    draft_journals: { type: Number, required: true },
    next_year: { type: Number, required: true },
    next_year_openings_exist: { type: Boolean, required: true },
    can_close_year: { type: Boolean, required: true },
    trial_balance: { type: Object, required: true },
    net_income: { type: Number, required: true },
    allocation: { type: Object, required: true },
    can_close: { type: Boolean, default: false },
    year_options: { type: Array, required: true },
});

const allowClose = computed(() => props.can_close && can('period_close.manage'));

const selectedYear = ref(String(props.year));
const syncing = ref(false);
const forceRewrite = ref(false);

/** periods | trial_balance | allocate */
const tab = ref('periods');
const tabs = [
    { value: 'periods', label: '1. Periode', short: 'Periode' },
    { value: 'trial_balance', label: '2. Neraca Saldo', short: 'Neraca Saldo' },
    { value: 'allocate', label: '3. Alokasi laba', short: 'Alokasi' },
];

watch(
    () => props.year,
    (y) => {
        syncing.value = true;
        selectedYear.value = String(y);
        queueMicrotask(() => {
            syncing.value = false;
        });
    },
);

watch(selectedYear, (value) => {
    if (syncing.value) return;
    const year = Number(value);
    if (!Number.isFinite(year) || year === props.year) return;
    router.get('/accounting/period-close', { year }, { preserveState: false, replace: true });
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const moneyDecimal = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}
function formatMoneyDecimal(v) {
    return moneyDecimal.format(Number(v || 0));
}

const yearForm = useForm({ year: props.year, force: false });

const { confirm: confirmAction, showAlert } = useConfirm();

async function closeMonth(month) {
    if (!allowClose.value) return;
    if (!await confirmAction({ title: 'Tutup Periode', message: `Tutup periode ${String(month).padStart(2, '0')}/${props.year}? Jurnal draft harus kosong.`, confirmLabel: 'Tutup Periode', variant: 'primary' })) return;
    router.post(`/accounting/period-close/${props.year}/${month}/close`, {}, { preserveScroll: true });
}

async function reopenMonth(month) {
    if (!allowClose.value) return;
    if (!await confirmAction({ title: 'Buka Kembali Periode', message: `Buka kembali periode ${String(month).padStart(2, '0')}/${props.year}?`, confirmLabel: 'Buka Periode', variant: 'primary' })) return;
    router.post(`/accounting/period-close/${props.year}/${month}/reopen`, {}, { preserveScroll: true });
}

async function closeYear() {
    if (!allowClose.value) return;
    const msg =
        props.next_year_openings_exist && !forceRewrite.value
            ? `Saldo awal ${props.next_year} dari tutup buku sudah ada. Aktifkan "Paksa tulis ulang" dulu, atau batalkan.`
            : `Tutup seluruh tahun ${props.year} dan tulis saldo awal ${props.next_year}?`;
    if (props.next_year_openings_exist && !forceRewrite.value) {
        await showAlert({ title: 'Perhatian', message: msg });
        return;
    }
    if (!await confirmAction({ title: 'Tutup Buku Tahun', message: msg, confirmLabel: 'Tutup Buku', variant: 'primary' })) return;
    yearForm.year = props.year;
    yearForm.force = forceRewrite.value;
    yearForm.post('/accounting/period-close/year-close', { preserveScroll: true });
}

const statusTone = { open: 'success', closed: 'neutral', missing: 'warning' };
const statusLabel = { open: 'Terbuka', closed: 'Ditutup', missing: 'Belum ada' };

function emptyCommunity() {
    const o = {};
    for (const line of props.allocation.community_lines || []) o[line.key] = '';
    return o;
}
function emptyVillages() {
    const o = {};
    for (const v of props.allocation.villages || []) o[v.row_id] = '';
    return o;
}

const allocForm = useForm({
    year: props.year,
    date: props.allocation.default_date,
    community: emptyCommunity(),
    villages: emptyVillages(),
    investor: '',
    retained: '',
    note: '',
});

watch(
    () => props.allocation,
    (a) => {
        allocForm.defaults({
            year: props.year,
            date: a.default_date,
            community: emptyCommunity(),
            villages: emptyVillages(),
            investor: '',
            retained: '',
            note: '',
        });
        allocForm.reset();
        allocForm.year = props.year;
        allocForm.date = a.default_date;
    },
);

function n(v) {
    const x = Number(v);
    return Number.isFinite(x) && x > 0 ? x : 0;
}

const communityTotal = computed(() =>
    Object.values(allocForm.community).reduce((s, v) => s + n(v), 0),
);
const villageTotal = computed(() => Object.values(allocForm.villages).reduce((s, v) => s + n(v), 0));
const allocTotal = computed(
    () => communityTotal.value + villageTotal.value + n(allocForm.investor) + n(allocForm.retained),
);
const allocOver = computed(() => allocTotal.value - Number(props.allocation.remaining || 0) > 0.009);

function fillRetained() {
    const rest = Math.max(
        0,
        Math.round(
            Number(props.allocation.remaining || 0) -
                communityTotal.value -
                villageTotal.value -
                n(allocForm.investor),
        ),
    );
    allocForm.retained = rest > 0 ? rest : '';
}

function formatPeriodDate(v) {
    if (!v) return '—';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return v;
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
}

async function submitAllocation() {
    if (!allowClose.value) return;
    if (allocTotal.value <= 0) {
        await showAlert({ title: 'Perhatian', message: 'Isi minimal satu pos alokasi.' });
        return;
    }
    if (allocOver.value) {
        await showAlert({ title: 'Perhatian', message: 'Total alokasi melebihi sisa laba.' });
        return;
    }
    if (!await confirmAction({ title: 'Simpan Alokasi Laba', message: `Simpan alokasi laba ${props.year} total ${formatMoney(allocTotal.value)}?`, confirmLabel: 'Simpan', variant: 'primary' })) return;
    allocForm.year = props.year;
    allocForm.post('/accounting/period-close/allocate', { preserveScroll: true });
}
</script>

<template>
    <Head title="Tutup Buku" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Keuangan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Tutup Buku</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Tutup periode, bawa saldo akun ke tahun depan, lalu alokasi laba — satu langkah per tab.
                    </p>
                </div>
                <div class="w-40">
                    <SmartSelect
                        id="period-close-year"
                        label="Tahun buku"
                        :model-value="Number(selectedYear)"
                        :options="year_options"
                        @update:model-value="(v) => (selectedYear = String(v))"
                    />
                </div>
            </header>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Terbuka</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ open_count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Ditutup</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ closed_count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jurnal draft</p>
                    <p class="mt-2 text-2xl font-bold" :class="draft_journals > 0 ? 'text-error' : 'text-primary'">
                        {{ draft_journals }}
                    </p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Laba/rugi {{ year }}</p>
                    <p class="mt-2 text-2xl font-bold" :class="net_income < 0 ? 'text-error' : 'text-primary'">
                        {{ formatMoney(net_income) }}
                    </p>
                </AppCard>
            </div>

            <!-- Step tabs -->
            <div class="border-b border-outline-variant pb-3">
                <AppFilterPill
                    v-model="tab"
                    :items="tabs"
                    variant="outline"
                    aria-label="Tab langkah tutup buku"
                />
            </div>

            <!-- TAB 1: periods -->
            <AppCard v-show="tab === 'periods'" class="overflow-hidden p-0">
                <div class="border-b border-outline-variant px-4 py-3">
                    <h2 class="text-sm font-bold text-primary">Periode bulanan {{ year }}</h2>
                    <p class="text-xs text-on-surface-variant">
                        Periode tertutup menolak posting jurnal baru.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">Bulan</th>
                                <th class="px-3 py-2 text-left">Rentang</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-right">Draft</th>
                                <th class="px-3 py-2 text-left">Ditutup</th>
                                <th class="px-3 py-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in months" :key="m.month" class="border-t border-outline-variant/40">
                                <td class="px-3 py-2 font-medium">{{ m.label }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-on-surface-variant">
                                    {{ formatPeriodDate(m.starts_at) }} – {{ formatPeriodDate(m.ends_at) }}
                                </td>
                                <td class="px-3 py-2">
                                    <AppBadge :tone="statusTone[m.status] || 'neutral'">
                                        {{ statusLabel[m.status] || m.status }}
                                    </AppBadge>
                                </td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums"
                                    :class="m.draft_journals > 0 ? 'font-semibold text-error' : ''"
                                >
                                    {{ m.draft_journals }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-on-surface-variant">
                                    {{ m.closed_at || '—' }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div v-if="allowClose" class="flex justify-end gap-1">
                                        <AppButton
                                            v-if="m.can_close"
                                            variant="secondary"
                                            size="compact"
                                            @click="closeMonth(m.month)"
                                        >
                                            Tutup
                                        </AppButton>
                                        <AppButton
                                            v-if="m.can_reopen"
                                            variant="ghost"
                                            size="compact"
                                            @click="reopenMonth(m.month)"
                                        >
                                            Buka
                                        </AppButton>
                                    </div>
                                    <span v-else class="text-xs text-on-surface-variant">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- TAB 2: Neraca Saldo -->
            <AppCard v-show="tab === 'trial_balance'" class="overflow-hidden p-0">
                <div class="flex flex-wrap items-start justify-between gap-2 border-b border-outline-variant px-4 py-3">
                    <div>
                        <h2 class="text-sm font-bold text-primary">Neraca Saldo per 31-12-{{ year }}</h2>
                        <p class="text-xs text-on-surface-variant">
                            Saldo akun pada akhir tahun buku — debit/kredit seimbang sebagai syarat tutup tahun.
                        </p>
                    </div>
                    <AppBadge :tone="trial_balance.balanced ? 'success' : 'error'">
                        {{ trial_balance.balanced ? 'Seimbang' : 'Tidak seimbang' }}
                    </AppBadge>
                </div>

                <div class="max-h-[28rem] overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left" rowspan="2">Rekening</th>
                                <th class="px-3 py-2 text-center" colspan="2">Neraca Saldo</th>
                                <th class="px-3 py-2 text-center" colspan="2">Laba Rugi</th>
                                <th class="px-3 py-2 text-center" colspan="2">Neraca</th>
                            </tr>
                            <tr>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Kredit</th>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Kredit</th>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Kredit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="trial_balance.rows.length === 0">
                                <td colspan="7" class="px-3 py-8 text-center text-on-surface-variant">
                                    Belum ada akun dengan saldo.
                                </td>
                            </tr>
                            <tr
                                v-for="row in trial_balance.rows"
                                :key="row.row_id"
                                class="border-t border-outline-variant/40"
                            >
                                <td class="px-3 py-2">
                                    <span class="font-medium">{{ row.code }}</span>
                                    <span class="text-on-surface-variant"> · {{ row.name }}</span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoneyDecimal(row.ns_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoneyDecimal(row.ns_credit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoneyDecimal(row.lr_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoneyDecimal(row.lr_credit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoneyDecimal(row.bs_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoneyDecimal(row.bs_credit) }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="trial_balance.rows.length">
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2">Jumlah</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoneyDecimal(trial_balance.totals.ns_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoneyDecimal(trial_balance.totals.ns_credit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoneyDecimal(trial_balance.totals.lr_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoneyDecimal(trial_balance.totals.lr_credit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoneyDecimal(trial_balance.totals.bs_debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoneyDecimal(trial_balance.totals.bs_credit) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p class="border-t border-outline-variant/40 px-4 py-2 text-xs text-on-surface-variant">
                    Laba/Rugi berjalan: <span class="font-semibold text-on-surface">{{ formatMoneyDecimal(trial_balance.net_income) }}</span>
                </p>

                <div
                    v-if="next_year_openings_exist"
                    class="border-t border-outline-variant bg-surface-container-low/50 px-4 py-2 text-sm text-on-surface-variant"
                >
                    Saldo awal {{ next_year }} dari tutup buku sudah ada.
                </div>

                <div class="space-y-3 border-t border-outline-variant px-4 py-3">
                    <AppSwitch
                        v-if="allowClose && next_year_openings_exist"
                        v-model="forceRewrite"
                        label="Paksa tulis ulang"
                        description="Timpa saldo awal tahun berikutnya yang sudah ada"
                    />
                    <div v-if="allowClose" class="flex justify-end">
                        <AppButton
                            variant="primary"
                            size="compact"
                            :loading="yearForm.processing"
                            :disabled="!can_close_year || yearForm.processing || !trial_balance.balanced"
                            @click="closeYear"
                        >
                            Tutup tahun {{ year }}
                        </AppButton>
                    </div>
                    <p v-if="allowClose && !trial_balance.balanced" class="text-xs text-error">
                        NS belum seimbang. Perbaiki jurnal sebelum menutup tahun.
                    </p>
                </div>
            </AppCard>

            <!-- TAB 3: allocate -->
            <AppCard v-show="tab === 'allocate'" class="overflow-hidden p-0">
                <div class="border-b border-outline-variant px-4 py-3">
                    <h2 class="text-sm font-bold text-primary">Alokasi laba tahun {{ year }}</h2>
                    <p class="text-xs text-on-surface-variant">
                        Dr {{ allocation.accounts?.earnings?.code || '3.2.02.01' }} → Cr utang laba / laba ditahan.
                        Tanggal biasanya 1 Jan tahun berikutnya (periode harus terbuka).
                    </p>
                </div>

                <div v-if="allocation.error" class="px-4 py-6 text-sm text-error">{{ allocation.error }}</div>

                <template v-else>
                    <div class="grid gap-3 border-b border-outline-variant p-4 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-on-surface-variant">Laba tersedia</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(allocation.available) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-on-surface-variant">Sudah dialokasi</p>
                            <p class="mt-1 text-xl font-bold">{{ formatMoney(allocation.already_allocated) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-on-surface-variant">Sisa</p>
                            <p class="mt-1 text-xl font-bold" :class="allocation.remaining > 0 ? 'text-primary' : ''">
                                {{ formatMoney(allocation.remaining) }}
                            </p>
                        </div>
                    </div>

                    <div v-if="allocation.existing?.length" class="border-b border-outline-variant px-4 py-3">
                        <p class="mb-2 text-xs font-bold uppercase text-on-surface-variant">Jurnal alokasi sebelumnya</p>
                        <ul class="space-y-1 text-sm">
                            <li v-for="e in allocation.existing" :key="e.row_id">
                                <Link :href="e.href" class="font-semibold text-primary hover:underline">
                                    #{{ e.id }}
                                </Link>
                                <span class="text-on-surface-variant">
                                    · {{ e.transaction_date }} · {{ e.description }}
                                </span>
                            </li>
                        </ul>
                    </div>

                    <form
                        v-if="allowClose && allocation.remaining > 0"
                        class="space-y-6 p-4 sm:p-5"
                        @submit.prevent="submitAllocation"
                    >
                        <div class="grid gap-4 sm:grid-cols-2">
                            <AppDatePicker v-model="allocForm.date" mode="day" label="Tanggal alokasi" />
                            <AppInput
                                v-model="allocForm.note"
                                label="Keterangan (opsional)"
                                placeholder="Alokasi laba …"
                            />
                        </div>

                        <section class="rounded-2xl border border-outline-variant/70 bg-surface-container-low/30 p-4">
                            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                                <div>
                                    <p class="font-mono text-[11px] font-semibold tracking-wide text-on-surface-variant">
                                        {{ allocation.accounts.community.code }}
                                    </p>
                                    <h3 class="text-sm font-bold text-primary">Bagian masyarakat</h3>
                                </div>
                                <p class="text-sm tabular-nums text-on-surface-variant">
                                    Î£ <span class="font-semibold text-primary">{{ formatMoney(communityTotal) }}</span>
                                </p>
                            </div>
                            <div class="divide-y divide-outline-variant/50">
                                <div
                                    v-for="line in allocation.community_lines"
                                    :key="line.key"
                                    class="grid gap-3 py-3 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,1fr)_16rem] sm:items-center"
                                >
                                    <p class="text-sm leading-snug text-on-surface">{{ line.label }}</p>
                                    <AppCurrencyInput
                                        v-model="allocForm.community[line.key]"
                                        :label="`Jumlah ${line.label}`"
                                        hide-label
                                        :min="0"
                                        :step="1000"
                                        placeholder="0"
                                    />
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="allocation.villages?.length"
                            class="rounded-2xl border border-outline-variant/70 bg-surface-container-low/30 p-4"
                        >
                            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                                <div>
                                    <p class="font-mono text-[11px] font-semibold tracking-wide text-on-surface-variant">
                                        {{ allocation.accounts.village.code }}
                                    </p>
                                    <h3 class="text-sm font-bold text-primary">Bagian desa</h3>
                                </div>
                                <p class="text-sm tabular-nums text-on-surface-variant">
                                    Î£ <span class="font-semibold text-primary">{{ formatMoney(villageTotal) }}</span>
                                </p>
                            </div>
                            <div class="max-h-80 divide-y divide-outline-variant/50 overflow-y-auto pr-1">
                                <div
                                    v-for="v in allocation.villages"
                                    :key="v.row_id"
                                    class="grid gap-3 py-3 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,1fr)_16rem] sm:items-center"
                                >
                                    <p class="text-sm font-medium text-on-surface">{{ v.name }}</p>
                                    <AppCurrencyInput
                                        v-model="allocForm.villages[v.row_id]"
                                        :label="`Jumlah ${v.name}`"
                                        hide-label
                                        :min="0"
                                        :step="1000"
                                        placeholder="0"
                                    />
                                </div>
                            </div>
                        </section>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <section class="rounded-2xl border border-outline-variant/70 bg-surface-container-low/30 p-4">
                                <p class="font-mono text-[11px] font-semibold tracking-wide text-on-surface-variant">
                                    {{ allocation.accounts.investor.code }}
                                </p>
                                <h3 class="mb-3 text-sm font-bold text-primary">Penyerta modal</h3>
                                <AppCurrencyInput
                                    v-model="allocForm.investor"
                                    label="Jumlah penyerta modal"
                                    hide-label
                                    :min="0"
                                    :step="1000"
                                    placeholder="0"
                                />
                            </section>
                            <section class="rounded-2xl border border-outline-variant/70 bg-surface-container-low/30 p-4">
                                <div class="mb-3 flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-mono text-[11px] font-semibold tracking-wide text-on-surface-variant">
                                            {{ allocation.accounts.retained.code }}
                                        </p>
                                        <h3 class="text-sm font-bold text-primary">Laba ditahan</h3>
                                    </div>
                                    <AppButton
                                        type="button"
                                        variant="ghost"
                                        size="compact"
                                        class="!min-h-0 !px-2 !text-xs"
                                        @click="fillRetained"
                                    >
                                        Isi sisa
                                    </AppButton>
                                </div>
                                <AppCurrencyInput
                                    v-model="allocForm.retained"
                                    label="Jumlah laba ditahan"
                                    hide-label
                                    :min="0"
                                    :step="1000"
                                    placeholder="0"
                                />
                            </section>
                        </div>

                        <div
                            class="flex flex-col gap-3 rounded-2xl border border-outline-variant bg-surface-container-low/40 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="text-sm">
                                <p class="text-on-surface-variant">
                                    Total alokasi
                                    <span class="ml-1 text-lg font-bold tabular-nums text-primary">
                                        {{ formatMoney(allocTotal) }}
                                    </span>
                                </p>
                                <p
                                    class="mt-0.5 text-xs"
                                    :class="allocOver ? 'font-semibold text-error' : 'text-on-surface-variant'"
                                >
                                    <template v-if="allocOver">
                                        Melebihi sisa {{ formatMoney(allocation.remaining) }}
                                    </template>
                                    <template v-else>
                                        Sisa setelah alokasi
                                        {{ formatMoney(Math.max(0, Number(allocation.remaining || 0) - allocTotal)) }}
                                    </template>
                                </p>
                            </div>
                            <AppButton
                                type="submit"
                                variant="primary"
                                :loading="allocForm.processing"
                                :disabled="allocForm.processing || allocTotal <= 0 || allocOver"
                            >
                                Simpan alokasi
                            </AppButton>
                        </div>
                    </form>

                    <p
                        v-else-if="allowClose && allocation.remaining <= 0"
                        class="px-4 py-6 text-sm text-on-surface-variant"
                    >
                        Tidak ada sisa laba untuk dialokasi
                        <span v-if="allocation.already_allocated > 0">
                            (sudah {{ formatMoney(allocation.already_allocated) }}).
                        </span>
                        <span v-else-if="allocation.available <= 0">(laba/rugi ≤ 0).</span>
                    </p>
                </template>

                </AppCard>
        </div>
    </AuthenticatedLayout>
</template>

