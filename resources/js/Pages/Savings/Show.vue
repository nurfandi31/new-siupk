<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppCurrencyInput from '../../Components/AppCurrencyInput.vue';
import AppDatePicker from '../../Components/AppDatePicker.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppIconButton from '../../Components/AppIconButton.vue';
import AppInput from '../../Components/AppInput.vue';
import AppModal from '../../Components/AppModal.vue';
import AppTextarea from '../../Components/AppTextarea.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../composables/useCan';
import { useConfirm } from '../../composables/useConfirm';
import { useMoney } from '../../composables/useMoney';
import { useI18n } from '../../i18n';

const { t } = useI18n();
const { can } = useCan();
const { money } = useMoney();
const { confirm } = useConfirm();

const props = defineProps({
    account: { type: Object, required: true },
    transactions: { type: Object, required: true },
    statement: { type: Object, default: null },
    filters: { type: Object, default: () => ({ year: 0, month: 0 }) },
    settings: { type: Object, default: () => ({}) },
});

const today = new Date().toLocaleDateString('en-CA');
const now = new Date();

const txOpen = ref(false);
const editOpen = ref(false);
const documentModalOpen = ref(false);
const activeDocumentKey = ref('kop');
const documentFrame = ref(null);

const year = ref(props.filters.year ?? now.getFullYear());
const month = ref(props.filters.month ?? now.getMonth() + 1);

const monthOptions = [
    { value: 0, label: t('Sepanjang tahun') },
    { value: 1, label: t('Januari') },
    { value: 2, label: t('Februari') },
    { value: 3, label: t('Maret') },
    { value: 4, label: t('April') },
    { value: 5, label: t('Mei') },
    { value: 6, label: t('Juni') },
    { value: 7, label: t('Juli') },
    { value: 8, label: t('Agustus') },
    { value: 9, label: t('September') },
    { value: 10, label: t('Oktober') },
    { value: 11, label: t('November') },
    { value: 12, label: t('Desember') },
];

const yearOptions = [
    { value: 0, label: t('Semua tahun') },
    ...Array.from({ length: 10 }, (_, index) => {
        const value = now.getFullYear() - index;

        return { value, label: String(value) };
    }),
];

const periodLabel = computed(() => {
    if (Number(year.value) === 0) return t('Semua tahun');

    if (Number(month.value) === 0) return String(year.value);

    const selectedMonth = monthOptions.find((option) => Number(option.value) === Number(month.value));

    return `${selectedMonth?.label ?? ''} ${year.value}`.trim();
});

const documentItems = computed(() => {
    const reportParams = new URLSearchParams({
        year: String(year.value),
        month: String(month.value),
    });
    const reportQuery = reportParams.toString();

    return [
        {
            key: 'kop',
            label: t('KOP Buku Simpanan'),
            description: t('Sampul buku simpanan anggota'),
            icon: 'id_card',
            url: `/simpanan/${props.account.row_id}/cetak/kop?preview=1`,
            pdfUrl: `/simpanan/${props.account.row_id}/cetak/kop/pdf`,
            excelUrl: `/simpanan/${props.account.row_id}/cetak/kop/excel`,
        },
        {
            key: 'formulir',
            label: t('Formulir Pembukaan Rekening'),
            description: t('Formulir data anggota dan rekening'),
            icon: 'description',
            url: `/simpanan/${props.account.row_id}/cetak/formulir?preview=1`,
            pdfUrl: `/simpanan/${props.account.row_id}/cetak/formulir/pdf`,
            excelUrl: `/simpanan/${props.account.row_id}/cetak/formulir/excel`,
        },
        {
            key: 'koran',
            label: t('Rekening Koran'),
            description: periodLabel.value,
            icon: 'receipt_long',
            url: `/simpanan/${props.account.row_id}/cetak/koran?${reportQuery}&preview=1`,
            pdfUrl: `/simpanan/${props.account.row_id}/cetak/koran/pdf?${reportQuery}`,
            excelUrl: `/simpanan/${props.account.row_id}/cetak/koran/excel?${reportQuery}`,
        },
    ];
});

const activeDocument = computed(() => documentItems.value.find((doc) => doc.key === activeDocumentKey.value) ?? documentItems.value[0]);

const txForm = useForm({
    type: 'setoran',
    amount: 0,
    transaction_date: today,
    reference: '',
    description: '',
});

const editForm = useForm({
    interest_rate: props.account.interest_rate,
    tax_rate: props.account.tax_rate,
    admin_fee: props.account.admin_fee,
    minimum_closing_date: props.account.minimum_closing_date ?? '',
    notes: props.account.notes ?? '',
});

