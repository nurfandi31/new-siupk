<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../../Components/AppBadge.vue';
import AppButton from '../../../../Components/AppButton.vue';
import AppCard from '../../../../Components/AppCard.vue';
import AppCheckbox from '../../../../Components/AppCheckbox.vue';
import AppDatePicker from '../../../../Components/AppDatePicker.vue';
import AppIconButton from '../../../../Components/AppIconButton.vue';
import AppInput from '../../../../Components/AppInput.vue';
import AppModal from '../../../../Components/AppModal.vue';
import SmartSelect from '../../../../Components/SmartSelect.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { useMoney } from '../../../../composables/useMoney.js';
import { useI18n } from '../../../../i18n';

const props = defineProps({
    tenant: { type: Object, required: true },
    rows: { type: Array, required: true },
    stats: { type: Object, required: true },
    pagination: { type: Object, required: true },
    filters: { type: Object, required: true },
    sourceOptions: { type: Array, required: true },
    categoryOptions: { type: Array, required: true },
});

const { money } = useMoney();
const { t, locale } = useI18n();

const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');
const q = ref(props.filters.q || '');
const source = ref(props.filters.source || 'all');
const category = ref(props.filters.category || 'all');
const syncing = ref(false);

const selectedRowIds = ref([]);
const includeReversalPairs = ref(true);

const allSelected = computed(() =>
    props.rows.length > 0 && props.rows.every((r) => selectedRowIds.value.includes(r.row_id)),
);

const isIndeterminate = computed(() =>
    selectedRowIds.value.length > 0 &&
    !allSelected.value &&
    props.rows.some((r) => selectedRowIds.value.includes(r.row_id)),
);

function toggleSelectAll(checked) {
    if (checked) {
        const idsToAdd = props.rows.map((r) => r.row_id);
        selectedRowIds.value = Array.from(new Set([...selectedRowIds.value, ...idsToAdd]));
    } else {
        const pageIds = new Set(props.rows.map((r) => r.row_id));
        selectedRowIds.value = selectedRowIds.value.filter((id) => !pageIds.has(id));
    }
}

const selectedTotalAmount = computed(() =>
    props.rows
        .filter((r) => selectedRowIds.value.includes(r.row_id))
        .reduce((sum, r) => sum + Number(r.amount || 0), 0),
);

