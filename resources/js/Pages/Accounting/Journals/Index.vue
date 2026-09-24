<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCheckbox from '../../../Components/AppCheckbox.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import AppFilterPill from '../../../Components/AppFilterPill.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppModal from '../../../Components/AppModal.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';

const { can } = useCan();

const props = defineProps({
    rows: { type: Array, required: true },
    pagination: { type: Object, required: true },
    filters: { type: Object, required: true },
    sourceOptions: { type: Array, required: true },
    can_reverse: { type: Boolean, default: false },
});

const allowReverse = computed(() => props.can_reverse && can('journals.create'));

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const q = ref(props.filters.q || '');
const source = ref(props.filters.source || 'all');
const syncing = ref(false);

const selectedRowIds = ref([]);

const reversableRowsOnPage = computed(() => props.rows.filter((r) => r.can_reverse));
const allSelected = computed(() =>
    reversableRowsOnPage.value.length > 0 &&
    reversableRowsOnPage.value.every((r) => selectedRowIds.value.includes(r.row_id)),
);
const isIndeterminate = computed(() =>
    selectedRowIds.value.length > 0 &&
    !allSelected.value &&
    reversableRowsOnPage.value.some((r) => selectedRowIds.value.includes(r.row_id)),
);

function toggleSelectAll(checked) {
    if (checked) {
        const idsToAdd = reversableRowsOnPage.value.map((r) => r.row_id);
        selectedRowIds.value = Array.from(new Set([...selectedRowIds.value, ...idsToAdd]));
    } else {
        const pageIds = new Set(reversableRowsOnPage.value.map((r) => r.row_id));
        selectedRowIds.value = selectedRowIds.value.filter((id) => !pageIds.has(id));
    }
}

const selectedTotalAmount = computed(() =>
    props.rows
        .filter((r) => selectedRowIds.value.includes(r.row_id))
        .reduce((sum, r) => sum + Number(r.amount || 0), 0),
);

watch(
    () => props.filters,
    (f) => {
        syncing.value = true;
        from.value = f.from;
        to.value = f.to;
        q.value = f.q || '';
        source.value = f.source || 'all';
        queueMicrotask(() => {
            syncing.value = false;
        });
    },
    { deep: true },
);

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}
function formatDate(v) {
    if (!v) return '—';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return v;
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
}

const cashEvidenceLabels = { BKM: 'BKM', BKK: 'BKK', BM: 'BM' };
function cashEvidenceLabel(kind) {
    return cashEvidenceLabels[kind] || 'Bukti';
}

// Quick date range presets
const todayIso = new Date().toISOString().slice(0, 10);
function isoDate(d) {
    return new Date(d).toISOString().slice(0, 10);
}
const sevenDaysAgo = isoDate(Date.now() - 6 * 24 * 60 * 60 * 1000);
const monthStart = isoDate(new Date(new Date().getFullYear(), new Date().getMonth(), 1));

const quickRanges = [
    { value: 'today', label: 'Hari ini', from: todayIso, to: todayIso },
    { value: '7d', label: '7 hari', from: sevenDaysAgo, to: todayIso },
    { value: 'mtd', label: 'Bulan ini', from: monthStart, to: todayIso },
];
const activeQuickRange = ref(null);
watch([from, to], ([f, t]) => {
    const match = quickRanges.find((r) => r.from === f && r.to === t);
    activeQuickRange.value = match ? match.value : 'custom';
});
function applyQuickRange(range) {
    from.value = range.from;
    to.value = range.to;
    activeQuickRange.value = range.value;
}

function apply(page = 1) {
    if (syncing.value) return;
    router.get(
        '/accounting/journals',
        {
            from: from.value,
            to: to.value,
            q: q.value || undefined,
            source: source.value === 'all' ? undefined : source.value,
            page,
        },
        { preserveState: false, preserveScroll: true, replace: true },
    );
}

let searchTimer;
watch(q, () => {
    if (syncing.value) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => apply(1), 350);
});
watch([from, to, source], () => {
    if (syncing.value) return;
    apply(1);
});

const sourceLabel = computed(() => {
    const map = Object.fromEntries(props.sourceOptions.map((o) => [o.value, o.label]));
    return (value) => map[value] || value || '—';
});