const isActive = computed(() => props.account.status === 'active');
const canManage = computed(() => can('simpanan.manage'));
const hasTransactions = computed(() => props.account.stats.transaction_count > 0);
const minimumWithdrawal = computed(() => Number(props.account.product.minimum_balance || 0));
const memberInitials = computed(() => {
    const parts = String(props.account.member.name || '').trim().split(/\s+/).filter(Boolean);

    return parts.slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase() || '—';
});

const showOpeningRow = computed(() => Number(props.statement?.year ?? 0) > 0);

const openingLabel = computed(() => {
    if (!props.statement || !showOpeningRow.value) return '';

    return Number(props.statement.month) > 0
        ? `${t('Saldo Sebelum')} ${props.statement.period_label}`
        : `${t('Saldo Sebelum')} ${props.statement.year}`;
});

const methodLabel = computed(() => {
    if (Number(props.settings.interest_method) === 1) return t('Saldo terakhir');
    if (Number(props.settings.interest_method) === 2) return t('Saldo terendah');

    return t('Saldo rata-rata');
});

function openTransaction(type) {
    txForm.clearErrors();
    txForm.type = type;
    txForm.amount = 0;
    txForm.transaction_date = today;
    txForm.reference = '';
    txForm.description = type === 'setoran'
        ? `Setor Tunai Rekening ${props.account.account_number}`
        : `Tarik Tunai Rekening ${props.account.account_number}`;
    txOpen.value = true;
}

function submitTransaction() {
    txForm.post(`/simpanan/${props.account.row_id}/transaksi`, {
        preserveScroll: true,
        onSuccess: () => {
            txOpen.value = false;
            txForm.reset();
        },
    });
}

function openEdit() {
    editForm.clearErrors();
    editForm.interest_rate = props.account.interest_rate;
    editForm.tax_rate = props.account.tax_rate;
    editForm.admin_fee = props.account.admin_fee;
    editForm.minimum_closing_date = props.account.minimum_closing_date ?? '';
    editForm.notes = props.account.notes ?? '';
    editOpen.value = true;
}

