<script setup>
import { useI18n } from '../../../i18n';
const { t } = useI18n();
import { Head, Link, router } from '@inertiajs/vue3';
import AdminPageHeader from '../../../Components/AdminPageHeader.vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    invoices: { type: Object, required: true },
    search: { type: String, default: '' },
    status: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'row_id' },
    direction: { type: String, default: 'desc' },
});

const columns = [
    { key: 'number', label: t('Nomor'), sortable: true },
    { key: 'tenant', label: t('Tenant') },
    { key: 'purpose', label: t('Keperluan') },
    { key: 'amount', label: t('Nominal'), sortable: true },
    { key: 'due_at', label: t('Jatuh tempo'), sortable: true },
    { key: 'status', label: t('Status'), sortable: true },
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
    { value: '', label: t('Semua status') },
    { value: 'issued', label: t('Issued') },
    { value: 'partially_paid', label: t('Partial') },
    { value: 'paid', label: t('Paid') },
    { value: 'overdue', label: t('Overdue') },
    { value: 'void', label: t('Void') },
    { value: 'draft', label: t('Draft') },
];

function money(value, currency = 'IDR') {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value || 0));
}

function filterStatus(value) {
    router.get('/admin/invoices', {
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
</script>

<template>
    <Head :title="t('Invoice')" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-4 sm:space-y-6">
            <AdminPageHeader
                :title="t('Invoice Platform')"
                :subtitle="t('Tagihan tenant: langganan, setup, support, training, custom, dan keperluan lain.')"
            >
                <template #actions>
                    <Link href="/admin/invoices/create"><AppButton icon="add">{{ t('Buat Invoice') }}</AppButton></Link>
                </template>
            </AdminPageHeader>

            <AppCard :padded="false">
                <SmartDataTable
                    :rows="invoices.data"
                    :columns="columns"
                    :pagination="invoices"
                    url="/admin/invoices"
                    :search="search"
                    :per-page="perPage"
                    :sort="sort"
                    :direction="direction"
                    search-placeholder="…"
                    empty-title="Belum ada invoice"
                    empty-description="Terbitkan invoice untuk tenant."
                >
                    <template #toolbar>
                        <div class="min-w-0 flex-1 sm:min-w-48 sm:flex-initial">
                            <SmartSelect :model-value="status" :label="t('Status')" hide-label size="compact" :options="statusOptions" @update:model-value="filterStatus" />
                        </div>
                    </template>
                    <template #cell-tenant="{ row }">
                        <span class="font-semibold text-primary">{{ row.tenant?.name || '—' }}</span>
                        <span class="block text-xs text-on-surface-variant">{{ row.tenant?.code }}</span>
                    </template>
                    <template #cell-purpose="{ row }">
                        <span class="font-semibold text-primary">{{ purposeLabels[row.purpose] || row.purpose || '—' }}</span>
                        <span v-if="row.description" class="block truncate text-xs text-on-surface-variant">{{ row.description }}</span>
                    </template>
                    <template #cell-amount="{ row }">
                        <span class="font-bold text-primary tabular-nums">{{ money(row.amount, row.currency) }}</span>
                    </template>
                    <template #cell-status="{ row }">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <AppBadge :tone="tone(row.status)">{{ row.status }}</AppBadge>
                            <span v-if="row.blocks_access" class="rounded bg-error/15 px-1.5 py-0.5 text-[10px] font-bold text-error" :title="t('Tagihan memblokir akses jika belum lunas')">
                                {{ t('Blokir') }}
                            </span>
                        </div>
                    </template>
                    <template #actions="{ row }">
                        <div class="flex items-center justify-end gap-1.5">
                            <Link :href="`/admin/invoices/${row.row_id}`"><AppButton variant="ghost" size="compact" icon="visibility">{{ t('Detail') }}</AppButton></Link>
                        </div>
                    </template>
                </SmartDataTable>
            </AppCard>
        </div>
    </AdminLayout>
</template>
