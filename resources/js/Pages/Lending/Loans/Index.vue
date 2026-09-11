<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppModal from '../../../Components/AppModal.vue';
import AppTabs from '../../../Components/AppTabs.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import LoanKanbanBoard from '../../../Components/LoanKanbanBoard.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';
import { useConfirm } from '../../../composables/useConfirm';

const { can } = useCan();
const { confirm: confirmAction } = useConfirm();

async function confirmDelete(row) {
    if (!await confirmAction({
        title: 'Hapus Proposal Pinjaman',
        message: `Apakah Anda yakin ingin menghapus proposal pinjaman ${row.loan_number || '#' + row.row_id}? Data akan dihapus permanen.`,
        confirmText: 'Ya, Hapus',
        variant: 'danger',
    })) return;

    router.delete(`/lending/loans/${row.row_id}`, { preserveScroll: true });
}

const props = defineProps({
    loans: { type: Object, required: true },
    tab: { type: String, default: 'proposal' },
    view: { type: String, default: 'table' },
    kanban: { type: Object, default: () => ({}) },
    disbursementAccounts: { type: Array, default: () => [] },
    today: { type: String, default: '' },
    columns: { type: Array, required: true },
    search: { type: String, default: '' },
    perPage: { type: [Number, String], default: 15 },
    sort: { type: String, default: '' },
    direction: { type: String, default: 'desc' },
});

const tabs = [
    { key: 'proposal', label: 'Proposal' },
    { key: 'verifikasi', label: 'Verifikasi' },
    { key: 'waiting', label: 'Waiting' },
    { key: 'aktif', label: 'Aktif' },
    { key: 'lunas', label: 'Lunas' },
];

const pdfModalOpen = ref(false);
const pdfForm = ref({
    tab: 'all_active',
    start_date: '',
    end_date: '',
});

const pdfTabOptions = [
    { value: 'all_active', label: 'Pinjaman Terkini (Proposal, Verifikasi, Waiting & Aktif)' },
    { value: 'proposal', label: 'Proposal (Pengajuan Baru)' },
    { value: 'verifikasi', label: 'Terverifikasi (Pemeriksaan)' },
    { value: 'waiting', label: 'Waiting (Menunggu Pencairan)' },
    { value: 'aktif', label: 'Aktif (Sedang Berjalan)' },
    { value: 'lunas', label: 'Lunas / Selesai' },
    { value: 'all', label: 'Semua Status Pinjaman' },
];

function openPdfModal() {
    pdfForm.value.tab = props.tab || 'all_active';
    pdfModalOpen.value = true;
}

function submitPdfPrint() {
    const params = new URLSearchParams();
    if (pdfForm.value.tab) params.append('tab', pdfForm.value.tab);
    if (pdfForm.value.start_date) params.append('start_date', pdfForm.value.start_date);
    if (pdfForm.value.end_date) params.append('end_date', pdfForm.value.end_date);
    if (props.search) params.append('search', props.search);

    const url = `/lending/loans/pdf?${params.toString()}`;
    window.open(url, '_blank');
    pdfModalOpen.value = false;
}

function switchTab(tabKey) {
    router.get('/lending/loans', { tab: tabKey, view: props.view }, { preserveState: false });
}

function switchView(newView) {
    router.get('/lending/loans', { tab: props.tab, view: newView }, { preserveState: false });
}

function refreshBoard() {
    router.get('/lending/loans', { tab: props.tab, view: 'kanban' }, { preserveState: false, preserveScroll: true });
}

const moneyFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

function formatNumber(value) {
    if (value === null || value === undefined || value === '') return '?';
    return moneyFormatter.format(Number(value));
}

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
}

function formatServiceRate(value) {
    return `${Number(value ?? 0).toFixed(2)}%`;
}

function formatDate(value) {
    if (!value) return '?';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '?';
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
}

const emptyMessages = {
    proposal: { title: 'Belum ada proposal', description: 'Belum ada pengajuan pinjaman kelompok yang baru didaftarkan.' },
    verifikasi: { title: 'Belum ada verifikasi', description: 'Tidak ada pinjaman yang sedang menunggu verifikasi.' },
    waiting: { title: 'Belum ada waiting', description: 'Tidak ada pinjaman yang menunggu keputusan pendanaan.' },
    aktif: { title: 'Belum ada pinjaman aktif', description: 'Tidak ada pinjaman yang sedang aktif berjalan.' },
    lunas: { title: 'Belum ada pinjaman lunas', description: 'Tidak ada pinjaman yang telah dilunasi.' },
};
</script>

