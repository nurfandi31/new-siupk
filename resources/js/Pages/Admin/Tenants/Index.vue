<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminPageHeader from '../../../Components/AdminPageHeader.vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineProps({
    tenants: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'row_id' },
    direction: { type: String, default: 'desc' },
});

const impersonatingId = ref(null);

async function autoLogin(tenant) {
    if (impersonatingId.value) return;
    impersonatingId.value = tenant.row_id;
    try {
        const response = await fetch(`/admin/tenants/${tenant.row_id}/impersonate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        const data = await response.json();
        if (!response.ok || !data.redirect_url) {
            alert(data.message || 'Gagal membuat sesi auto-login.');
            return;
        }
        window.open(data.redirect_url, '_blank');
    } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan saat memulai auto-login.');
    } finally {
        impersonatingId.value = null;
    }
}

const columns = [
    { key: 'name', label: 'Tenant', sortable: true, class: 'w-64 min-w-[12rem]' },
    { key: 'district_code', label: 'Kecamatan', class: 'w-44 hidden md:table-cell' },
    { key: 'status', label: 'Status', sortable: true, class: 'w-28' },
    { key: 'plan', label: 'Plan', class: 'w-32 hidden md:table-cell' },
    { key: 'memberships_count', label: 'Users', class: 'w-20 text-right hidden md:table-cell' },
    { key: 'shard', label: 'Shard', class: 'w-28 hidden xl:table-cell' },
];
</script>

<template>
    <Head title="Tenant" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-5">
            <AdminPageHeader
                title="Manajemen Tenant"
                subtitle="Daftarkan, monitor status, dan kelola penempatan tenant platform di seluruh shard."
            >
                <template #actions>
                    <Link href="/admin/tenants/create"><AppButton icon="add">Tambah Tenant</AppButton></Link>
                </template>
            </AdminPageHeader>

            <AppCard :padded="false">
                <div class="p-4 sm:p-6">
                    <SmartDataTable
                        :rows="tenants.data"
                        :columns="columns"
                        :pagination="tenants"
                        url="/admin/tenants"
                        :search="search"
                        :per-page="perPage"
                        :sort="sort"
                        :direction="direction"
                        :dense="true"
                        search-label="Pencarian"
                        empty-title="Belum ada tenant"
                        empty-description="Daftarkan tenant pertama untuk mulai."
                    >
                        <template #cell-name="{ row }">
                            <div class="min-w-0">
                                <Link :href="`/admin/tenants/${row.row_id}`" class="block truncate font-semibold text-primary hover:underline">{{ row.name }}</Link>
                                <p class="truncate text-[11px] text-on-surface-variant">{{ row.code }}</p>
                            </div>
                        </template>
                        <template #cell-status="{ row }">
                            <AppBadge :tone="row.status === 'active' ? 'success' : row.status === 'suspended' ? 'error' : 'neutral'">
                                {{ row.status === 'active' ? 'Aktif' : row.status === 'suspended' ? 'Suspended' : row.status }}
                            </AppBadge>
                        </template>
                        <template #cell-plan="{ row }">
                            <span v-if="row.plan" class="font-medium text-primary">{{ row.plan.name }}</span>
                            <span v-else class="text-outline">—</span>
                        </template>
                        <template #cell-shard="{ row }">
                            <span v-if="row.shard" class="inline-flex items-center gap-1 font-mono text-xs text-on-surface-variant">
                                <AppIcon name="storage" class="text-sm" />
                                {{ row.shard.code }}
                            </span>
                            <span v-else class="text-outline">—</span>
                        </template>
                        <template #actions="{ row }">
                            <div class="flex items-center justify-end gap-1.5">
                                <AppButton
                                    v-if="row.status === 'active'"
                                    variant="secondary"
                                    size="compact"
                                    icon="login"
                                    :loading="impersonatingId === row.row_id"
                                    aria-label="Auto login ke tenant"
                                    @click="autoLogin(row)"
                                >
                                    <span class="hidden md:inline">Auto Login</span>
                                </AppButton>
                                <Link :href="`/admin/tenants/${row.row_id}`">
                                    <AppButton variant="ghost" size="compact" icon="visibility" aria-label="Detail tenant">
                                        <span class="hidden md:inline">Detail</span>
                                    </AppButton>
                                </Link>
                            </div>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AdminLayout>
</template>
