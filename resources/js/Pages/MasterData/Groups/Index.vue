<script setup>
import { useI18n } from '../../../i18n';
const { t } = useI18n();
import { Head, Link } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import CsvImportExport from '../../../Components/CsvImportExport.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';

const { can } = useCan();

defineProps({
    groups: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'name' },
    direction: { type: String, default: 'asc' },
});

const columns = [
    { key: 'code', label: t('Kode'), sortable: true },
    { key: 'name', label: t('Nama Kelompok'), sortable: true },
    { key: 'village', label: t('Desa') },
    { key: 'members_count', label: t('Anggota'), sortable: true },
    { key: 'chair', label: t('Ketua') },
    { key: 'established_at', label: t('Berdiri'), sortable: true },
    { key: 'status', label: t('Status'), sortable: true },
];

const csvColumns = ['nama', 'desa', 'alamat', 'no_hp', 'tanggal_berdiri', 'status'];
const statusLabels = { active: 'Aktif', inactive: 'Tidak aktif' };
</script>

<template>
    <Head :title="t('Kelompok')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-4 sm:space-y-6">
            <header class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center sm:gap-4">
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold leading-tight text-primary sm:text-3xl">{{ t('Kelompok') }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ t('Kelola data, anggota, dan pengurus kelompok.') }}</p>
                </div>
                <div v-if="can('groups.manage')" class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
                    <CsvImportExport
                        export-url="/master-data/groups/export"
                        import-url="/master-data/groups/import"
                        :columns="csvColumns"
                        :title="t('Impor Kelompok')"
                        :hint="t('Import minimal: hanya shell kelompok. Anggota & pengurus dilengkapi lewat form edit. Duplikat nama+desa dilewati.')"
                    />
                    <Link href="/master-data/groups/create" class="contents">
                        <AppButton icon="add" class="w-full justify-center sm:w-auto">
                            {{ t('Tambah Kelompok') }}
                        </AppButton>
                    </Link>
                </div>
            </header>
            <AppCard :padded="false">
                <SmartDataTable
                    :rows="groups.data"
                    :columns="columns"
                    :pagination="groups"
                    url="/master-data/groups"
                    :search="search"
                    :per-page="perPage"
                    :sort="sort"
                    :direction="direction"
                    :row-href="(row) => `/master-data/groups/${row.row_id}`"
                    search-label="Cari kelompok"
                    :search-placeholder="t('Cari kelompok...')"
                    empty-title="Belum ada kelompok"
                    empty-description="Tambahkan kelompok untuk mulai mengelola anggota dan pengurus."
                >
                    <template #cell-name="{ row }">
                        <Link :href="`/master-data/groups/${row.row_id}`" class="font-semibold text-primary hover:underline">
                            {{ row.name }}
                        </Link>
                        <span class="block font-mono text-xs text-on-surface-variant">{{ row.code }}</span>
                    </template>
                    <template #cell-village="{ row }">{{ row.village?.name || '—' }}</template>
                    <template #cell-members_count="{ row }">
                        <span class="font-semibold tabular-nums">{{ row.members_count }}</span>
                        <span class="ml-1 text-xs text-on-surface-variant">orang</span>
                    </template>
                    <template #cell-status="{ row }">
                        <AppBadge :tone="row.status === 'active' ? 'success-soft' : 'neutral'">
                            {{ statusLabels[row.status] || row.status }}
                        </AppBadge>
                    </template>
                </SmartDataTable>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