const pageStart = computed(() => {
    if (props.pagination.total === 0) return 0;
    return (props.pagination.page - 1) * props.pagination.per_page + 1;
});
const pageEnd = computed(() => {
    if (props.pagination.total === 0) return 0;
    return Math.min(props.pagination.page * props.pagination.per_page, props.pagination.total);
});

const showPagination = computed(
    () => props.pagination.last_page > 1 || props.pagination.total > 0,
);

// Status pill config (dot + tone)
const statusConfig = computed(() => ({
    posted: { label: 'Posted', tone: 'success-soft', dot: 'bg-secondary' },
    reversal: { label: 'Reversal', tone: 'warning-soft', dot: 'bg-tertiary' },
    reversed: { label: 'Reversed', tone: 'error-soft', dot: 'bg-error' },
}));
function rowStatus(row) {
    if (row.is_reversal) return statusConfig.value.reversal;
    if (row.already_reversed) return statusConfig.value.reversed;
    return statusConfig.value.posted;
}

// Single Reverse modal
const reverseOpen = ref(false);
const reverseTarget = ref(null);
const reverseForm = useForm({
    reversal_date: new Date().toISOString().slice(0, 10),
    reason: '',
});

function openReverse(row) {
    reverseTarget.value = row;
    reverseForm.reversal_date = new Date().toISOString().slice(0, 10);
    reverseForm.reason = `Pembatalan jurnal #${row.id}`;
    reverseForm.clearErrors();
    reverseOpen.value = true;
}

function submitReverse() {
    if (!reverseTarget.value) return;
    reverseForm.post(`/accounting/journals/${reverseTarget.value.row_id}/reverse`, {
        preserveScroll: true,
        onSuccess: () => {
            reverseOpen.value = false;
            reverseTarget.value = null;
            selectedRowIds.value = selectedRowIds.value.filter((id) => id !== reverseTarget.value?.row_id);
        },
    });
}

// Bulk Reverse modal
const bulkReverseOpen = ref(false);
const bulkReverseForm = useForm({
    entry_ids: [],
    reversal_date: new Date().toISOString().slice(0, 10),
    reason: '',
});

function openBulkReverse() {
    if (selectedRowIds.value.length === 0) return;
    bulkReverseForm.entry_ids = [...selectedRowIds.value];
    bulkReverseForm.reversal_date = new Date().toISOString().slice(0, 10);
    bulkReverseForm.reason = `Pembatalan massal ${selectedRowIds.value.length} transaksi`;
    bulkReverseForm.clearErrors();
    bulkReverseOpen.value = true;
}

function submitBulkReverse() {
    bulkReverseForm.entry_ids = [...selectedRowIds.value];
    bulkReverseForm.post('/accounting/journals/bulk-reverse', {
        preserveScroll: true,
        onSuccess: () => {
            bulkReverseOpen.value = false;
            selectedRowIds.value = [];
        },
    });
}
</script>

