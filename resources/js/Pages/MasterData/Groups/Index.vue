<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { useConfirm } from '../../../composables/useConfirm';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import CsvImportExport from '../../../Components/CsvImportExport.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';

const { can } = useCan();
const { confirm: confirmAction } = useConfirm();

async function confirmDelete(row) {
    if (!await confirmAction({
        title: 'Hapus Kelompok',
        message: `Apakah Anda yakin ingin menghapus kelompok "${row.name}"? Penghapusan hanya berhasil jika kelompok belum pernah mengajukan pinjaman dan tidak memiliki anggota aktif.`,
        confirmText: 'Ya, Hapus',
        variant: 'danger',
    })) return;

    router.delete(`/master-data/groups/${row.row_id}`, { preserveScroll: true });
}

defineProps({
    groups: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'name' },
    direction: { type: String, default: 'asc' },
});

const columns = [
    { key: 'code', label: 'Kode', sortable: true },
    { key: 'name', label: 'Nama Kelompok', sortable: true },
    { key: 'village', label: 'Desa' },
    { key: 'members_count', label: 'Anggota', sortable: true },
    { key: 'chair', label: 'Ketua' },
    { key: 'established_at', label: 'Berdiri', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
];

const csvColumns = ['nama', 'desa', 'alamat', 'no_hp', 'tanggal_berdiri', 'status'];
</script>

<template>
    <Head title="Kelompok" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div><h1 class="text-2xl font-bold text-primary">Kelompok</h1><p class="mt-1 text-on-surface-variant">Kelola data, anggota, dan pengurus kelompok.</p></div>
                <div v-if="can('groups.manage')" class="flex flex-wrap items-center gap-2">
                    <CsvImportExport
                        export-url="/master-data/groups/export"
                        import-url="/master-data/groups/import"
                        :columns="csvColumns"
                        title="Impor Kelompok"
                        hint="Import minimal: hanya shell kelompok. Anggota & pengurus dilengkapi lewat form edit. Duplikat nama+desa dilewati."
                    />
                    <Link href="/master-data/groups/create"><AppButton icon="add">Tambah Kelompok</AppButton></Link>
                </div>
            </header>
            <AppCard :padded="false">
                <div class="p-6">
                    <SmartDataTable :rows="groups.data" :columns="columns" :pagination="groups" url="/master-data/groups" :search="search" :per-page="perPage" :sort="sort" :direction="direction" search-label="Cari kelompok" search-placeholder="Kode, nama, atau desa" empty-title="Belum ada kelompok" empty-description="Tambahkan kelompok untuk mulai mengelola anggota dan pengurus.">
                        <template #cell-name="{ row }">
                            <Link :href="`/master-data/groups/${row.row_id}`" class="font-semibold text-primary hover:underline">
                                {{ row.name }}
                            </Link>
                        </template>
                        <template #cell-village="{ row }">{{ row.village?.name || '—' }}</template>
                        <template #cell-members_count="{ row }">{{ row.members_count }} orang</template>
                        <template #cell-status="{ row }">
                            <AppBadge :tone="row.status === 'active' ? 'success' : 'neutral'">
                                {{ row.status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                            </AppBadge>
                        </template>
                        <template #actions="{ row }">
                            <div class="flex justify-end gap-1">
                                <Link :href="`/master-data/groups/${row.row_id}`">
                                    <AppButton variant="ghost" size="compact" icon="visibility">Detail</AppButton>
                                </Link>
                                <Link v-if="can('groups.manage')" :href="`/master-data/groups/${row.row_id}/edit`">
                                    <AppButton variant="ghost" size="compact" icon="edit">Edit</AppButton>
                                </Link>
                            </div>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