<template>
    <Head title="Tahapan Perguliran" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Tahapan Perguliran</h1>
                    <p class="mt-1 text-on-surface-variant">Pantau pergerakan pinjaman dari pengajuan hingga pelunasan.</p>
                </div>
                <div class="flex items-center gap-3">
                    <AppButton variant="secondary" :icon="view === 'kanban' ? 'table_chart' : 'view_kanban'" @click="switchView(view === 'kanban' ? 'table' : 'kanban')">
                        {{ view === 'kanban' ? 'Tampilan Tabel' : 'Tampilan Kanban' }}
                    </AppButton>
                    <AppButton variant="secondary" icon="print" @click="openPdfModal">Cetak PDF</AppButton>
                    <Link v-if="can('loans.propose')" href="/lending/loans/create"><AppButton icon="add">Register Proposal</AppButton></Link>
                </div>
            </header>

            <div v-if="view === 'table'" class="border-b border-outline-variant">
                <AppTabs
                    :model-value="tab"
                    :items="tabs"
                    variant="underline"
                    aria-label="Tabs Status Pinjaman"
                    @update:model-value="switchTab($event)"
                />
            </div>

            <template v-if="view === 'kanban'">
                <LoanKanbanBoard
                    :columns="kanban"
                    :disbursement-accounts="disbursementAccounts"
                    :search="search"
                    :today="today"
                    @refresh="refreshBoard"
                />
            </template>

            <AppCard v-else :padded="false">
                <div class="p-6">
                    <SmartDataTable
                        :rows="loans.data"
                        :columns="columns"
                        :pagination="loans"
                        :url="`/lending/loans?tab=${tab}`"
                        :search="search"
                        :per-page="perPage"
                        :sort="sort"
                        :direction="direction"
                        search-label="Cari pinjaman"
                        search-placeholder="Cari nama kelompok atau desa"
                        :empty-title="emptyMessages[tab]?.title || 'Belum ada data pinjaman'"
                        :empty-description="emptyMessages[tab]?.description || 'Tidak ditemukan data pinjaman untuk status ini.'"
                    >
                        <template #cell-loan_number="{ row }">
                            <span class="font-bold text-primary">{{ row.loan_number || `#${row.row_id}` }}</span>
                        </template>

                        <template #cell-group_name="{ row }">
                            <div>
                                <p class="font-bold text-primary">{{ row.group_name }}</p>
                                <p v-if="row.leader_name" class="text-xs text-on-surface-variant">Ketua: {{ row.leader_name }}</p>
                            </div>
                        </template>

                        <template #cell-principal_amount="{ row }">
                            <span class="tabular-nums font-bold text-primary">{{ formatNumber(row.principal_amount) }}</span>
                        </template>

                        <template #cell-proposed_amount="{ row }">
                            <span class="tabular-nums font-bold text-primary">{{ formatNumber(row.proposed_amount) }}</span>
                        </template>

                        <template #cell-verification_amount="{ row }">
                            <span class="tabular-nums font-bold text-primary">{{ formatNumber(row.verification_amount) }}</span>
                        </template>

                        <template #cell-allocated_amount="{ row }">
                            <span class="tabular-nums font-bold text-primary">{{ formatNumber(row.allocated_amount) }}</span>
                        </template>

                        <template #cell-principal_remaining="{ row }">
                            <span class="tabular-nums font-bold text-primary">{{ formatNumber(row.principal_remaining) }}</span>
                        </template>

                        <template #cell-total_interest_paid="{ row }">
                            <span class="tabular-nums font-bold text-primary">{{ formatNumber(row.total_interest_paid) }}</span>
                        </template>

                        <template #cell-service_rate="{ row }">
                            <span>{{ formatServiceRate(row.service_rate) }}</span>
                        </template>

                        <template #cell-interest_rate="{ row }">
                            <span>{{ formatServiceRate(row.interest_rate) }} ({{ row.installment_method || 'flat' }})</span>
                        </template>

                        <template #cell-proposed_at="{ row }">
                            <span>{{ formatDate(row.proposed_at) }}</span>
                        </template>

                        <template #cell-verified_at="{ row }">
                            <span>{{ formatDate(row.verified_at) }}</span>
                        </template>

                        <template #cell-funded_at="{ row }">
                            <span>{{ formatDate(row.funded_at) }}</span>
                        </template>

                        <template #cell-disbursed_at="{ row }">
                            <span>{{ formatDate(row.disbursed_at) }}</span>
                        </template>

                        <template #cell-completed_at="{ row }">
                            <span>{{ formatDate(row.completed_at) }}</span>
                        </template>

                        <template #cell-next_due_date="{ row }">
                            <span>{{ formatDate(row.next_due_date) }}</span>
                        </template>

                        <template #cell-status="{ row }">
                            <AppBadge :variant="row.status_badge_variant">{{ row.status_label }}</AppBadge>
                        </template>

                        <template #actions="{ row }">
                            <div class="flex items-center justify-end gap-1">
                                <Link :href="`/lending/loans/${row.row_id}`">
                                    <AppButton variant="ghost" size="compact" icon="visibility">Detail</AppButton>
                                </Link>
                                <AppButton
                                    v-if="row.status === 'draft' && (can('loans.manage') || can('loans.propose'))"
                                    variant="danger"
                                    size="compact"
                                    icon="delete_outline"
                                    @click="confirmDelete(row)"
                                >
                                    Hapus
                                </AppButton>
                            </div>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>

        <!-- Modal Cetak PDF -->
        <AppModal v-model="pdfModalOpen" title="Cetak Laporan Daftar Pinjaman (PDF)" size="md">
            <div class="space-y-4">
                <SmartSelect
                    v-model="pdfForm.tab"
                    label="Status Pinjaman"
                    :options="pdfTabOptions"
                    required
                    hint="Pilih status pinjaman terkini atau seluruh status."
                />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <AppDatePicker v-model="pdfForm.start_date" label="Dari Tanggal Pengajuan" hint="Kosongkan untuk dari awal." clearable />
                    <AppDatePicker v-model="pdfForm.end_date" label="Sampai Tanggal" hint="Kosongkan untuk sampai sekarang." clearable />
                </div>
            </div>

            <template #footer>
                <AppButton variant="secondary" @click="pdfModalOpen = false">Batal</AppButton>
                <AppButton variant="primary" icon="picture_as_pdf" @click="submitPdfPrint">Cetak / Buka PDF</AppButton>
            </template>
        </AppModal>
    </AuthenticatedLayout>
</template>
