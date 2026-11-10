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
import AppInput from '../../Components/AppInput.vue';
import AppModal from '../../Components/AppModal.vue';
import AppTextarea from '../../Components/AppTextarea.vue';
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

const year = ref(props.filters.year ?? now.getFullYear());
const month = ref(props.filters.month ?? 0);

const monthOptions = [
    { value: 0, label: t('Sepanjang tahun') },
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

const yearOptions = [
    { value: 0, label: t('Semua tahun') },
    ...Array.from({ length: 10 }, (_, index) => {
        const value = now.getFullYear() - index;

        return { value, label: String(value) };
    }),
];

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
        month: month.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilter() {
    year.value = now.getFullYear();
    month.value = 0;
    applyFilter();
}

function printDoc(path) {
    window.open(path, '_blank', 'noopener');
}

function printKoran() {
    const params = new URLSearchParams({
        year: String(year.value || now.getFullYear()),
        month: String(month.value || 0),
    });

    printDoc(`/simpanan/${props.account.row_id}/cetak/koran?${params.toString()}`);
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
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 xl:flex-row xl:items-center">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link href="/simpanan" class="text-on-surface-variant transition hover:text-primary">
                            <AppIcon name="arrow_back" />
                        </Link>
                        <h1 class="font-mono text-2xl font-bold text-primary">{{ account.account_number }}</h1>
                        <AppBadge :tone="isActive ? 'success-soft' : 'neutral'">
                            {{ isActive ? t('Aktif') : t('Ditutup') }}
                        </AppBadge>
                        <AppBadge tone="primary-soft">{{ account.product.form_type_label }}</AppBadge>
                    </div>
                    <p class="mt-1 text-on-surface-variant">
                        {{ account.member.name }}
                        <span class="text-outline">·</span>
                        {{ account.member.member_number || '—' }}
                        <span class="text-outline">·</span>
                        {{ account.product.name }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <AppButton variant="ghost" icon="id_card" @click="printDoc(`/simpanan/${account.row_id}/cetak/kop`)">
                        {{ t('KOP') }}
                    </AppButton>
                    <AppButton variant="ghost" icon="description" @click="printDoc(`/simpanan/${account.row_id}/cetak/formulir`)">
                        {{ t('Formulir') }}
                    </AppButton>
                    <AppButton variant="ghost" icon="receipt_long" @click="printKoran">
                        {{ t('Rekening Koran') }}
                    </AppButton>
                    <Link v-if="canManage" :href="`/simpanan/create?member=${account.member.row_id}`" class="contents">
                        <AppButton variant="ghost" icon="add_card">{{ t('Rekening Baru') }}</AppButton>
                    </Link>
                    <AppButton v-if="canManage" variant="secondary" icon="edit" @click="openEdit">{{ t('Edit') }}</AppButton>
                    <AppButton v-if="canManage && isActive" variant="primary" icon="add" @click="openTransaction('setoran')">
                        {{ t('Setoran') }}
                    </AppButton>
                    <AppButton v-if="canManage && isActive" variant="secondary" icon="remove" @click="openTransaction('penarikan')">
                        {{ t('Penarikan') }}
                    </AppButton>
                </div>
            </header>

            <section class="relative overflow-hidden rounded-3xl border border-outline-variant bg-gradient-to-br from-secondary-container via-surface-container-low to-primary-container p-5 sm:p-8">
                <div class="pointer-events-none absolute -right-16 -top-20 size-56 rounded-full bg-surface/40 blur-2xl" aria-hidden="true" />
                <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">{{ t('Saldo Rekening') }}</p>
                        <p class="mt-1 text-4xl font-black leading-tight text-on-surface sm:text-5xl">{{ money(account.balance) }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                            <AppBadge tone="primary-soft">{{ account.product.category_label }}</AppBadge>
                            <span class="text-on-surface-variant">{{ t('Dibuka') }} {{ account.opened_at || '—' }}</span>
                            <span class="text-outline">·</span>
                            <span class="text-on-surface-variant">{{ t('Bunga') }} {{ account.interest_rate }}% / {{ t('bulan') }}</span>
                            <span class="text-outline">·</span>
                            <span class="text-on-surface-variant">{{ t('Pajak') }} {{ account.tax_rate }}%</span>
                            <span class="text-outline">·</span>
                            <span class="text-on-surface-variant">{{ t('Admin') }} {{ money(account.admin_fee) }} / {{ t('bulan') }}</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-2xl bg-surface/75 px-3 py-3 backdrop-blur">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Masuk') }}</p>
                            <p class="mt-1 text-sm font-bold text-secondary">{{ money(account.stats.total_in) }}</p>
                        </div>
                        <div class="rounded-2xl bg-surface/75 px-3 py-3 backdrop-blur">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Keluar') }}</p>
                            <p class="mt-1 text-sm font-bold text-tertiary">{{ money(account.stats.total_out) }}</p>
                        </div>
                        <div class="rounded-2xl bg-surface/75 px-3 py-3 backdrop-blur">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Mutasi') }}</p>
                            <p class="mt-1 text-sm font-bold text-on-surface">{{ account.stats.transaction_count }}×</p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <AppCard :padded="false">
                    <template #header>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <AppIcon name="receipt_long" tone="primary" />
                                <span class="font-semibold text-on-surface">{{ t('Buku Simpanan') }}</span>
                            </div>
                            <AppBadge tone="neutral">{{ account.stats.transaction_count }} {{ t('transaksi') }}</AppBadge>
                        </div>
                    </template>

                    <div class="flex flex-col gap-3 border-b border-outline-variant p-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="flex flex-wrap items-end gap-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">
                                {{ t('Periode') }}
                                <select v-model="year" class="mt-1 block rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm font-medium text-on-surface">
                                    <option v-for="option in yearOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </label>
                            <label class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">
                                {{ t('Bulan') }}
                                <select v-model="month" class="mt-1 block rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm font-medium text-on-surface">
                                    <option v-for="option in monthOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </label>
                            <AppButton variant="secondary" icon="filter_alt" @click="applyFilter">{{ t('Tampilkan') }}</AppButton>
                            <AppButton variant="ghost" icon="restart_alt" @click="resetFilter">{{ t('Reset') }}</AppButton>
                        </div>
                        <div v-if="statement" class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="text-on-surface-variant">{{ t('Saldo awal') }}</span>
                            <span class="font-semibold text-on-surface">{{ money(statement.opening_balance) }}</span>
                            <AppIcon name="arrow_forward" class="text-outline" />
                            <span class="text-on-surface-variant">{{ t('Saldo akhir') }}</span>
                            <span class="font-bold text-secondary">{{ money(statement.closing_balance) }}</span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-outline-variant text-sm">
                            <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                                <tr>
                                    <th class="px-3 py-3 text-left font-semibold">No</th>
                                    <th class="px-3 py-3 text-left font-semibold">{{ t('Tanggal') }}</th>
                                    <th class="px-3 py-3 text-center font-semibold">{{ t('Kode') }}</th>
                                    <th class="px-3 py-3 text-left font-semibold">{{ t('Keterangan') }}</th>
                                    <th class="px-3 py-3 text-right font-semibold">{{ t('Debit') }}</th>
                                    <th class="px-3 py-3 text-right font-semibold">{{ t('Kredit') }}</th>
                                    <th class="px-3 py-3 text-right font-semibold">{{ t('Saldo') }}</th>
                                    <th class="px-3 py-3 text-center font-semibold">P</th>
                                    <th class="px-3 py-3 text-right font-semibold">{{ t('Cetak') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/60">
                                <tr v-for="(row, index) in transactions.data" :key="row.row_id" class="transition hover:bg-surface-container-low/60">
                                    <td class="whitespace-nowrap px-3 py-3 text-on-surface-variant">
                                        {{ (transactions.from || 1) + index }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-on-surface-variant">{{ row.transaction_date }}</td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="rounded-lg bg-surface-container-low px-2 py-1 font-mono text-xs font-bold text-primary">{{ row.code }}</span>
                                    </td>
                                    <td class="min-w-[220px] px-3 py-3">
                                        <div class="flex flex-col">
                                            <AppBadge :tone="typeTone(row.type)">{{ row.type_label }}</AppBadge>
                                            <span class="mt-1 text-xs text-on-surface-variant">{{ row.description || '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right font-semibold text-tertiary">
                                        {{ row.direction === 'out' ? money(row.amount) : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right font-semibold text-secondary">
                                        {{ row.direction === 'in' ? money(row.amount) : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right font-bold text-on-surface">{{ money(row.balance_after) }}</td>
                                    <td class="px-3 py-3 text-center text-xs font-semibold text-on-surface-variant">{{ row.created_by || '—' }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex justify-end gap-1">
                                            <AppButton variant="ghost" size="compact" icon="menu_book" :title="t('Cetak pada buku')" @click="printDoc(`/simpanan/${account.row_id}/cetak/buku/${row.row_id}`)" />
                                            <AppButton variant="ghost" size="compact" icon="receipt" :title="t('Cetak kwitansi')" @click="printDoc(`/simpanan/${account.row_id}/cetak/kwitansi/${row.row_id}`)" />
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="transactions.data.length === 0">
                                    <td colspan="9" class="px-4 py-10 text-center text-on-surface-variant">
                                        {{ t('Belum ada mutasi pada periode ini.') }}
                                    </td>
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

                <aside class="space-y-4">
                    <AppCard>
                        <template #header>
                            <div class="flex items-center gap-2">
                                <AppIcon name="person" tone="primary" />
                                <span class="font-semibold text-on-surface">{{ t('Anggota') }}</span>
                            </div>
                        </template>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="font-semibold text-on-surface">{{ account.member.name }}</p>
                                <p class="text-on-surface-variant">{{ account.member.member_number || '—' }}</p>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-on-surface-variant">NIK</span>
                                <span class="font-medium text-on-surface">{{ account.member.nik || '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-on-surface-variant">{{ t('Telepon') }}</span>
                                <span class="font-medium text-on-surface">{{ account.member.phone || '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-on-surface-variant">{{ t('Alamat') }}</span>
                                <span class="max-w-[60%] text-right font-medium text-on-surface">{{ account.address || '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-on-surface-variant">{{ t('Minimal tutup') }}</span>
                                <span class="font-medium text-on-surface">{{ account.minimum_closing_date || '—' }}</span>
                            </div>
                        </div>
                    </AppCard>

                    <AppCard>
                        <template #header>
                            <div class="flex items-center gap-2">
                                <AppIcon name="tune" tone="primary" />
                                <span class="font-semibold text-on-surface">{{ t('Ketentuan Bunga') }}</span>
                            </div>
                        </template>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">{{ t('Bunga per bulan') }}</dt>
                                <dd class="font-semibold text-on-surface">{{ account.interest_rate }}%</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">{{ t('Pajak bunga') }}</dt>
                                <dd class="font-semibold text-on-surface">{{ account.tax_rate }}%</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">{{ t('Biaya admin') }}</dt>
                                <dd class="font-semibold text-on-surface">{{ money(account.admin_fee) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">{{ t('Biaya buka buku') }}</dt>
                                <dd class="font-semibold text-on-surface">{{ money(account.opening_fee) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">{{ t('Metode saldo') }}</dt>
                                <dd class="text-right font-semibold text-on-surface">{{ methodLabel }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant">{{ t('Saldo minimal tarik') }}</dt>
                                <dd class="font-semibold text-on-surface">{{ money(minimumWithdrawal) }}</dd>
                            </div>
                        </dl>
                        <p v-if="account.notes" class="mt-3 rounded-xl bg-surface-container-low p-3 text-xs text-on-surface-variant">
                            {{ account.notes }}
                        </p>
                    </AppCard>

                    <AppCard v-if="canManage">
                        <div class="space-y-2">
                            <AppButton
                                v-if="isActive"
                                variant="secondary"
                                icon="lock"
                                class="w-full justify-center"
                                @click="closeAccount"
                            >
                                {{ t('Tutup Rekening') }}
                            </AppButton>
                            <AppButton
                                v-if="!hasTransactions"
                                variant="danger"
                                icon="delete"
                                class="w-full justify-center"
                                @click="deleteAccount"
                            >
                                {{ t('Hapus Rekening') }}
                            </AppButton>
                            <p v-else class="text-xs text-on-surface-variant">
                                {{ t('Rekening dengan mutasi tidak dapat dihapus; gunakan penutupan rekening.') }}
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
                    icon="payments"
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
                    icon="tag"
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

        <AppConfirmDialog />
    </AuthenticatedLayout>
</template>
