<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppModal from '../../../Components/AppModal.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import AppRadioGroup from '../../../Components/AppRadioGroup.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    transactionTypes: { type: Array, required: true },
    labels: { type: Object, required: true },
    options: { type: Object, required: true },
    accountOptions: { type: Array, required: true },
    today: { type: String, required: true },
    history: { type: Object, default: null },
    presetType: { type: String, default: null },
});

const page = usePage();
const pagePath = '/accounting/journal-entries/create';
const path = '/accounting/journal-entries';
const form = useForm({
    transaction_date: props.today,
    transaction_type: props.presetType || '',
    description: '',
    reference: '',
    amount: '',
    sumber_dana_row_id: '',
    disimpan_ke_row_id: '',
    asset_name: '',
    asset_quantity: '',
    asset_unit_cost: '',
    asset_useful_life_months: '',
});

const ASSET_PURCHASE_TYPES = [
    'pembelian_aset_tanah',
    'pembelian_aset_gedung',
    'pembelian_aset_kendaraan',
    'pembelian_aset_peralatan',
    'pembelian_biaya_pendirian',
    'pembelian_lisensi',
    'pembelian_sewa_dibayar_dimuka',
    'pembelian_asuransi_dibayar_dimuka',
    'pembelian_inventaris', // legacy
];
const DEFAULT_LIFE = {
    pembelian_aset_tanah: 0,
    pembelian_aset_gedung: 240,
    pembelian_aset_kendaraan: 60,
    pembelian_aset_peralatan: 48,
    pembelian_biaya_pendirian: 60,
    pembelian_lisensi: 60,
    pembelian_sewa_dibayar_dimuka: 60,
    pembelian_asuransi_dibayar_dimuka: 60,
    pembelian_inventaris: 48,
};

const currentType = computed(() => form.transaction_type);
const isInventory = computed(() => ASSET_PURCHASE_TYPES.includes(currentType.value));
const sumberDanaLabel = computed(() => (props.labels[currentType.value]?.sumber_dana ?? 'Sumber Dana'));
const disimpanKeLabel = computed(() => (props.labels[currentType.value]?.disimpan_ke ?? 'Disimpan Ke'));
const sumberDanaOptions = computed(() => props.options[currentType.value]?.sumber_dana ?? []);
const disimpanKeOptions = computed(() => props.options[currentType.value]?.disimpan_ke ?? []);

const acquisitionCost = computed(() => {
    const qty = Number(form.asset_quantity || 0);
    const unit = Number(form.asset_unit_cost || 0);
    if (!Number.isFinite(qty) || !Number.isFinite(unit) || qty <= 0 || unit <= 0) return 0;
    return Math.round(qty * unit);
});

function optionValue(opt) {
    return opt?.value ?? opt?.row_id ?? '';
}

function autoPickSingle(options, field) {
    if (!Array.isArray(options) || options.length !== 1) return;
    const only = optionValue(options[0]);
    if (only !== '' && only != null) form[field] = only;
}

watch(currentType, (type, prev) => {
    form.sumber_dana_row_id = '';
    form.disimpan_ke_row_id = '';
    if (!isInventory.value) {
        form.asset_name = '';
        form.asset_quantity = '';
        form.asset_unit_cost = '';
        form.asset_useful_life_months = '';
    } else if (type !== prev) {
        // Default umur eko per jenis (tanah = 0).
        const life = DEFAULT_LIFE[type];
        if (life !== undefined) form.asset_useful_life_months = life;
        if (!form.asset_quantity) form.asset_quantity = 1;
    }
    // Single-option COA (1 debit account per purchase type) → auto-select.
    autoPickSingle(sumberDanaOptions.value, 'sumber_dana_row_id');
    autoPickSingle(disimpanKeOptions.value, 'disimpan_ke_row_id');
}, { immediate: true });

watch(acquisitionCost, (value) => {
    if (!isInventory.value) return;
    form.amount = value > 0 ? value : '';
});

function submit() {
    if (isInventory.value) {
        form.amount = acquisitionCost.value > 0 ? acquisitionCost.value : form.amount;
        if (!form.description) {
            const qty = Number(form.asset_quantity || 0);
            form.description = `Pembelian inventaris: ${form.asset_name || 'barang'}${qty ? ` (${qty} unit)` : ''}`;
        }
    }
    form.post(path, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset(
                'amount',
                'reference',
                'description',
                'sumber_dana_row_id',
                'disimpan_ke_row_id',
                'transaction_type',
                'asset_name',
                'asset_quantity',
                'asset_unit_cost',
                'asset_useful_life_months',
            );
            form.transaction_date = props.today;
        },
    });
}

