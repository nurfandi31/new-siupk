<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCheckbox from '../../../Components/AppCheckbox.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
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

// Multi-select state
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

const cashEvidenceLabels = {
    BKM: 'BKM',
    BKK: 'BKK',
    BM: 'BM',
};
function cashEvidenceLabel(kind) {
    return cashEvidenceLabels[kind] || 'Bukti';
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
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Transaksi</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Daftar Jurnal</h1>
                    <p class="text-sm text-on-surface-variant">Jurnal posted. Koreksi / hapus transaksi lewat reverse (immutable).</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a v-if="can('journals.create')" href="/accounting/journal-entries/create">
                        <AppButton variant="secondary" icon="add" size="compact">Jurnal Umum</AppButton>
                    </a>
                    <a v-if="can('installments.record')" href="/accounting/journal-entries/installment">
                        <AppButton icon="payments" size="compact">Jurnal Angsuran</AppButton>
                    </a>
                </div>
            </header>

            <AppCard>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <AppDatePicker v-model="from" mode="day" label="Dari tanggal" />
                    <AppDatePicker v-model="to" mode="day" label="Sampai tanggal" />
                    <SmartSelect v-model="source" label="Sumber jurnal" :options="sourceOptions" />
                    <AppInput
                        v-model="q"
                        label="Cari"
                        icon="search"
                        placeholder="No. jurnal, ID, atau uraian…"
                    />
                </div>
            </AppCard>

            <!-- Floating / Inline Bulk Action Toolbar -->
            <div
                v-if="allowReverse && selectedRowIds.length > 0"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-error/30 bg-error-container/20 px-4 py-3 shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <span class="inline-flex size-7 items-center justify-center rounded-full bg-error text-xs font-bold text-on-error">
                        {{ selectedRowIds.length }}
                    </span>
                    <div>
                        <p class="text-sm font-bold text-primary">{{ selectedRowIds.length }} transaksi terpilih</p>
                        <p class="text-xs text-on-surface-variant">Total: {{ formatMoney(selectedTotalAmount) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <AppButton
                        type="button"
                        variant="ghost"
                        size="compact"
                        @click="selectedRowIds = []"
                    >
                        Batal Pilih
                    </AppButton>
                    <AppButton
                        type="button"
                        variant="danger"
                        size="compact"
                        icon="delete_sweep"
                        @click="openBulkReverse"
                    >
                        Hapus (Reverse) Terpilih
                    </AppButton>
                </div>
            </div>

            <AppCard :padded="false">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-outline-variant bg-surface-container-low text-on-surface-variant">
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
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-if="rows.length === 0">
                                <td :colspan="allowReverse ? 8 : 7" class="px-4 py-12 text-center text-on-surface-variant">
                                    Tidak ada transaksi jurnal untuk periode ini.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.row_id"
                                class="transition-colors hover:bg-surface-container-low/40"
                                :class="selectedRowIds.includes(row.row_id) ? 'bg-primary-container/10' : ''"
                            >
                                <td v-if="allowReverse" class="w-10 px-3 py-3 text-center">
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
                                <td class="whitespace-nowrap px-4 py-3 align-top text-xs text-on-surface-variant">
                                    {{ formatDate(row.transaction_date) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 align-top text-xs">
                                    <span class="font-medium text-primary">{{ sourceLabel(row.source_type) }}</span>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <p class="font-medium text-primary">{{ row.description || '—' }}</p>
                                    <p v-if="row.is_reversal && row.source_row_id" class="mt-0.5 text-xs text-on-surface-variant">
                                        Pembalik dari jurnal <span class="font-semibold text-primary">#{{ row.source_row_id }}</span>
                                    </p>
                                    <p v-else-if="row.already_reversed && row.reversal" class="mt-0.5 text-xs text-error">
                                        Telah dibatalkan oleh jurnal <span class="font-semibold">#{{ row.reversal.id }}</span>
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right align-top font-mono text-sm font-semibold text-primary">
                                    {{ formatMoney(row.amount) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center align-top">
                                    <AppBadge v-if="row.is_reversal" tone="warning">Reversal</AppBadge>
                                    <AppBadge v-else-if="row.already_reversed" tone="error">Reversed</AppBadge>
                                    <AppBadge v-else tone="success">Posted</AppBadge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right align-top">
                                    <div class="inline-flex items-center gap-1">
                                        <a
                                            v-if="row.receipt_url"
                                            :href="row.receipt_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="rounded-lg px-2 py-1 text-xs font-semibold text-primary hover:bg-primary/10"
                                            title="Cetak Kuitansi Angsuran"
                                        >
                                            Kuitansi
                                        </a>
                                        <a
                                            v-if="!row.receipt_url && row.cash_evidence_url"
                                            :href="row.cash_evidence_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="rounded-lg px-2 py-1 text-xs font-semibold text-primary hover:bg-primary/10"
                                            :title="`Cetak ${cashEvidenceLabel(row.cash_evidence_kind)}`"
                                        >
                                            {{ cashEvidenceLabel(row.cash_evidence_kind) }}
                                        </a>
                                        <a
                                            v-if="allowReverse && row.can_edit"
                                            :href="`/accounting/journals/${row.row_id}/edit`"
                                            class="rounded-lg px-2 py-1 text-xs font-semibold text-warning hover:bg-warning/10"
                                            title="Koreksi jurnal (reverse + buat baru)"
                                        >
                                            Edit
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

                <div
                    v-if="pagination.last_page > 1"
                    class="flex items-center justify-between border-t border-outline-variant px-4 py-3 text-sm"
                >
                    <p class="text-on-surface-variant">
                        {{ pagination.total }} jurnal · hlm {{ pagination.page }}/{{ pagination.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <AppButton
                            size="compact"
                            variant="secondary"
                            :disabled="pagination.page <= 1"
                            @click="apply(pagination.page - 1)"
                        >
                            Prev
                        </AppButton>
                        <AppButton
                            size="compact"
                            variant="secondary"
                            :disabled="pagination.page >= pagination.last_page"
                            @click="apply(pagination.page + 1)"
                        >
                            Next
                        </AppButton>
                    </div>
                </div>
            </AppCard>

            <!-- Single Reverse Modal -->
            <AppModal v-model="reverseOpen" title="Hapus transaksi (reverse)">
                <form class="space-y-4" @submit.prevent="submitReverse">
                    <p class="text-sm text-on-surface-variant">
                        Jurnal <span class="font-semibold text-primary">#{{ reverseTarget?.id }}</span>
                        tidak dihapus permanen dari basis data. Sistem akan membuat jurnal lawan pembalik (reverse: debit↔kredit) untuk menjaga kepatuhan audit.
                    </p>
                    <p v-if="reverseTarget" class="rounded-lg bg-surface-container-low px-3 py-2 text-sm">
                        {{ reverseTarget.description || '—' }}
                        · {{ formatMoney(reverseTarget.amount) }}
                    </p>
                    <AppDatePicker v-model="reverseForm.reversal_date" mode="day" label="Tanggal reverse" required />
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
                    <p class="text-sm text-on-surface-variant">
                        Sebanyak <span class="font-semibold text-primary">{{ selectedRowIds.length }} transaksi</span>
                        (Total nominal <span class="font-semibold text-primary">{{ formatMoney(selectedTotalAmount) }}</span>)
                        akan dibatalkan secara bersamaan dengan membuat jurnal lawan (reverse). Data transaksi asli tetap tersimpan demi audit trail.
                    </p>
                    <AppDatePicker v-model="bulkReverseForm.reversal_date" mode="day" label="Tanggal reverse" required />
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
