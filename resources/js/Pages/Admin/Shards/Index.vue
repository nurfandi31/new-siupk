<script setup>
import { Head } from '@inertiajs/vue3';
import AdminPageHeader from '../../../Components/AdminPageHeader.vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    shards: { type: Array, required: true },
    runs: { type: Object, required: true },
    summary: { type: Object, required: true },
    perPage: { type: Number, default: 15 },
});

const runColumns = [
    { key: 'created_at', label: 'Waktu Mulai', sortable: false },
    { key: 'tenant_name', label: 'Tenant' },
    { key: 'suffix', label: 'Suffix' },
    { key: 'mode', label: 'Mode' },
    { key: 'progress', label: 'Progres Step' },
    { key: 'status', label: 'Status' },
];

function shardStatusTone(status) {
    switch (String(status)) {
        case 'active': return 'success-soft';
        case 'maintenance': return 'warning-soft';
        case 'retired': case 'disabled': return 'error-soft';
        default: return 'neutral';
    }
}

function runStatusTone(status) {
    switch (String(status)) {
        case 'completed': return 'success';
        case 'running': return 'info';
        case 'failed': return 'error';
        default: return 'neutral';
    }
}

function weightPercent(shard) {
    if (!shard.maximum_weight || shard.maximum_weight <= 0) return null;
    return Math.min(100, Math.round((shard.current_weight / shard.maximum_weight) * 100));
}

function driverIcon(driver) {
    const d = String(driver || '').toLowerCase();
    if (d.includes('pgsql')) return 'database';
    if (d.includes('mysql') || d.includes('mariadb')) return 'storage';
    if (d.includes('sqlite')) return 'description';
    return 'lan';
}
</script>

