<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';

const { can } = useCan();

const props = defineProps({
    villages: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'name' },
    direction: { type: String, default: 'asc' },
});

const columns = [
    { key: 'code', label: 'Kode', sortable: true },
    { key: 'name', label: 'Nama Desa', sortable: true },
    { key: 'village_naming', label: 'Sebutan' },
    { key: 'village_head_name', label: 'Kepala Desa/Lurah', sortable: true },
    { key: 'is_active', label: 'Status' },
];
</script>

<template>
    <Head title="Daftar Desa" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary">Daftar Desa</h1>
                <p class="mt-1 text-on-surface-variant">Data desa berasal dari kecamatan tenant.</p>
            </header>
            <AppCard :padded="false">
                <div class="p-6">
                    <SmartDataTable
                        :rows="villages.data"
                        :columns="columns"
                        :pagination="villages"
                        url="/master-data/villages"
                        :search="search"
                        :per-page="perPage"
                        :sort="sort"
                        :direction="direction"
                        search-label="Cari desa"
                        search-placeholder="Kode atau nama desa"
                        empty-title="Belum ada desa"
                        empty-description="Desa akan dibuat otomatis saat tenant didaftarkan."
                    >
                        <template #cell-village_naming="{ row }">{{ row.village_naming?.village_name || '—' }}</template>
                        <template #cell-is_active="{ row }"><AppBadge :tone="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</AppBadge></template>
                        <template #actions="{ row }">
                            <Link v-if="can('villages.manage')" :href="`/master-data/villages/${row.row_id}/edit`">
                                <AppButton variant="ghost" size="compact" icon="edit" aria-label="Edit desa">Edit</AppButton>
                            </Link>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