<template>
    <Head title="Daftar Jurnal" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Hero header with gradient -->
            <header
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-container via-surface-container-lowest to-secondary-container p-6 ring-1 ring-outline-variant/40 sm:p-8"
            >
                <div
                    class="pointer-events-none absolute -right-16 -top-16 size-48 rounded-full bg-primary/20 blur-3xl"
                    aria-hidden="true"
                ></div>
                <div
                    class="pointer-events-none absolute -bottom-20 -left-10 size-56 rounded-full bg-secondary/15 blur-3xl"
                    aria-hidden="true"
                ></div>

                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex size-8 items-center justify-center rounded-lg bg-primary text-on-primary shadow-sm">
                                <AppIcon name="receipt_long" class="text-base" />
                            </span>
                            <h1 class="text-2xl font-bold text-on-surface sm:text-3xl">Daftar Jurnal</h1>
                        </div>
                        <p class="mt-2 max-w-2xl text-sm text-on-surface-variant">
                            Semua jurnal posted bersifat immutable. Koreksi atau hapus transaksi lewat jurnal pembalik (reverse) demi audit trail.
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-2 self-end lg:self-auto">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">Transaksi</p>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <a v-if="can('journals.create')" href="/accounting/journal-entries/create">
                                <AppButton variant="secondary" icon="add" size="compact">Jurnal Umum</AppButton>
                            </a>
                            <a v-if="can('installments.record')" href="/accounting/journal-entries/installment">
                                <AppButton variant="secondary" icon="payments" size="compact">Angsuran Kelompok</AppButton>
                            </a>
                            <a v-if="can('installments.record')" href="/accounting/journal-entries/installment-individual">
                                <AppButton variant="secondary" icon="person" size="compact">Angsuran Individu</AppButton>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Filter Bar -->
            <AppCard class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Periode</p>
                    <AppFilterPill
                        v-model="activeQuickRange"
                        :items="[
                            ...quickRanges.map((r) => ({ value: r.value, label: r.label })),
                            { value: 'custom', label: 'Kustom', disabled: true },
                        ]"
                        variant="outline"
                        size="compact"
                        aria-label="Pilih rentang cepat"
                        @update:model-value="(v) => { const r = quickRanges.find((x) => x.value === v); if (r) applyQuickRange(r); }"
                    />
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <AppDatePicker v-model="from" mode="date" label="Dari tanggal" />
                    <AppDatePicker v-model="to" mode="date" label="Sampai tanggal" />
                    <SmartSelect v-model="source" label="Sumber jurnal" :options="sourceOptions" />
                    <AppInput
                        v-model="q"
                        label="Cari"
                        icon="search"
                        placeholder="No. jurnal, ID, atau uraian…"
                    />
                </div>
            </AppCard>

            <!-- Floating Bulk Action Toolbar -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div
                    v-if="allowReverse && selectedRowIds.length > 0"
                    class="sticky top-3 z-20 flex flex-col gap-3 rounded-2xl border border-error/40 bg-error-container/30 px-4 py-3 shadow-lg shadow-error/10 backdrop-blur sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-3">
                        <span class="inline-flex size-10 items-center justify-center rounded-full bg-error text-sm font-bold text-on-error shadow-md">
                            {{ selectedRowIds.length }}
                        </span>
                        <div>
                            <p class="text-sm font-bold text-on-surface">{{ selectedRowIds.length }} transaksi dipilih</p>
                            <p class="text-xs text-on-surface-variant">
                                Total nominal · <span class="font-semibold text-on-surface tabular-nums">{{ formatMoney(selectedTotalAmount) }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <AppButton type="button" variant="ghost" size="compact" @click="selectedRowIds = []">
                            Batal
                        </AppButton>
                        <AppButton type="button" variant="danger" size="compact" icon="delete_sweep" @click="openBulkReverse">
                            Hapus (Reverse) Terpilih
                        </AppButton>
                    </div>
                </div>
            </Transition>

            <!-- Journal Table -->
            <AppCard :padded="false" class="overflow-hidden">
                <div class="flex items-center justify-between border-b border-outline-variant/60 px-4 py-3 sm:px-5">
                    <div>
                        <h2 class="text-sm font-bold text-on-surface">Daftar Transaksi</h2>
                        <p class="text-xs text-on-surface-variant">
                            Diurutkan dari tanggal terbaru · klik baris untuk melihat detail jurnal
                        </p>
                    </div>
                    <span class="hidden text-xs text-on-surface-variant sm:inline">
                        {{ rows.length }} baris pada halaman ini
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-surface-container-low text-left text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th v-if="allowReverse" class="w-10 px-3 py-3 text-center">
                                    <AppCheckbox
                                        :model-value="allSelected"
                                        :indeterminate="isIndeterminate"
                                        :disabled="reversableRowsOnPage.length === 0"
                                        aria-label="Pilih semua di halaman ini"
                                        @update:model-value="toggleSelectAll"
                                    />
                                </th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">No / ID</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Tanggal</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Sumber</th>
                                <th class="px-4 py-3 font-semibold">Uraian</th>
                                <th class="whitespace-nowrap px-4 py-3 text-right font-semibold">Nominal</th>
                                <th class="whitespace-nowrap px-4 py-3 text-center font-semibold">Status</th>
                                <th class="whitespace-nowrap px-4 py-3 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td :colspan="allowReverse ? 8 : 7" class="px-4 py-10">
                                    <AppEmptyState
                                        icon="search_off"
                                        title="Tidak ada transaksi jurnal"
                                        description="Coba ubah rentang tanggal, hapus filter pencarian, atau pilih sumber jurnal lain."
                                    />
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.row_id"
                                class="border-t border-outline-variant/40 transition-colors hover:bg-primary-container/10"
                                :class="selectedRowIds.includes(row.row_id) ? 'bg-primary-container/20' : ''"
                            >
                                <td v-if="allowReverse" class="w-10 px-3 py-3 text-center align-middle">
                                    <AppCheckbox
                                        v-if="row.can_reverse"
                                        v-model="selectedRowIds"
                                        :value="row.row_id"
                                        :aria-label="`Pilih jurnal #${row.id}`"
                                    />
                                    <span v-else class="text-xs text-outline">—</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 align-top font-mono text-xs">
                                    <span class="font-bold text-primary">{{ row.journal_number }}</span>
                                    <span class="block text-[11px] text-on-surface-variant">#{{ row.id }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 align-top text-xs text-on-surface-variant tabular-nums">
                                    {{ formatDate(row.transaction_date) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 align-top text-xs">
                                    <span class="font-semibold text-on-surface">{{ sourceLabel(row.source_type) }}</span>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <p class="font-medium text-on-surface">{{ row.description || '—' }}</p>
                                    <p v-if="row.is_reversal && row.source_row_id" class="mt-0.5 text-xs text-on-surface-variant">
                                        Pembalik dari jurnal
                                        <span class="font-semibold text-primary">#{{ row.source_row_id }}</span>
                                    </p>
                                    <p v-else-if="row.already_reversed && row.reversal" class="mt-0.5 text-xs text-error">
                                        Telah dibatalkan oleh jurnal
                                        <span class="font-semibold">#{{ row.reversal.id }}</span>
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right align-top font-mono text-sm font-bold tabular-nums text-on-surface">
                                    {{ formatMoney(row.amount) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center align-top">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold"
                                        :class="rowStatus(row).tone === 'success-soft' ? 'bg-secondary-container/40 text-secondary' : rowStatus(row).tone === 'warning-soft' ? 'bg-tertiary-fixed/40 text-tertiary' : 'bg-error-container/40 text-error'"
                                    >
                                        <span class="size-1.5 rounded-full" :class="rowStatus(row).dot" aria-hidden="true"></span>
                                        {{ rowStatus(row).label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right align-top">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1">
                                        <a
                                            v-if="row.receipt_url"
                                            :href="row.receipt_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-primary hover:bg-primary/10"
                                            title="Cetak Kuitansi Angsuran"
                                        >
                                            <AppIcon name="description" class="text-base" />
                                            <span>Kuitansi</span>
                                        </a>
                                        <a
                                            v-if="!row.receipt_url && row.cash_evidence_url"
                                            :href="row.cash_evidence_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-primary hover:bg-primary/10"
                                            :title="`Cetak ${cashEvidenceLabel(row.cash_evidence_kind)}`"
                                        >
                                            <AppIcon name="print" class="text-base" />
                                            <span>{{ cashEvidenceLabel(row.cash_evidence_kind) }}</span>
                                        </a>
                                        <a
                                            v-if="allowReverse && row.can_edit"
                                            :href="`/accounting/journals/${row.row_id}/edit`"
                                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-tertiary hover:bg-tertiary/10"
                                            title="Koreksi jurnal (reverse + buat baru)"
                                        >
                                            <AppIcon name="edit" class="text-base" />
                                            <span>Edit</span>
                                        </a>
                                        <AppButton
                                            v-if="allowReverse && row.can_reverse"
                                            type="button"
                                            variant="ghost"
                                            size="compact"
                                            icon="undo"
                                            class="!min-h-0 !px-2 !text-error"
                                            title="Hapus / batalkan transaksi lewat jurnal pembalik"
                                            @click="openReverse(row)"
                                        >
                                            Hapus
                                        </AppButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="showPagination"
                    class="flex flex-col gap-3 border-t border-outline-variant/60 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-5"
                >
                    <p class="text-on-surface-variant">
                        <span v-if="pagination.total > 0">
                            Menampilkan <span class="font-semibold text-on-surface">{{ pageStart }}–{{ pageEnd }}</span>
                            dari <span class="font-semibold text-on-surface">{{ pagination.total }}</span> jurnal
                        </span>
                        <span v-else>Tidak ada jurnal</span>
                        <span class="text-outline"> · hlm {{ pagination.page }}/{{ pagination.last_page }}</span>
                    </p>
                    <div class="flex items-center gap-2">
                        <AppButton
                            size="compact"
                            variant="secondary"
                            icon="chevron_left"
                            :disabled="pagination.page <= 1"
                            @click="apply(pagination.page - 1)"
                        >
                            Sebelumnya
                        </AppButton>
                        <AppButton
                            size="compact"
                            variant="secondary"
                            :disabled="pagination.page >= pagination.last_page"
                            @click="apply(pagination.page + 1)"
                        >
                            Berikutnya
                        </AppButton>
                    </div>
                </div>
            </AppCard>

            <!-- Single Reverse Modal -->
            <AppModal v-model="reverseOpen" title="Hapus transaksi (reverse)">
                <form class="space-y-4" @submit.prevent="submitReverse">
                    <div class="flex items-start gap-3 rounded-xl bg-error-container/30 p-3">
                        <AppIcon name="warning" tone="danger" :container-size="9" />
                        <p class="text-sm text-on-surface-variant">
                            Jurnal <span class="font-bold text-on-surface">#{{ reverseTarget?.id }}</span>
                            tidak dihapus permanen. Sistem akan membuat jurnal pembalik (debit ↔ kredit) untuk menjaga audit trail.
                        </p>
                    </div>
                    <p v-if="reverseTarget" class="rounded-lg bg-surface-container-low px-3 py-2 text-sm">
                        <span class="font-medium text-on-surface">{{ reverseTarget.description || '—' }}</span>
                        <span class="block text-xs text-on-surface-variant">
                            {{ formatDate(reverseTarget.transaction_date) }} · {{ formatMoney(reverseTarget.amount) }}
                        </span>
                    </p>
                    <AppDatePicker v-model="reverseForm.reversal_date" mode="date" label="Tanggal reverse" required />
                    <AppTextarea
                        v-model="reverseForm.reason"
                        label="Alasan pembatalan"
                        :error="reverseForm.errors.reason"
                        placeholder="Salah nominal / salah akun / transaksi dibatalkan…"
                    />
                    <div class="flex justify-end gap-2">
                        <AppButton type="button" variant="secondary" @click="reverseOpen = false">Batal</AppButton>
                        <AppButton type="submit" variant="danger" icon="undo" :loading="reverseForm.processing">
                            Hapus (Reverse)
                        </AppButton>
                    </div>
                </form>
            </AppModal>

            <!-- Bulk Reverse Modal -->
            <AppModal v-model="bulkReverseOpen" title="Hapus transaksi terpilih (reverse massal)">
                <form class="space-y-4" @submit.prevent="submitBulkReverse">
                    <div class="flex items-start gap-3 rounded-xl bg-error-container/30 p-3">
                        <AppIcon name="delete_sweep" tone="danger" :container-size="9" />
                        <p class="text-sm text-on-surface-variant">
                            <span class="font-bold text-on-surface">{{ selectedRowIds.length }} transaksi</span>
                            (Total nominal
                            <span class="font-bold text-on-surface tabular-nums">{{ formatMoney(selectedTotalAmount) }}</span>)
                            akan dibatalkan bersamaan dengan membuat jurnal pembalik. Data asli tetap tersimpan untuk audit.
                        </p>
                    </div>
                    <AppDatePicker v-model="bulkReverseForm.reversal_date" mode="date" label="Tanggal reverse" required />
                    <AppTextarea
                        v-model="bulkReverseForm.reason"
                        label="Alasan pembatalan massal"
                        :error="bulkReverseForm.errors.reason"
                        placeholder="Alasan pembatalan transaksi terpilih…"
                    />
                    <div class="flex justify-end gap-2">
                        <AppButton type="button" variant="secondary" @click="bulkReverseOpen = false">Batal</AppButton>
                        <AppButton type="submit" variant="danger" icon="delete_sweep" :loading="bulkReverseForm.processing">
                            Hapus {{ selectedRowIds.length }} Transaksi
                        </AppButton>
                    </div>
                </form>
            </AppModal>
        </div>
    </AuthenticatedLayout>
</template>
