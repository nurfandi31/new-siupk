<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    logs: { type: Object, required: true },
    search: { type: String, default: '' },
    action: { type: String, default: '' },
    tenant_id: { type: Number, default: null },
    perPage: { type: Number, default: 15 },
    actions: { type: Array, default: () => [] },
    tenants: { type: Array, default: () => [] },
});

const columns = [
    { key: 'created_at', label: 'Waktu', sortable: false },
    { key: 'actor_name', label: 'Aktor' },
    { key: 'action', label: 'Aksi' },
    { key: 'tenant', label: 'Tenant' },
    { key: 'description', label: 'Deskripsi' },
];

function label(value) {
    return String(value).replace(/[._]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

const actionOptions = [
    { value: '', label: 'Semua aksi' },
    ...props.actions.map((value) => ({ value, label: label(value) })),
];

function filterAction(value) {
    router.get('/admin/audit-logs', {
        search: props.search || undefined,
        action: value || undefined,
        tenant_id: props.tenant_id || undefined,
        per_page: props.perPage,
    }, { preserveState: true, replace: true });
}

function filterTenant(value) {
    router.get('/admin/audit-logs', {
        search: props.search || undefined,
        action: props.action || undefined,
        tenant_id: value ? Number(value) : undefined,
        per_page: props.perPage,
    }, { preserveState: true, replace: true });
}

function tone(action) {
    if (/purge|void|suspend|reset_password|reset_training/.test(String(action))) return 'error-soft';
    if (/impersonate/.test(String(action))) return 'warning-soft';
    if (/create|activate/.test(String(action))) return 'success-soft';
    return 'info-soft';
}

function subjectLink(row) {
    if (!row.subject_type || !row.subject_id) return null;
    const map = {
        'App\\Models\\Platform\\Tenant': `/admin/tenants/${row.subject_id}`,
        'App\\Models\\Platform\\Invoice': `/admin/invoices/${row.subject_id}`,
        'App\\Models\\User': row.tenant ? `/admin/tenants/${row.tenant.row_id}/users` : null,
    };
    return map[row.subject_type] ?? null;
}
</script>

<template>
    <Head title="Log Audit" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary">Log Audit Platform</h1>
                <p class="mt-1 text-on-surface-variant">Jejak semua aksi sensitif superadmin: perubahan tenant, impersonasi, penagihan, dan penghapusan data.</p>
            </header>

            <AppCard :padded="false">
                <div class="p-6">
                    <SmartDataTable
                        :rows="logs.data"
                        :columns="columns"
                        :pagination="logs"
                        url="/admin/audit-logs"
                        :search="search"
                        :per-page="perPage"
                        search-placeholder="Cari deskripsi atau aktor"
                        empty-title="Belum ada aktivitas tercatat"
                        empty-description="Aksi admin sensitif akan terekam otomatis di sini."
                    >
                        <template #toolbar>
                            <div class="min-w-48">
                                <SmartSelect
                                    :model-value="action"
                                    label="Aksi"
                                    hide-label
                                    clearable
                                    :options="actionOptions"
                                    @update:model-value="filterAction"
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
                                    @update:model-value="filterTenant"
                                />
                            </div>
                        </template>
                        <template #cell-created_at="{ row }">
                            <span class="whitespace-nowrap font-medium tabular-nums text-on-surface">{{ row.created_at }}</span>
                        </template>
                        <template #cell-actor_name="{ row }">
                            <span class="font-semibold text-primary">{{ row.actor_name }}</span>
                            <AppBadge v-if="row.actor_type === 'superadmin'" :tone="'primary-soft'" class="ml-1.5">
                                Superadmin
                            </AppBadge>
                        </template>
                        <template #cell-action="{ row }">
                            <AppBadge :tone="tone(row.action)" class="whitespace-nowrap">{{ label(row.action) }}</AppBadge>
                        </template>
                        <template #cell-tenant="{ row }">
                            <span v-if="row.tenant" class="font-semibold text-primary">{{ row.tenant.name }}</span>
                            <span v-if="row.tenant" class="block text-xs text-on-surface-variant">{{ row.tenant.code }}</span>
                            <span v-if="!row.tenant" class="text-on-surface-variant">—</span>
                        </template>
                        <template #cell-description="{ row }">
                            <p>{{ row.description || '—' }}</p>
                            <p v-if="row.ip_address" class="text-xs text-outline">{{ row.subject_type ? `#${row.subject_id} · ${subjectLink(row) ? '' : row.subject_type.split('\\').pop()} · ` : '' }}IP {{ row.ip_address }}</p>
                            <details v-if="row.properties && Object.keys(row.properties).length" class="mt-0.5 text-xs">
                                <summary class="cursor-pointer text-primary hover:underline">Detail</summary>
                                <pre class="mt-1 max-w-xl overflow-x-auto rounded bg-surface-container-low p-2 text-[11px] leading-relaxed text-on-surface-variant">{{ JSON.stringify(row.properties, null, 2) }}</pre>
                            </details>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AdminLayout>
</template>
