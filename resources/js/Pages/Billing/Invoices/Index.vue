<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    invoices: { type: Object, required: true },
    search: { type: String, default: '' },
    status: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'row_id' },
    direction: { type: String, default: 'desc' },
});

const columns = [
    { key: 'number', label: 'Nomor', sortable: true },
    { key: 'purpose', label: 'Keperluan' },
    { key: 'description', label: 'Deskripsi' },
    { key: 'amount', label: 'Nominal', sortable: true },
    { key: 'due_at', label: 'Jatuh tempo', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
];

const purposeLabels = {
    subscription: 'Langganan',
    setup: 'Setup',
    support: 'Support',
    training: 'Pelatihan',
    custom_dev: 'Custom',
    other: 'Lainnya',
};

const statusOptions = [
    { value: '', label: 'Semua status' },
    { value: 'issued', label: 'Belum dibayar' },
    { value: 'partially_paid', label: 'Sebagian' },
    { value: 'paid', label: 'Lunas' },
    { value: 'overdue', label: 'Terlambat' },
    { value: 'void', label: 'Dibatalkan' },
];

function money(value, currency = 'IDR') {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value || 0));
}

function filterStatus(value) {
    router.get('/billing/invoices', {
        search: props.search || undefined,
        status: value || undefined,
        per_page: props.perPage,
        sort: props.sort,
        direction: props.direction,
    }, { preserveState: true, replace: true });
}

function tone(status) {
    if (status === 'paid') return 'success';
    if (status === 'overdue' || status === 'void') return 'error';
    if (status === 'partially_paid') return 'warning';
    return 'neutral';
}

function statusLabel(status) {
    return ({
        issued: 'Belum dibayar',
        partially_paid: 'Sebagian',
        paid: 'Lunas',
        overdue: 'Terlambat',
        void: 'Dibatalkan',
        draft: 'Draft',
    })[status] || status;
}
</script>

<template>
    <Head title="Tagihan" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary">Tagihan</h1>
                <p class="mt-1 text-on-surface-variant">Daftar tagihan lembaga: langganan, setup, support, dan lainnya.</p>
            </header>

            <AppCard :padded="false">
                <div class="p-6">
                    <SmartDataTable
                        :rows="invoices.data"
                        :columns="columns"
                        :pagination="invoices"
                        url="/billing/invoices"
                        :search="search"
                        :per-page="perPage"
                        :sort="sort"
                        :direction="direction"
                        search-placeholder="Nomor atau deskripsi"
                        empty-title="Belum ada tagihan"
                        empty-description="Tagihan akan muncul di sini setelah diterbitkan."
                    >
                        <template #toolbar>
                            <div class="min-w-48">
                                <SmartSelect :model-value="status" label="Status" hide-label :options="statusOptions" @update:model-value="filterStatus" />
                            </div>
                        </template>
                        <template #cell-purpose="{ row }">
                            <span class="font-semibold text-primary">{{ purposeLabels[row.purpose] || row.purpose || '—' }}</span>
                        </template>
                        <template #cell-description="{ row }">
                            <span class="text-on-surface">{{ row.description || '—' }}</span>
                        </template>
                        <template #cell-amount="{ row }">
                            <span class="font-semibold text-primary">{{ money(row.amount, row.currency) }}</span>
                            <span v-if="Number(row.remaining) > 0 && row.status !== 'paid'" class="block text-xs text-on-surface-variant">
                                Sisa {{ money(row.remaining, row.currency) }}
                            </span>
                        </template>
                        <template #cell-status="{ row }">
                            <AppBadge :tone="tone(row.status)">{{ statusLabel(row.status) }}</AppBadge>
                        </template>
                        <template #actions="{ row }">
                            <Link :href="`/billing/invoices/${row.row_id}`">
                                <AppButton variant="ghost" size="compact" icon="visibility">Detail</AppButton>
                            </Link>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