function fetchHistory() {
    const params = {
        account_row_id: historyForm.value.account_row_id,
        period: historyForm.value.period,
    };
    if (historyForm.value.period === 'daily') params.date = historyForm.value.date;
    else if (historyForm.value.period === 'monthly') params.month = historyForm.value.month;
    else if (historyForm.value.period === 'yearly') params.year = historyForm.value.year;
    router.get(pagePath, params, { preserveState: true, preserveScroll: true });
}

function resetHistory() {
    historyForm.value = {
        account_row_id: '',
        period: 'monthly',
        date: props.today,
        month: props.today.slice(0, 7),
        year: props.today.slice(0, 4),
    };
    router.get(pagePath, {}, { preserveState: true, preserveScroll: true });
}

const flash = computed(() => page.props.flash?.success);
const flashEntry = computed(() => (typeof flash.value === 'object' ? flash.value : null));
const flashLines = computed(() => flashEntry.value?.lines ?? []);
const debitedLines = computed(() => flashLines.value.filter((line) => line.debit > 0));
const creditedLines = computed(() => flashLines.value.filter((line) => line.credit > 0));

function currency(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value ?? 0);
}

const periodOptions = [
    { value: 'daily', label: 'Harian' },
    { value: 'monthly', label: 'Bulanan' },
    { value: 'yearly', label: 'Tahunan' },
];

const historyForm = ref({
    account_row_id: props.history?.account?.row_id ?? '',
    period: props.history?.period ?? 'monthly',
    date: props.history?.date ?? props.today,
    month: props.history?.month ?? props.today.slice(0, 7),
    year: props.history?.year ?? props.today.slice(0, 4),
});

const historyLoading = computed(() => router.processing);

const historyRows = computed(() => props.history?.rows ?? []);
const historyRange = computed(() => props.history?.range ?? null);

const periodDetail = computed(() => {
    if (historyForm.value.period === 'daily') return historyForm.value.date;
    if (historyForm.value.period === 'monthly') return historyForm.value.month;
    return historyForm.value.year;
});

function transactionTypeLabel(value) {
    return props.transactionTypes.find((type) => type.value === value)?.label ?? value ?? '—';
}

function entryHref(entryPublicId) {
    return entryPublicId ? `/accounting/journal-entries/${entryPublicId}` : '#';
}

const periodFieldLabel = computed(() => {
    if (historyForm.value.period === 'daily') return 'Tanggal';
    if (historyForm.value.period === 'monthly') return 'Bulan';
    return 'Tahun';
});

const periodValue = computed({
    get() {
        if (historyForm.value.period === 'daily') return historyForm.value.date;
        if (historyForm.value.period === 'monthly') return historyForm.value.month;
        return historyForm.value.year;
    },
    set(value) {
        if (historyForm.value.period === 'daily') historyForm.value.date = value;
        else if (historyForm.value.period === 'monthly') historyForm.value.month = value;
        else historyForm.value.year = value;
    },
});

const historyModalOpen = ref(false);

watch(() => props.history?.account, (account) => {
    if (account) historyModalOpen.value = true;
});

const historySummary = computed(() => {
    if (!historyRows.value.length) return null;
    const totalDebit = historyRows.value.reduce((sum, row) => sum + (row.debit || 0), 0);
    const totalCredit = historyRows.value.reduce((sum, row) => sum + (row.credit || 0), 0);
    const last = historyRows.value[historyRows.value.length - 1];
    return {
        count: historyRows.value.length,
        totalDebit,
        totalCredit,
        closingBalance: last.running_balance,
    };
});

function closeHistoryModal() {
    historyModalOpen.value = false;
}

function openHistoryModal() {
    if (props.history?.account) historyModalOpen.value = true;
}
</script>

