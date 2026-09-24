<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
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
        title: 'Hapus Proposal',
        message: `Hapus proposal ${row.loan_number || '#' + row.row_id}? Data akan dihapus permanen.`,
        confirmText: 'Hapus',
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
    { key: 'proposal', label: 'Proposal', icon: 'inventory_2' },
    { key: 'verifikasi', label: 'Verifikasi', icon: 'task_alt' },
    { key: 'waiting', label: 'Waiting', icon: 'hourglass_top' },
    { key: 'aktif', label: 'Aktif', icon: 'payments' },
    { key: 'lunas', label: 'Lunas', icon: 'verified' },
];

const pdfModalOpen = ref(false);
const pdfForm = ref({
    tab: 'all_active',
    start_date: '',
    end_date: '',
});

const pdfTabOptions = [
    { value: 'all_active', label: 'Pinjaman Terkini' },
    { value: 'proposal', label: 'Proposal' },
    { value: 'verifikasi', label: 'Verifikasi' },
    { value: 'waiting', label: 'Waiting' },
    { value: 'aktif', label: 'Aktif' },
    { value: 'lunas', label: 'Lunas' },
    { value: 'all', label: 'Semua' },
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
    window.open(`/lending/loans/pdf?${params.toString()}`, '_blank');
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
function formatNumber(value) { if (value === null || value === undefined || value === '') return '—'; return moneyFormatter.format(Number(value)); }
function formatServiceRate(value) { return `${Number(value ?? 0).toFixed(2)}%`; }
function formatDate(value) { if (!value) return '—'; const d = new Date(value); return Number.isNaN(d.getTime()) ? '—' : new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d); }

const emptyMessages = {
    proposal: { title: 'Belum ada proposal', description: '' },
    verifikasi: { title: 'Belum ada verifikasi', description: '' },
    waiting: { title: 'Belum ada waiting', description: '' },
    aktif: { title: 'Belum ada pinjaman aktif', description: '' },
    lunas: { title: 'Belum ada pinjaman lunas', description: '' },
};
</script>

<template>
    <Head title="Pinjaman" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-4 sm:space-y-6 pb-12">
            <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1">
                    <nav class="flex flex-wrap items-center gap-1.5 text-xs text-on-surface-variant" aria-label="Breadcrumb">
                        <span class="font-semibold text-on-surface">Pinjaman</span>
                    </nav>
                    <h1 class="mt-1.5 text-xl font-extrabold tracking-tight text-on-surface sm:text-2xl lg:text-[26px]">
                        Pinjaman
                    </h1>
                    <p class="mt-1 max-w-3xl text-sm text-on-surface-variant">
                        Kelola proposal, verifikasi, dan pencairan pinjaman kelompok.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <AppButton variant="secondary" :icon="view === 'kanban' ? 'table_chart' : 'view_kanban'" @click="switchView(view === 'kanban' ? 'table' : 'kanban')">
                        {{ view === 'kanban' ? 'Tabel' : 'Kanban' }}
                    </AppButton>
                    <AppButton variant="secondary" icon="print" @click="openPdfModal">Cetak PDF</AppButton>
                    <Link v-if="can('loans.propose')" href="/lending/loans/create"><AppButton icon="add">Ajukan</AppButton></Link>
                </div>
            </header>

            <div v-if="view === 'table'" class="border-b border-outline-variant">
                <AppTabs
                    :model-value="tab"
                    :items="tabs"
                    variant="underline"
                    align="end"
                    aria-label="Status Pinjaman"
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
                <SmartDataTable
                    :rows="loans.data"
                    :columns="columns"
                    :pagination="loans"
                    :url="`/lending/loans?tab=${tab}`"
                    :search="search"
                    :per-page="perPage"
                    :sort="sort"
                    :direction="direction"
                    :row-href="`/lending/loans/{row_id}`"
                    search-label="Cari"
                    search-placeholder="Cari kelompok atau nomor pinjaman"
                    :empty-title="emptyMessages[tab]?.title || 'Belum ada data'"
                    :empty-description="emptyMessages[tab]?.description || ''"
                >
                    <template #cell-loan_number="{ row }">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-on-surface">{{ row.loan_number || `#${row.row_id}` }}</span>
                            <AppButton
                                v-if="row.status === 'draft' && (can('loans.manage') || can('loans.propose'))"
                                variant="ghost"
                                size="compact"
                                icon="delete_outline"
                                aria-label="Hapus proposal"
                                title="Hapus"
                                @click="confirmDelete(row)"
                            />
                        </div>
                    </template>

                    <template #cell-group_name="{ row }">
                        <div class="min-w-[180px]">
                            <p class="font-bold text-on-surface">{{ row.group_name }}</p>
                            <p v-if="row.leader_name" class="text-xs text-on-surface-variant">Ketua: {{ row.leader_name }}</p>
                            <p v-if="row.loan_number" class="mt-0.5 text-[11px] font-semibold uppercase tracking-wide text-primary/80">
                                ID {{ row.loan_number }}
                            </p>
                        </div>
                    </template>

                    <template #cell-proposed_amount="{ row }">
                        <span class="tabular-nums font-bold text-on-surface">{{ formatNumber(row.proposed_amount) }}</span>
                    </template>

                    <template #cell-verification_amount="{ row }">
                        <span class="tabular-nums font-bold text-on-surface">{{ formatNumber(row.verification_amount) }}</span>
                    </template>

                    <template #cell-allocated_amount="{ row }">
                        <span class="tabular-nums font-bold text-on-surface">{{ formatNumber(row.allocated_amount) }}</span>
                    </template>

                    <template #cell-principal_remaining="{ row }">
                        <span class="tabular-nums font-bold text-on-surface">{{ formatNumber(row.principal_remaining) }}</span>
                    </template>

                    <template #cell-total_interest_paid="{ row }">
                        <span class="tabular-nums font-bold text-on-surface">{{ formatNumber(row.total_interest_paid) }}</span>
                    </template>

                    <template #cell-service_rate="{ row }">
                        <span>{{ formatServiceRate(row.service_rate) }}</span>
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
                </SmartDataTable>
            </AppCard>
        </div>

        <AppModal v-model="pdfModalOpen" title="Cetak PDF" size="md">
            <div class="space-y-4">
                <SmartSelect
                    v-model="pdfForm.tab"
                    label="Status"
                    :options="pdfTabOptions"
                    required
                />
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <AppDatePicker v-model="pdfForm.start_date" label="Dari" clearable />
                    <AppDatePicker v-model="pdfForm.end_date" label="Sampai" clearable />
                </div>
            </div>
            <template #footer>
                <AppButton variant="secondary" @click="pdfModalOpen = false">Batal</AppButton>
                <AppButton variant="primary" icon="picture_as_pdf" @click="submitPdfPrint">Cetak</AppButton>
            </template>
        </AppModal>
    </AuthenticatedLayout>
</template>