function submitEdit() {
    editForm.put(`/simpanan/${props.account.row_id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
        },
    });
}

function applyFilter() {
    router.get(`/simpanan/${props.account.row_id}`, {
        year: year.value,
        month: month.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilter() {
    year.value = now.getFullYear();
    month.value = now.getMonth() + 1;
    applyFilter();
}

function printDoc(path) {
    window.open(path, '_blank', 'noopener');
}

function openDocument(key = 'kop') {
    activeDocumentKey.value = key;
    documentModalOpen.value = true;
}

function printActiveDocument() {
    const frame = documentFrame.value;

    if (!frame?.contentWindow) return;

    frame.contentWindow.focus();
    frame.contentWindow.print();
}

async function recalculate() {
    const ok = await confirm({
        title: t('Hitung Ulang Saldo?'),
        message: t('Saldo berjalan seluruh mutasi rekening akan dihitung ulang secara kronologis, sama seperti menu Generate pada SIUPK.'),
        confirmLabel: t('Hitung Ulang'),
        variant: 'primary',
        icon: 'refresh',
    });
    if (!ok) return;

    router.post(`/simpanan/${props.account.row_id}/hitung-ulang`, {}, { preserveScroll: true });
}

async function closeAccount() {
    const ok = await confirm({
        title: t('Tutup Rekening?'),
        message: t('Rekening hanya dapat ditutup bila saldonya nol dan tanggal minimal tutup sudah lewat. Riwayat mutasi tetap tersimpan.'),
        confirmLabel: t('Tutup Rekening'),
        variant: 'danger',
    });
    if (!ok) return;

    router.post(`/simpanan/${props.account.row_id}/tutup`, {}, { preserveScroll: true });
}

async function deleteAccount() {
    const ok = await confirm({
        title: t('Hapus Rekening?'),
        message: t('Rekening yang belum memiliki mutasi akan dihapus permanen.'),
        confirmLabel: t('Hapus'),
        variant: 'danger',
    });
    if (!ok) return;

    router.delete(`/simpanan/${props.account.row_id}`);
}

function typeTone(type) {
    if (type === 'bunga') return 'success-soft';
    if (type === 'penarikan' || type === 'pajak' || type === 'admin') return 'warning-soft';
    return 'primary-soft';
}
</script>

<template>
    <Head :title="`Rekening ${account.account_number}`" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-5">
            <header class="flex min-w-0 items-start gap-3">
                <Link
                    href="/simpanan"
                    class="mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface-variant transition hover:border-primary/30 hover:text-primary"
                    :aria-label="t('Kembali')"
                >
                    <AppIcon name="arrow_back" class="text-lg" />
                </Link>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="font-mono text-xl font-bold tracking-tight text-primary sm:text-2xl">{{ account.account_number }}</h1>
                        <AppBadge :tone="isActive ? 'success-soft' : 'neutral'">
                            {{ isActive ? t('Aktif') : t('Ditutup') }}
                        </AppBadge>
                        <AppBadge tone="primary-soft">{{ account.product.form_type_label }}</AppBadge>
                    </div>
                    <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-on-surface-variant">
                        <span class="font-medium text-on-surface">{{ account.member.name }}</span>
                        <span class="text-outline">·</span>
                        <span>{{ account.member.member_number || '—' }}</span>
                        <span class="text-outline">·</span>
                        <span>{{ account.product.name }}</span>
                    </p>
                </div>
            </header>

            <div class="flex flex-col gap-2 rounded-2xl border border-outline-variant bg-surface-container-lowest p-2 md:flex-row md:items-center md:justify-between">
                <div v-if="canManage" class="grid grid-cols-2 gap-2 md:flex md:flex-wrap">
                    <AppButton v-if="isActive" size="compact" icon="add" icon-class="!hidden xl:!inline" class="w-full justify-center md:w-auto" @click="openTransaction('setoran')">
                        {{ t('Setoran') }}
                    </AppButton>
                    <AppButton v-if="isActive" variant="secondary" size="compact" icon="remove" icon-class="!hidden xl:!inline" class="w-full justify-center md:w-auto" @click="openTransaction('penarikan')">
                        {{ t('Penarikan') }}
                    </AppButton>
                    <AppButton variant="secondary" size="compact" icon="edit" icon-class="!hidden xl:!inline" class="w-full justify-center md:w-auto" @click="openEdit">
                        {{ t('Edit') }}
                    </AppButton>
                    <AppButton variant="ghost" size="compact" icon="refresh" icon-class="!hidden xl:!inline" class="w-full justify-center md:w-auto" @click="recalculate">
                        {{ t('Hitung Ulang') }}
                    </AppButton>
                </div>
                <div class="grid grid-cols-2 gap-2 md:flex md:flex-wrap md:items-center md:ml-auto">
                    <Link v-if="canManage" :href="`/simpanan/create?member=${account.member.row_id}`" class="col-span-2 block md:col-span-1">
                        <AppButton variant="secondary" size="compact" icon="add_card" icon-class="!hidden xl:!inline" class="w-full justify-center md:w-auto">
                            {{ t('Rekening Baru') }}
                        </AppButton>
                    </Link>
                    <AppButton variant="secondary" size="compact" icon="print" icon-class="!hidden xl:!inline" class="col-span-2 w-full justify-center md:col-span-1 md:w-auto" @click="openDocument()">
                        {{ t('Cetak Dokumen') }}
                    </AppButton>
                </div>
            </div>

            <section class="grid gap-3 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                <div class="relative overflow-hidden rounded-2xl border border-outline-variant bg-gradient-to-br from-secondary-container via-surface-container-low to-primary-container p-5">
                    <div class="pointer-events-none absolute -right-12 -top-14 size-44 rounded-full bg-surface/40 blur-2xl" aria-hidden="true" />
                    <div class="relative">
                        <div class="flex items-center gap-2">
                            <span class="grid size-8 place-items-center rounded-xl bg-surface/70 text-secondary shadow-sm">
                                <AppIcon name="savings" class="text-lg" />
                            </span>
                            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-on-surface-variant">{{ t('Saldo Rekening') }}</p>
                        </div>
                        <p class="mt-2 text-3xl font-black tracking-tight text-on-surface sm:text-4xl">{{ money(account.balance) }}</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                            <AppBadge tone="primary-soft">{{ account.product.category_label }}</AppBadge>
                            <span class="text-on-surface-variant">{{ t('Dibuka') }} {{ account.opened_at || '—' }}</span>
                            <span class="text-outline">·</span>
                            <span class="text-on-surface-variant">{{ t('Bunga') }} {{ account.interest_rate }}% / {{ t('bulan') }}</span>
                            <span class="text-outline">·</span>
                            <span class="text-on-surface-variant">{{ t('Pajak') }} {{ account.tax_rate }}%</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col divide-y divide-outline-variant overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest">
                    <div class="flex flex-1 items-center justify-between gap-3 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <AppIcon name="south_west" class="text-sm text-secondary" />
                            <span class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Masuk') }}</span>
                        </div>
                        <span class="truncate text-sm font-bold text-secondary">{{ money(account.stats.total_in) }}</span>
                    </div>
                    <div class="flex flex-1 items-center justify-between gap-3 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <AppIcon name="north_east" class="text-sm text-tertiary" />
                            <span class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Keluar') }}</span>
                        </div>
                        <span class="truncate text-sm font-bold text-tertiary">{{ money(account.stats.total_out) }}</span>
                    </div>
                    <div class="flex flex-1 items-center justify-between gap-3 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <AppIcon name="receipt_long" class="text-sm text-primary" />
                            <span class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Mutasi') }}</span>
                        </div>
                        <span class="text-sm font-bold text-on-surface">{{ account.stats.transaction_count }}×</span>
                    </div>
                </div>
            </section>

            <div class="grid min-w-0 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="min-w-0 space-y-4">
                    <AppCard :padded="false" class="min-w-0 overflow-hidden">
                        <template #header>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <AppIcon name="receipt_long" tone="primary" container-size="8" />
                                    <div>
                                        <p class="font-semibold text-on-surface">{{ t('Buku Simpanan') }}</p>
                                        <p class="text-xs text-on-surface-variant">{{ t('Riwayat mutasi rekening') }}</p>
                                    </div>
                                </div>
                                <AppBadge tone="neutral">{{ account.stats.transaction_count }} {{ t('transaksi') }}</AppBadge>
                            </div>
                        </template>

                        <div class="space-y-2 border-b border-outline-variant p-3">
                            <div class="flex items-center gap-1 sm:gap-2">
                                <span class="hidden shrink-0 px-1 text-[11px] font-bold uppercase tracking-[0.14em] text-on-surface-variant sm:inline">{{ t('Periode') }}</span>
                                <div class="min-w-0 flex-1 sm:w-28 sm:flex-none">
                                    <SmartSelect v-model="year" :label="t('Tahun')" hide-label size="compact" :options="yearOptions" />
                                </div>
                                <div class="min-w-0 flex-1 sm:w-36 sm:flex-none">
                                    <SmartSelect v-model="month" :label="t('Bulan')" hide-label size="compact" :options="monthOptions" />
                                </div>
                                <AppButton variant="secondary" size="compact" icon="filter_alt" class="shrink-0 !px-2 sm:!px-3" :aria-label="t('Tampilkan')" :title="t('Tampilkan')" @click="applyFilter">
                                    <span class="hidden sm:inline">{{ t('Tampilkan') }}</span>
                                </AppButton>
                                <AppButton variant="ghost" size="compact" icon="restart_alt" class="shrink-0 !px-2 sm:!px-3" :aria-label="t('Reset')" :title="t('Reset')" @click="resetFilter">
                                    <span class="hidden sm:inline">{{ t('Reset') }}</span>
                                </AppButton>
                            </div>
                            <div v-if="statement" class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl bg-surface-container-low px-3 py-2 text-xs sm:text-sm">
                                <span class="text-on-surface-variant">{{ t('Saldo awal') }}</span>
                                <span class="font-semibold text-on-surface">{{ money(statement.opening_balance) }}</span>
                                <AppIcon name="arrow_forward" class="text-base text-outline" />
                                <span class="text-on-surface-variant">{{ t('Saldo akhir') }}</span>
                                <span class="font-bold text-secondary">{{ money(statement.closing_balance) }}</span>
                            </div>
                        </div>

                        <div class="divide-y divide-outline-variant/60 min-[1400px]:hidden">
                            <div v-if="showOpeningRow" class="flex items-center justify-between gap-3 bg-surface-container-low/40 px-4 py-2.5">
                                <span class="text-xs font-semibold text-on-surface">{{ openingLabel }}</span>
                                <span class="text-xs font-bold text-on-surface">{{ money(statement.opening_balance) }}</span>
                            </div>
                            <div v-for="(row, index) in transactions.data" :key="row.row_id" class="px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs text-on-surface-variant">
                                            {{ row.transaction_date }}
                                            <span class="text-outline">· #{{ (transactions.from || 1) + index }}</span>
                                        </p>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                            <AppBadge :tone="typeTone(row.type)">{{ row.type_label }}</AppBadge>
                                            <span class="font-mono text-[11px] font-semibold text-outline">{{ t('Kode') }} {{ row.code }}</span>
                                        </div>
                                        <p class="mt-1 break-words text-xs text-on-surface-variant">{{ row.description || '—' }}</p>
                                        <p v-if="row.created_by" class="mt-0.5 text-[11px] text-outline">{{ t('oleh') }} {{ row.created_by }}</p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-sm font-bold" :class="row.direction === 'in' ? 'text-secondary' : 'text-tertiary'">
                                            {{ row.direction === 'in' ? '+' : '−' }} {{ money(row.amount) }}
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-on-surface-variant">{{ t('Saldo') }} {{ money(row.balance_after) }}</p>
                                    </div>
                                </div>
                                <div class="mt-2 flex justify-end gap-1">
                                    <AppIconButton
                                        name="menu_book"
                                        tone="primary"
                                        size="sm"
                                        :tooltip="t('Cetak pada buku')"
                                        @click="printDoc(`/simpanan/${account.row_id}/cetak/buku/${row.row_id}`)"
                                    />
                                    <AppIconButton
                                        name="receipt"
                                        tone="secondary"
                                        size="sm"
                                        :tooltip="t('Cetak kwitansi')"
                                        @click="printDoc(`/simpanan/${account.row_id}/cetak/kwitansi/${row.row_id}`)"
                                    />
                                </div>
                            </div>
                            <div v-if="transactions.data.length === 0" class="px-4 py-8 text-center">
                                <AppIcon name="receipt_long" class="text-2xl text-outline" />
                                <p class="mt-2 text-sm font-semibold text-on-surface">{{ t('Belum ada mutasi pada periode ini.') }}</p>
                                <p class="mt-1 text-xs text-on-surface-variant">{{ t('Ubah periode atau reset filter untuk melihat riwayat lainnya.') }}</p>
                            </div>
                            <div class="flex items-center justify-between gap-3 border-t-2 border-outline-variant bg-surface-container-low/40 px-4 py-3">
                                <span class="text-sm font-bold text-on-surface">{{ t('Total Saldo') }}</span>
                                <span class="text-sm font-black text-on-surface">{{ money(statement?.closing_balance ?? account.balance) }}</span>
                            </div>
                        </div>

                        <div class="hidden overflow-x-auto min-[1400px]:block">
                            <table class="w-full min-w-[720px] divide-y divide-outline-variant text-sm">
                                <thead class="bg-surface-container-low/60 text-[11px] uppercase tracking-wider text-on-surface-variant">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">{{ t('Tanggal') }}</th>
                                        <th class="px-4 py-3 text-left font-semibold">{{ t('Keterangan') }}</th>
                                        <th class="px-3 py-3 text-right font-semibold">{{ t('Debit') }}</th>
                                        <th class="px-3 py-3 text-right font-semibold">{{ t('Kredit') }}</th>
                                        <th class="px-3 py-3 text-right font-semibold">{{ t('Saldo') }}</th>
                                        <th class="px-3 py-3 text-right font-semibold">{{ t('Cetak') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60">
                                    <tr v-if="showOpeningRow" class="bg-surface-container-low/40">
                                        <td colspan="4" class="px-4 py-2.5 font-semibold text-on-surface">{{ openingLabel }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-bold text-on-surface">{{ money(statement.opening_balance) }}</td>
                                        <td class="px-3 py-2.5">&nbsp;</td>
                                    </tr>
                                    <tr v-for="(row, index) in transactions.data" :key="row.row_id" class="transition hover:bg-surface-container-low/40">
                                        <td class="whitespace-nowrap px-4 py-3 align-top">
                                            <p class="text-sm text-on-surface">{{ row.transaction_date }}</p>
                                            <p class="mt-0.5 text-[11px] text-outline">#{{ (transactions.from || 1) + index }}</p>
                                        </td>
                                        <td class="min-w-[220px] px-4 py-3 align-top">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <AppBadge :tone="typeTone(row.type)">{{ row.type_label }}</AppBadge>
                                                <span class="font-mono text-[11px] font-semibold text-outline">{{ t('Kode') }} {{ row.code }}</span>
                                            </div>
                                            <p class="mt-1 text-xs text-on-surface-variant">{{ row.description || '—' }}</p>
                                            <p v-if="row.created_by" class="mt-0.5 text-[11px] text-outline">{{ t('oleh') }} {{ row.created_by }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right align-top font-semibold text-tertiary">
                                            {{ row.direction === 'out' ? money(row.amount) : '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right align-top font-semibold text-secondary">
                                            {{ row.direction === 'in' ? money(row.amount) : '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right align-top font-bold text-on-surface">{{ money(row.balance_after) }}</td>
                                        <td class="px-3 py-3 align-top">
                                            <div class="flex justify-end gap-1">
                                                <AppIconButton
                                                    name="menu_book"
                                                    tone="primary"
                                                    size="sm"
                                                    :tooltip="t('Cetak pada buku')"
                                                    @click="printDoc(`/simpanan/${account.row_id}/cetak/buku/${row.row_id}`)"
                                                />
                                                <AppIconButton
                                                    name="receipt"
                                                    tone="secondary"
                                                    size="sm"
                                                    :tooltip="t('Cetak kwitansi')"
                                                    @click="printDoc(`/simpanan/${account.row_id}/cetak/kwitansi/${row.row_id}`)"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="transactions.data.length === 0">
                                        <td colspan="6" class="px-4 py-10 text-center">
                                            <AppIcon name="receipt_long" class="text-2xl text-outline" />
                                            <p class="mt-2 text-sm font-semibold text-on-surface">{{ t('Belum ada mutasi pada periode ini.') }}</p>
                                            <p class="mt-1 text-xs text-on-surface-variant">{{ t('Ubah periode atau reset filter untuk melihat riwayat lainnya.') }}</p>
                                        </td>
                                    </tr>
                                    <tr class="border-t-2 border-outline-variant bg-surface-container-low/40">
                                        <td colspan="4" class="px-4 py-3 text-right font-bold text-on-surface">{{ t('Total Saldo') }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right font-black text-on-surface">
                                            {{ money(statement?.closing_balance ?? account.balance) }}
                                        </td>
                                        <td class="px-3 py-3">&nbsp;</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="transactions.last_page > 1" class="flex flex-wrap items-center justify-between gap-2 border-t border-outline-variant px-4 py-3 text-sm">
                            <p class="text-on-surface-variant">{{ t('Halaman') }} {{ transactions.current_page }} / {{ transactions.last_page }}</p>
                            <div class="flex flex-wrap gap-1">
                                <template v-for="(link, index) in transactions.links" :key="index">
                                    <Link
                                        v-if="link.url"
                                        :href="link.url"
                                        preserve-scroll
                                        class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                                        :class="link.active ? 'bg-primary text-on-primary' : 'bg-surface-container-low text-on-surface hover:bg-surface-container-high'"
                                        v-html="link.label"
                                    />
                                    <span v-else class="rounded-lg px-3 py-1.5 text-sm text-outline" v-html="link.label" />
                                </template>
                            </div>
                        </div>
                    </AppCard>

                    <AppCard v-if="canManage" :padded="false">
                        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold text-on-surface">{{ t('Tindakan Rekening') }}</p>
                                <p v-if="hasTransactions" class="mt-0.5 text-xs text-on-surface-variant">
                                    {{ t('Rekening dengan mutasi tidak dapat dihapus; gunakan penutupan rekening.') }}
                                </p>
                                <p v-else class="mt-0.5 text-xs text-on-surface-variant">
                                    {{ t('Rekening tanpa mutasi dapat dihapus permanen.') }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <AppButton v-if="isActive" variant="secondary" size="compact" icon="lock" @click="closeAccount">
                                    {{ t('Tutup Rekening') }}
                                </AppButton>
                                <AppButton v-if="!hasTransactions" variant="danger" size="compact" icon="delete" @click="deleteAccount">
                                    {{ t('Hapus Rekening') }}
                                </AppButton>
                            </div>
                        </div>
                    </AppCard>
                </div>

                <aside class="min-w-0 space-y-4 lg:grid lg:grid-cols-2 lg:items-start lg:gap-4 lg:space-y-0 xl:block xl:space-y-4">
                    <AppCard :padded="false">
                        <template #header>
                            <div class="flex items-center gap-2">
                                <AppIcon name="person" tone="primary" container-size="8" />
                                <span class="font-semibold text-on-surface">{{ t('Anggota') }}</span>
                            </div>
                        </template>
                        <div class="p-4">
                            <div class="flex items-center gap-3">
                                <div class="grid size-11 shrink-0 place-items-center rounded-full bg-primary-container text-sm font-bold text-on-primary-container">
                                    {{ memberInitials }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-on-surface">{{ account.member.name }}</p>
                                    <p class="text-xs text-on-surface-variant">{{ account.member.member_number || '—' }}</p>
                                </div>
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 text-sm">
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">NIK</dt>
                                    <dd class="mt-0.5 break-words font-medium text-on-surface">{{ account.member.nik || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Telepon') }}</dt>
                                    <dd class="mt-0.5 break-words font-medium text-on-surface">{{ account.member.phone || '—' }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Alamat') }}</dt>
                                    <dd class="mt-0.5 font-medium text-on-surface">{{ account.address || '—' }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Minimal tutup') }}</dt>
                                    <dd class="mt-0.5 font-medium text-on-surface">{{ account.minimum_closing_date || '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </AppCard>

                    <AppCard :padded="false">
                        <template #header>
                            <div class="flex items-center gap-2">
                                <AppIcon name="tune" tone="primary" container-size="8" />
                                <span class="font-semibold text-on-surface">{{ t('Ketentuan Bunga') }}</span>
                            </div>
                        </template>
                        <div class="p-4">
                            <dl class="grid grid-cols-2 gap-2 text-sm">
                                <div class="rounded-xl bg-surface-container-low p-2.5">
                                    <dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Bunga / bulan') }}</dt>
                                    <dd class="mt-1 font-bold text-on-surface">{{ account.interest_rate }}%</dd>
                                </div>
                                <div class="rounded-xl bg-surface-container-low p-2.5">
                                    <dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Pajak bunga') }}</dt>
                                    <dd class="mt-1 font-bold text-on-surface">{{ account.tax_rate }}%</dd>
                                </div>
                                <div class="rounded-xl bg-surface-container-low p-2.5">
                                    <dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Biaya admin') }}</dt>
                                    <dd class="mt-1 font-bold text-on-surface">{{ money(account.admin_fee) }}</dd>
                                </div>
                                <div class="rounded-xl bg-surface-container-low p-2.5">
                                    <dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Buka buku') }}</dt>
                                    <dd class="mt-1 font-bold text-on-surface">{{ money(account.opening_fee) }}</dd>
                                </div>
                                <div class="col-span-2 flex items-center justify-between gap-3 rounded-xl bg-surface-container-low px-2.5 py-2">
                                    <dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Metode saldo') }}</dt>
                                    <dd class="text-right text-sm font-semibold text-on-surface">{{ methodLabel }}</dd>
                                </div>
                                <div class="col-span-2 flex items-center justify-between gap-3 rounded-xl bg-surface-container-low px-2.5 py-2">
                                    <dt class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Saldo minimal tarik') }}</dt>
                                    <dd class="text-right text-sm font-semibold text-on-surface">{{ money(minimumWithdrawal) }}</dd>
                                </div>
                            </dl>
                            <p v-if="account.notes" class="mt-3 rounded-xl bg-surface-container-low p-3 text-xs text-on-surface-variant">
                                {{ account.notes }}
                            </p>
                        </div>
                    </AppCard>

                </aside>
            </div>
        </div>

        <AppModal v-model="txOpen" :title="txForm.type === 'setoran' ? t('Setoran Simpanan') : t('Penarikan Simpanan')" size="md">
            <form class="space-y-4" @submit.prevent="submitTransaction">
                <AppCurrencyInput
                    v-model="txForm.amount"
                    :label="t('Nominal')"
                    :icon="null"
                    :show-stepper="false"
                    :error="txForm.errors.amount"
                />
                <AppDatePicker
                    v-model="txForm.transaction_date"
                    :label="t('Tanggal Transaksi')"
                    :error="txForm.errors.transaction_date"
                />
                <AppInput
                    v-model="txForm.reference"
                    :label="t('Referensi')"
                    :error="txForm.errors.reference"
                    :placeholder="t('Nomor bukti / referensi (opsional)')"
                />
                <AppTextarea
                    v-model="txForm.description"
                    :label="t('Keterangan')"
                    :error="txForm.errors.description"
                    :rows="2"
                />
                <p v-if="txForm.type === 'penarikan'" class="rounded-xl bg-warning/10 p-3 text-xs text-on-surface-variant">
                    {{ t('Saldo setelah penarikan tidak boleh kurang dari saldo minimal jenis simpanan') }}
                    <strong>{{ money(minimumWithdrawal) }}</strong>.
                </p>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="txOpen = false">{{ t('Batal') }}</AppButton>
                <AppButton :loading="txForm.processing" icon="save" @click="submitTransaction">{{ t('Simpan') }}</AppButton>
            </template>
        </AppModal>

        <AppModal v-model="editOpen" :title="t('Edit Rekening Simpanan')" size="md">
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div class="grid gap-4 sm:grid-cols-3">
                    <AppInput v-model="editForm.interest_rate" type="number" step="0.001" min="0" max="100" :label="t('Bunga (%)')" :error="editForm.errors.interest_rate" />
                    <AppInput v-model="editForm.tax_rate" type="number" step="0.001" min="0" max="100" :label="t('Pajak (%)')" :error="editForm.errors.tax_rate" />
                    <AppInput v-model="editForm.admin_fee" type="number" step="0.01" min="0" :label="t('Admin / Bulan')" :error="editForm.errors.admin_fee" />
                </div>
                <AppDatePicker
                    v-model="editForm.minimum_closing_date"
                    :label="t('Tanggal Minimal Tutup')"
                    :error="editForm.errors.minimum_closing_date"
                />
                <AppTextarea v-model="editForm.notes" :label="t('Catatan')" :error="editForm.errors.notes" :rows="2" />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="editOpen = false">{{ t('Batal') }}</AppButton>
                <AppButton :loading="editForm.processing" icon="save" @click="submitEdit">{{ t('Simpan Perubahan') }}</AppButton>
            </template>
        </AppModal>

        <AppModal v-model="documentModalOpen" :title="`${t('Cetak Dokumen')} - ${account.account_number}`" size="fullscreen">
            <div class="-m-5 flex h-[calc(100vh-8rem)] flex-col sm:-m-6 md:grid md:grid-cols-[300px_minmax(0,1fr)]">
                <aside class="flex shrink-0 gap-2 overflow-x-auto border-b border-outline-variant p-3 md:flex-col md:overflow-y-auto md:border-b-0 md:border-r">
                    <div class="hidden px-1 md:block">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('Daftar Dokumen') }}</p>
                        <p class="mt-1 text-[11px] text-on-surface-variant">{{ t('Pilih dokumen untuk melihat pratinjau.') }}</p>
                    </div>
                    <button
                        v-for="doc in documentItems"
                        :key="doc.key"
                        type="button"
                        class="group flex shrink-0 items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm transition md:w-full"
                        :class="activeDocument.key === doc.key ? 'bg-primary-container/30 text-primary' : 'hover:bg-surface-container-low'"
                        @click="activeDocumentKey = doc.key"
                    >
                        <AppIcon :name="doc.icon" class="shrink-0 text-base" :class="activeDocument.key === doc.key ? 'text-primary' : 'text-on-surface-variant group-hover:text-primary'" />
                        <span class="min-w-0">
                            <span class="block truncate text-xs font-semibold">{{ doc.label }}</span>
                            <span class="hidden truncate text-[10px] text-on-surface-variant md:block">{{ doc.description }}</span>
                        </span>
                        <AppIcon name="chevron_right" class="ml-auto hidden shrink-0 text-base text-on-surface-variant md:block" />
                    </button>
                </aside>
                <div class="flex min-h-0 flex-1 flex-col bg-surface-container-lowest">
                    <div class="flex shrink-0 items-center justify-between gap-2 border-b border-outline-variant bg-surface px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-on-surface">{{ activeDocument.label }}</p>
                            <p class="mt-0.5 truncate text-[11px] text-on-surface-variant">{{ activeDocument.description }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <a
                                :href="activeDocument.url"
                                target="_blank"
                                rel="noopener"
                                class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-outline-variant bg-surface text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary sm:inline-flex"
                                :title="t('Buka di tab baru')"
                                :aria-label="t('Buka di tab baru')"
                            >
                                <AppIcon name="open_in_new" class="text-base" />
                            </a>
                            <a
                                :href="activeDocument.pdfUrl"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-2.5 text-xs font-bold text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary"
                                :title="t('Unduh PDF')"
                                :aria-label="t('Unduh PDF')"
                            >
                                <AppIcon name="picture_as_pdf" class="text-base" />
                                <span class="hidden sm:inline">PDF</span>
                            </a>
                            <a
                                :href="activeDocument.excelUrl"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-2.5 text-xs font-bold text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary"
                                :title="t('Unduh Excel')"
                                :aria-label="t('Unduh Excel')"
                            >
                                <AppIcon name="table_view" class="text-base" />
                                <span class="hidden sm:inline">{{ t('Excel') }}</span>
                            </a>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-2.5 text-xs font-bold text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary"
                                :title="t('Cetak')"
                                :aria-label="t('Cetak')"
                                @click="printActiveDocument"
                            >
                                <AppIcon name="print" class="text-base" />
                                <span class="hidden sm:inline">{{ t('Cetak') }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="relative min-h-0 flex-1">
                        <iframe
                            ref="documentFrame"
                            :key="activeDocument.url"
                            :src="activeDocument.url"
                            class="h-full w-full border-0 bg-white"
                            :title="activeDocument.label"
                        ></iframe>
                    </div>
                </div>
            </div>
            <template #footer>
                <AppButton variant="secondary" @click="documentModalOpen = false">{{ t('Tutup') }}</AppButton>
            </template>
        </AppModal>

        <AppConfirmDialog />
    </AuthenticatedLayout>
</template>
