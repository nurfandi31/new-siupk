<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminPageHeader from '../../../Components/AdminPageHeader.vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineProps({
    plans: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'name' },
    direction: { type: String, default: 'asc' },
});

const columns = [
    { key: 'name', label: 'Plan', sortable: true },
    { key: 'price_amount', label: 'Harga' },
    { key: 'billing_period', label: 'Periode' },
    { key: 'is_active', label: 'Status' },
];

function money(value, currency = 'IDR') {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value || 0));
}
</script>

<template>
    <Head title="Plan" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-4 sm:space-y-6">
            <AdminPageHeader
                title="Paket Langganan"
                subtitle="Definisikan plan SaaS, harga, dan periode penagihan untuk tenant."
            >
                <template #actions>
                    <Link href="/admin/plans/create"><AppButton icon="add">Tambah Plan</AppButton></Link>
                </template>
            </AdminPageHeader>

            <AppCard :padded="false">
                <SmartDataTable
                    :rows="plans.data"
                    :columns="columns"
                    :pagination="plans"
                    url="/admin/plans"
                    :search="search"
                    :per-page="perPage"
                    :sort="sort"
                    :direction="direction"
                    empty-title="Belum ada plan"
                    empty-description="Buat plan sebelum menetapkan langganan."
                >
                    <template #cell-name="{ row }">
                        <div class="flex items-center gap-3">
                            <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-tertiary-container text-xs font-bold text-on-tertiary-container">
                                <AppIcon name="workspace_premium" />
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-primary">{{ row.name }}</p>
                                <p class="font-mono text-xs text-on-surface-variant">{{ row.code }}</p>
                            </div>
                        </div>
                    </template>
                    <template #cell-price_amount="{ row }">
                        <span class="font-bold text-primary tabular-nums">{{ money(row.price_amount, row.currency) }}</span>
                    </template>
                    <template #cell-is_active="{ row }">
                        <AppBadge :tone="row.is_active ? 'success' : 'neutral'">
                            {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                        </AppBadge>
                    </template>
                    <template #actions="{ row }">
                        <div class="flex items-center justify-end gap-1.5">
                            <Link :href="`/admin/plans/${row.row_id}/edit`"><AppButton variant="ghost" size="compact" icon="edit">Edit</AppButton></Link>
                        </div>
                    </template>
                </SmartDataTable>
            </AppCard>
        </div>
    </AdminLayout>
</template>
