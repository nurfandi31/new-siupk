<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
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
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Plan</h1>
                    <p class="mt-1 text-on-surface-variant">Paket langganan SaaS tenant.</p>
                </div>
                <Link href="/admin/plans/create"><AppButton icon="add">Tambah Plan</AppButton></Link>
            </header>

            <AppCard :padded="false">
                <div class="p-6">
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
                            <p class="font-semibold text-primary">{{ row.name }}</p>
                            <p class="text-xs text-on-surface-variant">{{ row.code }}</p>
                        </template>
                        <template #cell-price_amount="{ row }">{{ money(row.price_amount, row.currency) }}</template>
                        <template #cell-is_active="{ row }">
                            <AppBadge :tone="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</AppBadge>
                        </template>
                        <template #actions="{ row }">
                            <Link :href="`/admin/plans/${row.row_id}/edit`"><AppButton variant="ghost" size="compact" icon="edit">Edit</AppButton></Link>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AdminLayout>
</template>
