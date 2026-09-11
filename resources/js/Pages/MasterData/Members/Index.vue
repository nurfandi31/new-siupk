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
        title: 'Hapus Anggota',
        message: `Apakah Anda yakin ingin menghapus anggota "${row.name}"? Penghapusan hanya berhasil jika anggota tidak terdaftar di kelompok dan tidak memiliki riwayat pinjaman.`,
        confirmText: 'Ya, Hapus',
        variant: 'danger',
    })) return;

    router.delete(`/master-data/members/${row.row_id}`, { preserveScroll: true });
}

defineProps({
    members: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'name' },
    direction: { type: String, default: 'asc' },
});

const columns = [
    { key: 'nik', label: 'NIK', sortable: true },
    { key: 'name', label: 'Nama Anggota', sortable: true },
    { key: 'village', label: 'Desa' },
    { key: 'phone', label: 'No. HP', sortable: true },
    { key: 'registered_at', label: 'Terdaftar', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
];

const csvColumns = ['nik', 'nama', 'jenis_kelamin', 'alamat', 'desa', 'no_hp', 'status'];
const statusLabels = { active: 'Aktif', exited: 'Keluar', deceased: 'Meninggal' };
</script>

<template>
    <Head title="Anggota" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Anggota</h1>
                    <p class="mt-1 text-on-surface-variant">Kelola data anggota tenant.</p>
                </div>
                <div v-if="can('members.manage')" class="flex flex-wrap items-center gap-2">
                    <CsvImportExport
                        export-url="/master-data/members/export"
                        import-url="/master-data/members/import"
                        :columns="csvColumns"
                        title="Impor Anggota"
                        hint="Kolom minimal. jenis_kelamin: L/P. desa = nama desa aktif. NIK duplikat dilewati."
                    />
                    <Link href="/master-data/members/create"><AppButton icon="add">Tambah Anggota</AppButton></Link>
                </div>
            </header>
            <AppCard :padded="false"><div class="p-6"><SmartDataTable :rows="members.data" :columns="columns" :pagination="members" url="/master-data/members" :search="search" :per-page="perPage" :sort="sort" :direction="direction" search-label="Cari anggota" search-placeholder="NIK, nama, atau nomor HP" empty-title="Belum ada anggota" empty-description="Tambahkan anggota untuk mulai mengelola data anggota."><template #cell-name="{ row }">
                            <Link :href="`/master-data/members/${row.row_id}`" class="font-semibold text-primary hover:underline">
                                {{ row.name }}
                            </Link>
                            <span class="block text-xs text-on-surface-variant">{{ row.member_number }}</span>
                        </template>
                        <template #cell-village="{ row }">{{ row.village?.name || '—' }}</template>
                        <template #cell-status="{ row }">
                            <AppBadge :tone="row.status === 'active' ? 'success' : 'neutral'">
                                {{ statusLabels[row.status] || row.status }}
                            </AppBadge>
                        </template>
                        <template #actions="{ row }">
                            <div class="flex justify-end gap-1">
                                <Link :href="`/master-data/members/${row.row_id}`">
                                    <AppButton variant="ghost" size="compact" icon="visibility" aria-label="Detail anggota">Detail</AppButton>
                                </Link>
                                <Link v-if="can('members.manage')" :href="`/master-data/members/${row.row_id}/edit`">
                                    <AppButton variant="ghost" size="compact" icon="edit" aria-label="Edit anggota">Edit</AppButton>
                                </Link>
                            </div>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