<template>
    <Head title="Jurnal Umum" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary sm:text-3xl">Input Jurnal Umum</h1>
                <p class="mt-1 text-on-surface-variant">Catat transaksi jurnal manual dengan satu akun debit dan satu akun kredit.</p>
            </header>

            <AppCard v-if="flashEntry">
                <div class="mb-5 flex items-start gap-3">
                    <div class="grid size-12 shrink-0 place-items-center rounded-full bg-secondary-container text-secondary">
                        <AppIcon name="check_circle" class="text-2xl" />
                    </div>
                    <div class="flex-1">
                        <p class="text-base font-bold text-primary">{{ flashEntry.message }}</p>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            <span class="font-semibold">{{ flashEntry.entry.transaction_date }}</span>
                            <span v-if="flashEntry.entry.journal_number"> · No. {{ flashEntry.entry.journal_number }}</span>
                            · {{ transactionTypeLabel(flashEntry.entry.transaction_type) }}
                        </p>
                        <p v-if="flashEntry.entry.description" class="mt-1 text-sm text-on-surface-variant">{{ flashEntry.entry.description }}</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-secondary/30 bg-secondary-container/40 p-4">
                        <div class="mb-3 flex items-center gap-2">
                            <AppIcon name="arrow_downward" class="text-secondary" />
                            <p class="text-xs font-bold uppercase tracking-wider text-secondary">Akun Bertambah</p>
                        </div>
                        <ul class="space-y-2">
                            <li v-for="line in debitedLines" :key="`d-${line.account_code}`" class="rounded-lg bg-surface-container-lowest p-3">
                                <p class="text-sm font-bold text-primary">{{ line.account_code }} · {{ line.account_name }}</p>
                                <p class="mt-1 text-base font-bold text-secondary">{{ currency(line.debit) }}</p>
                            </li>
                            <li v-if="!debitedLines.length" class="text-sm text-on-surface-variant">Tidak ada akun debit.</li>
                        </ul>
                    </div>
                    <div class="rounded-xl border border-error/30 bg-error-container/40 p-4">
                        <div class="mb-3 flex items-center gap-2">
                            <AppIcon name="arrow_outward" class="text-error" />
                            <p class="text-xs font-bold uppercase tracking-wider text-error">Akun Berkurang</p>
                        </div>
                        <ul class="space-y-2">
                            <li v-for="line in creditedLines" :key="`c-${line.account_code}`" class="rounded-lg bg-surface-container-lowest p-3">
                                <p class="text-sm font-bold text-primary">{{ line.account_code }} · {{ line.account_name }}</p>
                                <p class="mt-1 text-base font-bold text-error">{{ currency(line.credit) }}</p>
                            </li>
                            <li v-if="!creditedLines.length" class="text-sm text-on-surface-variant">Tidak ada akun kredit.</li>
                        </ul>
                    </div>
                </div>
            </AppCard>

            <AppCard v-else-if="page.props.flash?.success">
                <p class="text-sm text-primary">{{ page.props.flash.success }}</p>
            </AppCard>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppDatePicker v-model="form.transaction_date" label="Tanggal Transaksi" required :error="form.errors.transaction_date" />
                        <SmartSelect v-model="form.transaction_type" :options="props.transactionTypes" label="Jenis Transaksi" placeholder="Pilih jenis transaksi" required :error="form.errors.transaction_type" />
                    </div>

                    <AppTextarea
                        v-if="!isInventory"
                        v-model="form.description"
                        label="Keterangan / Deskripsi"
                        required
                        :error="form.errors.description"
                        placeholder="Contoh: Pencatatan biaya operasional bulanan"
                    />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <SmartSelect v-model="form.sumber_dana_row_id" :options="sumberDanaOptions" :label="sumberDanaLabel + ' (Kredit)'" placeholder="Pilih akun" :disabled="!currentType" required :error="form.errors.sumber_dana_row_id" />
                        <SmartSelect v-model="form.disimpan_ke_row_id" :options="disimpanKeOptions" :label="disimpanKeLabel + ' (Debit)'" placeholder="Pilih akun" :disabled="!currentType" required :error="form.errors.disimpan_ke_row_id" />
                    </div>

                    <p v-if="form.errors.sumber_dana_row_id || form.errors.disimpan_ke_row_id" class="text-sm text-error">{{ form.errors.sumber_dana_row_id || form.errors.disimpan_ke_row_id }}</p>

                    <template v-if="isInventory">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <AppInput v-model="form.reference" label="Relasi" :error="form.errors.reference" placeholder="No referensi / vendor" />
                            <AppInput v-model="form.asset_name" label="Nama Barang" required :error="form.errors.asset_name" placeholder="Contoh: Laptop" />
                            <AppInput v-model="form.asset_quantity" label="Jml. Unit" type="number" min="1" required :error="form.errors.asset_quantity" placeholder="1" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <AppCurrencyInput v-model="form.asset_unit_cost" label="Harga Satuan" icon="payments" :min="1" required :error="form.errors.asset_unit_cost" placeholder="0" />
                            <AppInput
                                v-model="form.asset_useful_life_months"
                                label="Umur Eko. (bulan)"
                                type="number"
                                :min="currentType === 'pembelian_aset_tanah' ? 0 : 1"
                                required
                                :error="form.errors.asset_useful_life_months"
                                :placeholder="currentType === 'pembelian_aset_tanah' ? '0' : '48'"
                                :hint="currentType === 'pembelian_aset_tanah' ? 'Tanah: 0 = tidak disusutkan' : null"
                            />
                            <AppCurrencyInput v-model="form.amount" label="Harga Perolehan" icon="payments" :min="1" required readonly :error="form.errors.amount" placeholder="0" hint="Otomatis: unit × harga satuan" />
                        </div>
                    </template>

                    <template v-else>
                        <AppInput v-model="form.reference" label="Relasi (opsional)" :error="form.errors.reference" placeholder="No referensi / catatan tambahan" />
                        <AppCurrencyInput v-model="form.amount" label="Nominal" icon="payments" :min="1" required :error="form.errors.amount" placeholder="0" />
                    </template>

                    <div class="flex justify-end gap-3 border-t border-outline-variant pt-4">
                        <a :href="pagePath"><AppButton variant="secondary" type="button">Reset</AppButton></a>
                        <AppButton type="submit" :loading="form.processing" :disabled="form.processing" icon="save">Catat Jurnal</AppButton>
                    </div>
                </form>
            </AppCard>

            <AppCard>
                <template #header>
                    <div>
                        <h2 class="text-xl font-bold text-primary">Riwayat Transaksi Akun</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">Lihat pergerakan debit/kredit suatu akun pada periode tertentu.</p>
                    </div>
                </template>

                <form class="space-y-5" @submit.prevent="fetchHistory">
                    <div class="grid gap-4 lg:grid-cols-3">
                        <SmartSelect v-model="historyForm.account_row_id" :options="props.accountOptions" label="Akun" placeholder="Pilih akun" required searchable search-placeholder="Cari akun..." />
                        <AppRadioGroup v-model="historyForm.period" :options="periodOptions" label="Periode" required />
                        <AppDatePicker
                            :key="historyForm.period"
                            v-model="periodValue"
                            :mode="historyForm.period === 'daily' ? 'date' : historyForm.period === 'monthly' ? 'month' : 'year'"
                            :label="periodFieldLabel"
                        />
                    </div>

                    <div class="flex justify-end gap-3 border-t border-outline-variant pt-4">
                        <AppButton variant="secondary" type="button" icon="restart_alt" @click="resetHistory">Reset</AppButton>
                        <AppButton type="submit" :loading="historyLoading" icon="search">Tampilkan</AppButton>
                    </div>
                </form>

                <AppEmptyState v-if="!props.history?.account" class="mt-6" icon="database" title="Pilih akun & periode" description="Pilih akun dan rentang waktu untuk melihat pergerakan debit/kredit." />
            </AppCard>
        </div>

        <AppModal v-model="historyModalOpen" :title="`Riwayat · ${props.history?.account?.code ?? ''}`" size="full" @update:model-value="closeHistoryModal">
            <div v-if="props.history?.account" class="space-y-4">
                <p class="text-sm text-on-surface-variant">{{ props.history.account.name }} · {{ props.history.account.account_type }} · Saldo Normal {{ props.history.account.normal_balance === 'D' ? 'Debit' : 'Kredit' }}</p>
                <p class="text-xs text-on-surface-variant">{{ periodDetail }}<span v-if="historyRange"> · {{ historyRange.start }} → {{ historyRange.end }}</span></p>

                <div v-if="historySummary" class="grid gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Transaksi</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ historySummary.count }}</p>
                    </div>
                    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Debit</p>
                        <p class="mt-1 text-xl font-bold text-secondary">{{ currency(historySummary.totalDebit) }}</p>
                    </div>
                    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Kredit</p>
                        <p class="mt-1 text-xl font-bold text-error">{{ currency(historySummary.totalCredit) }}</p>
                    </div>
                    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Saldo Akhir</p>
                        <p class="mt-1 text-xl font-bold text-primary">{{ currency(historySummary.closingBalance) }}</p>
                    </div>
                </div>

                <div v-if="historyRows.length" class="overflow-x-auto rounded-xl border border-outline-variant">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs font-bold uppercase tracking-wider text-primary">
                            <tr>
                                <th class="px-4 py-3">Nomor</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Trx ID</th>
                                <th class="px-4 py-3">Keterangan</th>
                                <th class="px-4 py-3 text-right">Debit</th>
                                <th class="px-4 py-3 text-right">Kredit</th>
                                <th class="px-4 py-3 text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in historyRows" :key="`${row.entry_public_id}-${index}`" class="border-t border-outline-variant">
                                <td class="px-4 py-3 font-bold text-primary">{{ row.row_sequence }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ row.transaction_date }}</td>
                                <td class="px-4 py-3"><a v-if="row.entry_public_id" :href="entryHref(row.entry_public_id)" class="font-semibold text-primary hover:underline">{{ row.entry_id }}</a><span v-else class="text-on-surface-variant">—</span></td>
                                <td class="px-4 py-3">{{ row.description || '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-secondary">{{ row.debit > 0 ? currency(row.debit) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-error">{{ row.credit > 0 ? currency(row.credit) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-bold text-primary">{{ currency(row.running_balance) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <AppEmptyState v-else icon="history" title="Belum ada transaksi" :description="`Tidak ada jurnal terposting pada akun ini di periode ${periodDetail}.`" />
            </div>
        </AppModal>
    </AuthenticatedLayout>
</template>
