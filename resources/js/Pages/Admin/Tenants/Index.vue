<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminPageHeader from '../../../Components/AdminPageHeader.vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useI18n } from '../../../i18n';

const { t } = useI18n();

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
            alert(data.message || t('Gagal membuat sesi auto-login.'));
            return;
        }
        window.open(data.redirect_url, '_blank');
    } catch (err) {
        console.error(err);
        alert(t('Terjadi kesalahan saat memulai auto-login.'));
    } finally {
        impersonatingId.value = null;
    }
}

const columns = computed(() => [
    { key: 'name', label: t('Tenant'), sortable: true, class: 'w-64 min-w-[12rem]' },
    { key: 'district_code', label: t('Kecamatan'), class: 'w-44 hidden md:table-cell' },
    { key: 'status', label: t('Status'), sortable: true, class: 'w-28' },
    { key: 'plan', label: t('Plan'), class: 'w-32 hidden md:table-cell' },
    { key: 'memberships_count', label: t('Users'), class: 'w-20 text-right hidden md:table-cell' },
    { key: 'shard', label: t('Shard'), class: 'w-28 hidden xl:table-cell' },
]);
</script>

<template>
    <Head :title="t('Tenant')" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-4 sm:space-y-6">
            <AdminPageHeader
                :title="t('Manajemen Tenant')"
                :subtitle="t('Daftarkan, monitor status, dan kelola penempatan tenant platform di seluruh shard.')"
            >
                <template #actions>
                    <Link href="/admin/tenants/create"><AppButton icon="add">{{ t('Tambah Tenant') }}</AppButton></Link>
                </template>
            </AdminPageHeader>

            <AppCard :padded="false">
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
                    :search-label="t('Pencarian')"
                    :empty-title="t('Belum ada tenant')"
                    :empty-description="t('Daftarkan tenant pertama untuk mulai.')"
                >
                    <template #cell-name="{ row }">
                        <div class="min-w-0">
                            <Link :href="`/admin/tenants/${row.row_id}`" class="block truncate font-semibold text-primary hover:underline">{{ row.name }}</Link>
                            <p class="truncate text-[11px] text-on-surface-variant">{{ row.code }}</p>
                        </div>
                    </template>
                    <template #cell-status="{ row }">
                        <AppBadge :tone="row.status === 'active' ? 'success' : row.status === 'suspended' ? 'error' : 'neutral'">
                            {{ row.status === 'active' ? t('Aktif') : row.status === 'suspended' ? t('Suspended') : row.status }}
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
                                :aria-label="t('Auto login ke tenant')"
                                @click="autoLogin(row)"
                            >
                                <span class="hidden md:inline">{{ t('Auto Login') }}</span>
                            </AppButton>
                            <Link :href="`/admin/tenants/${row.row_id}`">
                                <AppButton variant="ghost" size="compact" icon="visibility" :aria-label="t('Detail tenant')">
                                    <span class="hidden md:inline">{{ t('Detail') }}</span>
                                </AppButton>
                            </Link>
                        </div>
                    </template>
                </SmartDataTable>
            </AppCard>
        </div>
    </AdminLayout>
</template>