function apply(page = 1) {
    if (syncing.value) return;
    router.get(
        `/admin/tenants/${props.tenant.row_id}/data-purifier`,
        {
            from: from.value || undefined,
            to: to.value || undefined,
            q: q.value || undefined,
            source: source.value === 'all' ? undefined : source.value,
            category: category.value === 'all' ? undefined : category.value,
            page,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let searchTimer;
watch(q, () => {
    if (syncing.value) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => apply(1), 350);
});
watch([from, to, source, category], () => {
    if (syncing.value) return;
    apply(1);
});

function formatDate(v) {
    if (!v) return '—';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return v;
    return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
}

function formatDateOnly(v) {
    if (!v) return '—';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return v;
    return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
}

const sourceLabel = computed(() => {
    const map = Object.fromEntries(props.sourceOptions.map((o) => [o.value, o.label]));
    return (value) => map[value] || value || '—';
});

// Selective Purge Modal
const purgeModalOpen = ref(false);
const purgeForm = useForm({
    entry_ids: [],
    include_reversal_pairs: true,
});

function openPurgeModal() {
    if (selectedRowIds.value.length === 0) return;
    purgeForm.entry_ids = [...selectedRowIds.value];
    purgeForm.include_reversal_pairs = includeReversalPairs.value;
    purgeModalOpen.value = true;
}

function submitPurge() {
    purgeForm.entry_ids = [...selectedRowIds.value];
    purgeForm.include_reversal_pairs = includeReversalPairs.value;
    purgeForm.post(`/admin/tenants/${props.tenant.row_id}/data-purifier/purge`, {
        preserveScroll: true,
        onSuccess: () => {
            purgeModalOpen.value = false;
            selectedRowIds.value = [];
        },
    });
}

// Start Training Session
const startTrainingForm = useForm({});
function startTraining() {
    startTrainingForm.post(`/admin/tenants/${props.tenant.row_id}/data-purifier/start-training`, {
        preserveScroll: true,
    });
}

// End Training / Go-Live Modal
const endTrainingModalOpen = ref(false);
const endTrainingForm = useForm({
    purge_data: true,
});

function submitEndTraining() {
    endTrainingForm.post(`/admin/tenants/${props.tenant.row_id}/data-purifier/end-training`, {
        preserveScroll: true,
        onSuccess: () => {
            endTrainingModalOpen.value = false;
            selectedRowIds.value = [];
        },
    });
}

// Reset Training Modal
const resetModalOpen = ref(false);
const resetConfirmText = ref('');
const resetForm = useForm({});

function submitResetTraining() {
    resetForm.post(`/admin/tenants/${props.tenant.row_id}/data-purifier/reset-training`, {
        preserveScroll: true,
        onSuccess: () => {
            resetModalOpen.value = false;
            resetConfirmText.value = '';
            selectedRowIds.value = [];
        },
    });
}
</script>

<template>
    <Head :title="`Data Purifier - ${tenant.name}`" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div class="space-y-3">
                    <Link :href="`/admin/tenants/${tenant.row_id}`" class="inline-flex items-center gap-1 text-sm font-semibold text-primary transition hover:text-primary-container">
                        <AppIconButton name="arrow_back" size="sm" tone="primary" rounded="full" :aria-label="t('Kembali')" />
                        <span>{{ t('Kembali ke detail tenant') }}</span>
                    </Link>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold text-primary">{{ t('Data Purifier & Siklus Pelatihan') }}</h1>
                        <AppBadge :tone="tenant.is_training_mode ? 'warning' : 'success'">
                            {{ tenant.is_training_mode ? t('Mode Pelatihan') : t('Mode Live') }}
                        </AppBadge>
                    </div>
                    <p class="text-sm text-on-surface-variant">
                        {{ t('Tenant:') }} <span class="font-bold text-primary">{{ tenant.name }}</span> ({{ tenant.code }}) · {{ t('Isolasi dan pembersihan transaksi uji coba secara presisi.') }}
                    </p>
                </div>
            </header>

            <!-- Training Lifecycle Control Card -->
            <AppCard>
                <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-block size-3 rounded-full"
                                :class="tenant.is_training_mode ? 'bg-warning animate-pulse' : tenant.has_completed_training ? 'bg-secondary' : 'bg-outline'"
                            />
                            <h2 class="text-lg font-bold text-primary">
                                {{ t('Status:') }}
                                <span v-if="tenant.is_training_mode" class="text-warning">{{ t('Mode Pelatihan Aktif') }}</span>
                                <span v-else-if="tenant.has_completed_training" class="text-secondary">{{ t('Mode Live / Produksi (Pelatihan Selesai)') }}</span>
                                <span v-else class="text-on-surface-variant">{{ t('Belum Memulai Sesi Pelatihan') }}</span>
                            </h2>
                        </div>
                        <p v-if="tenant.is_training_mode" class="text-xs text-on-surface-variant">
                            {{ t('Sesi dimulai pada') }} <span class="font-semibold text-primary">{{ formatDate(tenant.training_started_at) }}</span>.
                            {{ t('Seluruh transaksi yang di-input pada periode ini tercatat sebagai data pelatihan (total: :count transaksi).', { count: stats.training_transactions_count }) }}
                        </p>
                        <p v-else-if="tenant.has_completed_training" class="text-xs text-on-surface-variant">
                            {{ t('Pelatihan diselesaikan pada') }} <span class="font-semibold text-primary">{{ formatDate(tenant.training_ended_at) }}</span>.
                            {{ t('Tenant saat ini beroperasi normal (Fitur reset transaksi terkunci demi keamanan data operasional).') }}
                        </p>
                        <p v-else class="text-xs text-on-surface-variant">
                            {{ t('Aktifkan Mode Pelatihan sebelum sesi pelatihan dimulai agar sistem dapat mencatat dan mengisolasi seluruh transaksi latihan.') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- If currently in training mode -->
                        <template v-if="tenant.is_training_mode">
                            <AppButton
                                variant="danger"
                                icon="restart_alt"
                                :disabled="stats.training_transactions_count === 0"
                                @click="resetModalOpen = true"
                            >
                                {{ t('Reset Transaksi Pelatihan') }}
                            </AppButton>
                            <AppButton
                                variant="success"
                                icon="check_circle"
                                @click="endTrainingModalOpen = true"
                            >
                                {{ t('Selesai & Masuk Mode Live') }}
                            </AppButton>
                        </template>

                        <!-- If completed training / Live -->
                        <template v-else-if="tenant.has_completed_training">
                            <AppButton
                                variant="secondary"
                                icon="school"
                                :loading="startTrainingForm.processing"
                                @click="startTraining"
                            >
                                {{ t('Buka Sesi Pelatihan Baru') }}
                            </AppButton>
                        </template>

                        <!-- If not started -->
                        <template v-else>
                            <AppButton
                                variant="primary"
                                icon="play_arrow"
                                :loading="startTrainingForm.processing"
                                @click="startTraining"
                            >
                                {{ t('Mulai Sesi Pelatihan') }}
                            </AppButton>
                        </template>
                    </div>
                </div>
            </AppCard>

            <!-- Stat Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('Total Transaksi') }}</p>
                    <p class="mt-2 text-3xl font-bold text-primary">{{ stats.total_transactions }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ t('Keseluruhan di database shard') }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-warning">{{ t('Transaksi Sesi Pelatihan') }}</p>
                    <p class="mt-2 text-3xl font-bold text-warning">{{ stats.training_transactions_count }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ t('Dalam batas waktu pelatihan') }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('Jurnal Reversal') }}</p>
                    <p class="mt-2 text-3xl font-bold text-error">{{ stats.reversal_count }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ t('Jurnal pembalik di buku besar') }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-secondary">{{ t('Saldo Awal & Master') }}</p>
                    <p class="mt-2 text-3xl font-bold text-secondary">{{ stats.opening_balances_count }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ t('100% Terlindungi & Utuh') }}</p>
                </AppCard>
            </div>

            <!-- Filters -->
            <AppCard>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <AppDatePicker v-model="from" mode="date" :label="t('Dari tanggal')" />
                    <AppDatePicker v-model="to" mode="date" :label="t('Sampai tanggal')" />
                    <SmartSelect v-model="category" :label="t('Kategori Data')" :options="categoryOptions" />
                    <SmartSelect v-model="source" :label="t('Sumber Jurnal')" :options="sourceOptions" />
                    <AppInput
                        v-model="q"
                        :label="t('Cari')"
                        icon="search"
                        :placeholder="t('Cari jurnal...')"
                    />
                </div>
            </AppCard>

            <!-- Bulk Action Toolbar -->
            <div
                v-if="selectedRowIds.length > 0"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-error/30 bg-error-container/20 px-4 py-3 shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <span class="inline-flex size-7 items-center justify-center rounded-full bg-error text-xs font-bold text-on-error">
                        {{ selectedRowIds.length }}
                    </span>
                    <div>
                        <p class="text-sm font-bold text-primary">{{ t(':count transaksi dipilih untuk di-purge', { count: selectedRowIds.length }) }}</p>
                        <p class="text-xs text-on-surface-variant">{{ t('Total nominal:') }} {{ money(selectedTotalAmount) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <label class="hidden items-center gap-2 text-xs text-primary sm:flex">
                        <input
                            v-model="includeReversalPairs"
                            type="checkbox"
                            class="size-4 rounded border-outline-variant accent-primary"
                        />
                        <span>{{ t('Hapus juga jurnal pembalik terkait') }}</span>
                    </label>
                    <AppButton
                        type="button"
                        variant="ghost"
                        size="compact"
                        @click="selectedRowIds = []"
                    >
                        {{ t('Batal') }}
                    </AppButton>
                    <AppButton
                        type="button"
                        variant="danger"
                        size="compact"
                        icon="delete_forever"
                        @click="openPurgeModal"
                    >
                        {{ t('Hapus Permanen Terpilih') }}
                    </AppButton>
                </div>
            </div>

            <!-- Transaction Table -->
            <AppCard :padded="false">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-outline-variant bg-surface-container-low text-on-surface-variant">
                            <tr>
                                <th class="w-10 px-3 py-3 text-center">
                                    <AppCheckbox
                                        :model-value="allSelected"
                                        :indeterminate="isIndeterminate"
                                        :disabled="rows.length === 0"
                                        :aria-label="t('Pilih semua di halaman ini')"
                                        @update:model-value="toggleSelectAll"
                                    />
                                </th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">{{ t('No / ID') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">{{ t('Tanggal') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">{{ t('Kategori') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">{{ t('Sumber') }}</th>
                                <th class="px-4 py-3 font-semibold">{{ t('Uraian') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-right font-semibold">{{ t('Nominal') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-center font-semibold">{{ t('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-if="rows.length === 0">
                                <td colspan="8" class="px-4 py-12 text-center text-on-surface-variant">
                                    {{ t('Tidak ada transaksi yang sesuai filter.') }}
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.row_id"
                                class="transition-colors hover:bg-surface-container-low/40"
                                :class="selectedRowIds.includes(row.row_id) ? 'bg-error-container/15' : ''"
                            >
                                <td class="w-10 px-3 py-3 text-center">
                                    <AppCheckbox
                                        v-model="selectedRowIds"
                                        :value="row.row_id"
                                        :aria-label="`${t('Pilih transaksi')} #${row.id}`"
                                    />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 align-top font-mono text-xs">
                                    <span class="font-bold text-primary">{{ row.journal_number }}</span>
                                    <span class="block text-[11px] text-on-surface-variant">#{{ row.id }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 align-top text-xs text-on-surface-variant">
                                    {{ formatDateOnly(row.transaction_date) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 align-top text-xs">
                                    <AppBadge v-if="row.is_training" tone="warning">{{ t('Sesi Pelatihan') }}</AppBadge>
                                    <AppBadge v-else-if="row.is_legacy" tone="neutral">{{ t('Legacy Migrasi') }}</AppBadge>
                                    <AppBadge v-else tone="success">{{ t('Produksi') }}</AppBadge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 align-top text-xs">
                                    <span class="font-medium text-primary">{{ sourceLabel(row.source_type) }}</span>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <p class="font-medium text-primary">{{ row.description || '—' }}</p>
                                    <p v-if="row.is_reversal && row.source_row_id" class="mt-0.5 text-xs text-on-surface-variant">
                                        {{ t('Pembalik dari jurnal') }} <span class="font-semibold text-primary">#{{ row.source_row_id }}</span>
                                    </p>
                                    <p v-else-if="row.already_reversed && row.reversal_id" class="mt-0.5 text-xs text-error">
                                        {{ t('Telah dibatalkan oleh jurnal') }} <span class="font-semibold">#{{ row.reversal_id }}</span>
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right align-top font-mono text-sm font-semibold text-primary">
                                    {{ money(row.amount) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center align-top">
                                    <AppBadge v-if="row.is_reversal" tone="warning">{{ t('Reversal') }}</AppBadge>
                                    <AppBadge v-else-if="row.already_reversed" tone="error">{{ t('Reversed') }}</AppBadge>
                                    <AppBadge v-else tone="success">{{ t('Posted') }}</AppBadge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="pagination.last_page > 1"
                    class="flex items-center justify-between border-t border-outline-variant px-4 py-3 text-sm"
                >
                    <p class="text-on-surface-variant">
                        {{ pagination.total }} {{ t('transaksi') }} · {{ t('hlm') }} {{ pagination.page }}/{{ pagination.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <AppButton
                            size="compact"
                            variant="secondary"
                            :disabled="pagination.page <= 1"
                            @click="apply(pagination.page - 1)"
                        >
                            {{ t('Prev') }}
                        </AppButton>
                        <AppButton
                            size="compact"
                            variant="secondary"
                            :disabled="pagination.page >= pagination.last_page"
                            @click="apply(pagination.page + 1)"
                        >
                            {{ t('Next') }}
                        </AppButton>
                    </div>
                </div>
            </AppCard>

            <!-- Modal Selective Purge -->
            <AppModal v-model="purgeModalOpen" :title="t('Konfirmasi Hard Delete (Purge Transaksi)')">
                <form class="space-y-4" @submit.prevent="submitPurge">
                    <div class="rounded-xl border border-error/40 bg-error-container/20 p-4">
                        <p class="text-sm font-semibold text-error">{{ t('Peringatan: Penghapusan Permanen (Hard Delete)') }}</p>
                        <p class="mt-1 text-xs text-on-surface-variant">
                            {{ t('Sebanyak') }} <span class="font-bold text-primary">{{ selectedRowIds.length }} {{ t('transaksi') }}</span>
                            {{ t('akan') }} <strong>{{ t('dihapus bersih dari basis data') }}</strong> {{ t('(termasuk baris jurnal dan catatan angsuran).') }}
                            {{ t('Transaksi ini tidak akan muncul lagi di buku besar maupun laporan keuangan.') }}
                        </p>
                    </div>

                    <label class="ml-1 flex items-center gap-2 text-sm text-primary">
                        <input
                            v-model="includeReversalPairs"
                            type="checkbox"
                            class="size-4 rounded border-outline-variant accent-primary"
                        />
                        <span class="font-bold uppercase tracking-wider">{{ t('Hapus juga jurnal pembalik (reversal) terkait secara otomatis') }}</span>
                    </label>

                    <div class="flex justify-end gap-2">
                        <AppButton type="button" variant="secondary" @click="purgeModalOpen = false">{{ t('Batal') }}</AppButton>
                        <AppButton type="submit" variant="danger" icon="delete_forever" :loading="purgeForm.processing">
                            {{ t('Hapus Permanen :count Transaksi', { count: selectedRowIds.length }) }}
                        </AppButton>
                    </div>
                </form>
            </AppModal>

            <!-- Modal Reset Training -->
            <AppModal v-model="resetModalOpen" :title="t('Reset Transaksi Sesi Pelatihan')">
                <form class="space-y-4" @submit.prevent="submitResetTraining">
                    <div class="rounded-xl border border-error/40 bg-error-container/20 p-4">
                        <p class="text-sm font-semibold text-error">{{ t('Pembersihan Transaksi Sesi Pelatihan') }}</p>
                        <p class="mt-1 text-xs text-on-surface-variant">
                            {{ t('Sistem akan menghapus seluruh') }} <span class="font-bold text-primary">{{ stats.training_transactions_count }} {{ t('transaksi') }}</span>
                            {{ t('yang dibuat sejak sesi pelatihan dimulai') }} (<span class="font-semibold">{{ formatDate(tenant.training_started_at) }}</span>).
                        </p>
                    </div>

                    <div class="rounded-xl bg-surface-container-low p-3 text-xs text-on-surface-variant space-y-1.5">
                        <p class="font-bold text-secondary">{{ t('Data yang TETAP UTUH & TERLINDUNGI:') }}</p>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <li>{{ t('Saldo Awal Akun') }} (`account_opening_balances`)</li>
                            <li>{{ t('Bagan Akun & Master COA') }} {{ t('(`accounts`)') }}</li>
                            <li>{{ t('Data Anggota, Kelompok, Desa & Unit Organisasi') }}</li>
                            <li>{{ t('Data Pengguna, Akun Login & Hak Akses') }}</li>
                            <li>{{ t('Data Historis Migrasi Legacy') }} (`legacy_transaksi`)</li>
                        </ul>
                    </div>

                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">
                            {{ t('Ketik') }} <code class="rounded bg-error-container px-1 py-0.5 text-error font-bold">RESET</code> {{ t('untuk konfirmasi:') }}
                        </label>
                        <AppInput v-model="resetConfirmText" placeholder="RESET" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <AppButton type="button" variant="secondary" @click="resetModalOpen = false">{{ t('Batal') }}</AppButton>
                        <AppButton
                            type="submit"
                            variant="danger"
                            icon="restart_alt"
                            :disabled="resetConfirmText !== 'RESET'"
                            :loading="resetForm.processing"
                        >
                            {{ t('Reset Transaksi Pelatihan') }}
                        </AppButton>
                    </div>
                </form>
            </AppModal>

            <!-- Modal End Training / Go-Live -->
            <AppModal v-model="endTrainingModalOpen" :title="t('Selesaikan Pelatihan & Masuk Mode Live')">
                <form class="space-y-4" @submit.prevent="submitEndTraining">
                    <div class="rounded-xl border border-secondary/40 bg-secondary-container/20 p-4">
                        <p class="text-sm font-semibold text-secondary">{{ t('Beralih ke Mode Live / Produksi') }}</p>
                        <p class="mt-1 text-xs text-on-surface-variant">
                            {{ t('Setelah masuk Mode Live, tenant akan beroperasi secara resmi dan fitur reset pelatihan akan') }} <strong>{{ t('dikunci otomatis') }}</strong> {{ t('demi keamanan.') }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-surface-container-low p-4 space-y-3">
                        <label class="flex items-start gap-3 text-sm font-medium text-primary cursor-pointer">
                            <input
                                v-model="endTrainingForm.purge_data"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-outline-variant accent-primary"
                            />
                            <div>
                                <p class="font-bold">{{ t('Bersihkan :count transaksi pelatihan sekarang', { count: stats.training_transactions_count }) }}</p>
                                <p class="text-xs text-on-surface-variant">
                                    {{ t('Centang untuk memulai masa live dengan buku besar yang bersih dari transaksi coba-coba saat latihan.') }}
                                </p>
                            </div>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2">
                        <AppButton type="button" variant="secondary" @click="endTrainingModalOpen = false">{{ t('Batal') }}</AppButton>
                        <AppButton
                            type="submit"
                            variant="success"
                            icon="check_circle"
                            :loading="endTrainingForm.processing"
                        >
                            {{ t('Konfirmasi Masuk Mode Live') }}
                        </AppButton>
                    </div>
                </form>
            </AppModal>
        </div>
    </AdminLayout>
</template>