<template>
    <Head title="Shard & Cutover" />
    <AdminLayout>
        <div class="mx-auto max-w-6xl space-y-5">
            <AdminPageHeader
                title="Shard & Cutover"
                subtitle="Peta infrastruktur database shard dan riwayat cutover data tenant. Hanya-baca — provisioning lewat menu Migrasi Data."
            />

            <!-- Ringkasan KPI -->
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <AppCard>
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Shard</p>
                        <AppIcon name="storage" tone="primary" container-size="9" container-shape="pill" />
                    </div>
                    <p class="mt-2 text-2xl font-extrabold text-primary">
                        {{ summary.active_shards }}<span class="text-sm font-medium text-on-surface-variant">/{{ summary.total_shards }} aktif</span>
                    </p>
                </AppCard>

                <AppCard>
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Tenant Ter-place</p>
                        <AppIcon name="domain" tone="secondary" container-size="9" container-shape="pill" />
                    </div>
                    <p class="mt-2 text-2xl font-extrabold text-secondary">{{ summary.total_tenants_placed }}</p>
                    <p class="mt-0.5 text-xs text-on-surface-variant">Seluruh shard aktif</p>
                </AppCard>

                <AppCard>
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Cutover Selesai</p>
                        <AppIcon name="check_circle" tone="success" container-size="9" container-shape="pill" />
                    </div>
                    <p class="mt-2 text-2xl font-extrabold text-secondary">{{ summary.runs_completed }}</p>
                    <p v-if="summary.runs_failed > 0" class="mt-0.5 text-xs font-semibold text-error">{{ summary.runs_failed }} run gagal</p>
                    <p v-else class="mt-0.5 text-xs text-on-surface-variant">Tidak ada run gagal</p>
                </AppCard>

                <AppCard>
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Skema Tertinggal</p>
                        <AppIcon name="sync_problem" :tone="summary.schema_lagging > 0 ? 'warning' : 'neutral'" container-size="9" container-shape="pill" />
                    </div>
                    <p class="mt-2 text-2xl font-extrabold" :class="summary.schema_lagging > 0 ? 'text-warning' : 'text-primary'">{{ summary.schema_lagging }}</p>
                    <p class="mt-0.5 text-xs text-on-surface-variant">Belum sinkron ke versi target</p>
                </AppCard>
            </div>

            <!-- Daftar Shard -->
            <AppCard>
                <template #header>
                    <div class="flex items-center gap-2">
                        <AppIcon name="database" class="text-primary" />
                        <h2 class="font-bold text-primary">Database Shard</h2>
                        <span class="rounded-full bg-surface-container-low px-2 py-0.5 text-xs font-bold text-on-surface-variant">{{ shards.length }}</span>
                    </div>
                </template>

                <div v-if="!shards.length" class="rounded-lg bg-surface-container-low p-4 text-sm text-on-surface-variant">
                    Belum ada shard terdaftar. Jalankan <code class="rounded bg-surface-container-lowest px-1 font-mono">php artisan app:bootstrap-local</code> untuk inisialisasi.
                </div>
                <div v-else class="space-y-3">
                    <div v-for="shard in shards" :key="shard.row_id" class="rounded-lg border border-outline-variant p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-bold text-primary">{{ shard.name }}</span>
                                    <AppBadge :tone="shardStatusTone(shard.status)" class="capitalize">{{ shard.status }}</AppBadge>
                                    <AppBadge tone="neutral" class="capitalize">{{ shard.placement_type }}</AppBadge>
                                </div>
                                <div class="mt-1 flex min-w-0 items-center gap-1 font-mono text-xs text-on-surface-variant">
                                    <AppIcon :name="driverIcon(shard.driver)" class="shrink-0 text-base" />
                                    <span class="truncate"><span class="font-semibold">{{ shard.code }}</span> · {{ shard.driver }}://{{ shard.endpoint }}/{{ shard.database_name }}</span>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-2xl font-extrabold tabular-nums leading-none text-primary">{{ shard.tenants_count }}</p>
                                <p class="mt-0.5 text-xs text-on-surface-variant">tenant aktif</p>
                            </div>
                        </div>

                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <div>
                                <div class="flex items-center justify-between text-xs text-on-surface-variant">
                                    <span>Beban (weight)</span>
                                    <span class="tabular-nums font-semibold text-on-surface">{{ shard.current_weight }}<template v-if="shard.maximum_weight"> / {{ shard.maximum_weight }}</template></span>
                                </div>
                                <div v-if="weightPercent(shard) !== null" class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-container-high">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="{
                                            'bg-error': weightPercent(shard) >= 80,
                                            'bg-tertiary': weightPercent(shard) >= 60 && weightPercent(shard) < 80,
                                            'bg-secondary': weightPercent(shard) < 60,
                                        }"
                                        :style="{ width: `${weightPercent(shard)}%` }"
                                    />
                                </div>
                                <div v-else class="mt-1 h-1.5 rounded-full bg-surface-container-high" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-on-surface-variant">Versi Skema</p>
                                <p class="mt-0.5 truncate text-sm font-semibold" :title="shard.schema_version?.current_version" :class="shard.schema_version?.current_version === shard.schema_version?.target_version ? 'text-primary' : 'text-warning'">
                                    {{ shard.schema_version?.current_version ?? '—' }}
                                </p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-on-surface-variant">Target Skema</p>
                                <p class="mt-0.5 truncate text-sm font-semibold text-primary" :title="shard.schema_version?.target_version">
                                    {{ shard.schema_version?.target_version ?? '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </AppCard>

            <!-- Riwayat Cutover Run -->
            <AppCard>
                <template #header>
                    <div class="flex items-center gap-2">
                        <AppIcon name="history" class="text-primary" />
                        <h2 class="font-bold text-primary">Riwayat Cutover</h2>
                        <span class="rounded-full bg-surface-container-low px-2 py-0.5 text-xs font-bold text-on-surface-variant">{{ runs.total || 0 }}</span>
                    </div>
                </template>

                <SmartDataTable
                    :rows="runs.data"
                    :columns="runColumns"
                    :pagination="runs"
                    url="/admin/shards"
                    :per-page="perPage"
                    empty-title="Belum ada riwayat cutover"
                    empty-description="Run cutover data tenant akan tercatat di sini setelah dieksekusi."
                >
                    <template #cell-created_at="{ row }">
                        <span class="whitespace-nowrap font-medium tabular-nums text-on-surface">{{ row.created_at }}</span>
                    </template>
                    <template #cell-tenant_name="{ row }">
                        <span class="block font-semibold text-primary">{{ row.tenant_name }}</span>
                        <span class="block text-xs text-on-surface-variant">{{ row.tenant_code }}</span>
                    </template>
                    <template #cell-mode="{ row }">
                        <AppBadge :tone="row.is_dry_run ? 'neutral' : 'warning'" class="whitespace-nowrap">
                            {{ row.is_dry_run ? 'Dry-run' : 'Produksi' }}
                        </AppBadge>
                    </template>
                    <template #cell-progress="{ row }">
                        <span class="whitespace-nowrap tabular-nums text-on-surface">{{ row.steps_ok }}/{{ row.steps_total }} step</span>
                    </template>
                    <template #cell-status="{ row }">
                        <div class="space-y-0.5">
                            <AppBadge :tone="runStatusTone(row.status)" class="whitespace-nowrap capitalize">{{ row.status }}</AppBadge>
                            <p v-if="row.error_message" class="max-w-[180px] truncate text-xs text-error" :title="row.error_message">{{ row.error_message }}</p>
                        </div>
                    </template>
                </SmartDataTable>
            </AppCard>
        </div>
    </AdminLayout>
</template>
