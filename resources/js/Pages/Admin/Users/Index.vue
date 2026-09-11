<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useConfirm } from '../../../composables/useConfirm';

const props = defineProps({
    users: { type: Object, required: true },
    search: { type: String, default: '' },
    status: { type: String, default: '' },
    tenant_id: { type: Number, default: null },
    perPage: { type: Number, default: 15 },
    summary: { type: Object, required: true },
    tenants: { type: Array, default: () => [] },
});

const { confirm } = useConfirm();
const toggleForm = useForm({});

const columns = [
    { key: 'name', label: 'Nama', sortable: true },
    { key: 'username', label: 'Username' },
    { key: 'email', label: 'Email' },
    { key: 'tenant', label: 'Tenant' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'last_login_at', label: 'Login Terakhir' },
];

const statusOptions = [
    { value: '', label: 'Semua status' },
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'inactive', label: 'Inactive' },
];

const kpis = [
    { label: 'Total User', icon: 'group', tone: 'primary' },
    { label: 'User Aktif', icon: 'how_to_reg', tone: 'success' },
    { label: 'User Nonaktif', icon: 'person_off', tone: 'error' },
    { label: 'Tanpa Tenant', icon: 'person_search', tone: 'secondary' },
];

function kpiValue(key) {
    if (key === 'Total User') return props.summary.total_users;
    if (key === 'User Aktif') return props.summary.active_users;
    if (key === 'User Nonaktif') return props.summary.disabled_users;
    return props.summary.without_tenant;
}

function filter(value, field) {
    router.get('/admin/users', {
        search: props.search || undefined,
        status: field === 'status' ? (value || undefined) : (props.status || undefined),
        tenant_id: field === 'tenant' ? (value ? Number(value) : undefined) : (props.tenant_id || undefined),
        per_page: props.perPage,
    }, { preserveState: true, replace: true });
}

async function toggleStatus(user) {
    const disabling = user.status === 'active';
    const ok = await confirm({
        title: disabling ? 'Nonaktifkan Akun?' : 'Aktifkan Kembali Akun?',
        message: disabling
            ? `User ${user.name} (@${user.username}) tidak akan bisa login ke aplikasi mana pun sampai akunnya diaktifkan kembali.`
            : `User ${user.name} (@${user.username}) akan dapat login kembali.`,
        confirmLabel: disabling ? 'Nonaktifkan' : 'Aktifkan',
        variant: disabling ? 'danger' : 'primary',
    });
    if (!ok) return;

    toggleForm.post(`/admin/users/${user.row_id}/toggle-status`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Pengguna Platform" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary">Pengguna Platform</h1>
                <p class="mt-1 text-on-surface-variant">
                    Cari dan kelola semua pengguna lintas tenant. Menonaktifkan akun langsung memblokir
                    login user di seluruh aplikasi — perubahan role/detail tetap lewat halaman user tenant.
                </p>
            </header>

            <!-- Ringkasan KPI -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <AppCard v-for="kpi in kpis" :key="kpi.label">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ kpi.label }}</p>
                        <AppIcon :name="kpi.icon" :tone="kpi.tone" container-size="8" container-shape="pill" />
                    </div>
                    <p class="mt-2 text-2xl font-extrabold tabular-nums text-primary">{{ kpiValue(kpi.label) }}</p>
                </AppCard>
            </div>

            <AppCard :padded="false">
                <div class="p-6">
                    <SmartDataTable
                        :rows="users.data"
                        :columns="columns"
                        :pagination="users"
                        url="/admin/users"
                        :search="search"
                        :per-page="perPage"
                        search-placeholder="Nama, username, atau email"
                        empty-title="Tidak ada pengguna ditemukan"
                        empty-description="Ubah kata kunci pencarian atau filter."
                    >
                        <template #toolbar>
                            <div class="min-w-40">
                                <SmartSelect
                                    :model-value="status"
                                    label="Status"
                                    hide-label
                                    clearable
                                    :options="statusOptions"
                                    @update:model-value="(v) => filter(v, 'status')"
                                />
                            </div>
                            <div class="min-w-52">
                                <SmartSelect
                                    :model-value="tenant_id ? String(tenant_id) : ''"
                                    label="Tenant"
                                    hide-label
                                    clearable
                                    searchable
                                    value-key="value"
                                    :options="[{ value: '', label: 'Semua tenant' }, ...tenants.map((t) => ({ value: String(t.row_id), label: `${t.code} — ${t.name}` }))]"
                                    @update:model-value="(v) => filter(v, 'tenant')"
                                />
                            </div>
                        </template>
                        <template #cell-name="{ row }">
                            <span class="font-semibold text-primary">{{ row.name }}</span>
                            <AppBadge v-if="row.is_superadmin" tone="primary-soft" class="ml-1.5">Superadmin</AppBadge>
                        </template>
                        <template #cell-email="{ row }">
                            <span class="text-on-surface-variant">{{ row.email || '—' }}</span>
                        </template>
                        <template #cell-tenant="{ row }">
                            <span v-if="row.tenant" class="font-medium text-primary">{{ row.tenant.code }}</span>
                            <span v-if="row.tenant" class="block text-xs text-on-surface-variant">{{ row.tenant.name }}</span>
                            <span v-if="!row.tenant" class="text-on-surface-variant">—</span>
                        </template>
                        <template #cell-status="{ row }">
                            <AppBadge :tone="row.status === 'active' ? 'success' : 'error'" class="whitespace-nowrap capitalize">{{ row.status }}</AppBadge>
                        </template>
                        <template #cell-last_login_at="{ row }">
                            <span v-if="row.last_login_at" class="whitespace-nowrap tabular-nums text-on-surface">{{ row.last_login_at }}</span>
                            <span v-else class="text-outline">belum pernah</span>
                        </template>
                        <template #actions="{ row }">
                            <AppButton
                                v-if="!row.is_superadmin"
                                :variant="row.status === 'active' ? 'error' : 'success'"
                                size="compact"
                                :icon="row.status === 'active' ? 'block' : 'check_circle'"
                                :loading="toggleForm.processing"
                                @click="toggleStatus(row)"
                            >
                                {{ row.status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                            </AppButton>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>

            <AppConfirmDialog />
        </div>
    </AdminLayout>
</template>
