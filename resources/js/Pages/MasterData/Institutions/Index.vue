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
    institutions: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'name' },
    direction: { type: String, default: 'asc' },
});

const columns = [
    { key: 'code', label: t('Kode'), sortable: true },
    { key: 'name', label: t('Nama Lembaga'), sortable: true },
    { key: 'village', label: t('Desa') },
    { key: 'leader_name', label: t('Pimpinan'), sortable: true },
    { key: 'is_active', label: t('Status') },
];

const csvColumns = ['nama', 'desa', 'nomor_identitas', 'pimpinan', 'penanggungjawab', 'alamat', 'no_hp', 'status'];
</script>

<template>
    <Head :title="t('Lembaga Lain')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-4">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">{{ t('Lembaga Lain') }}</h1>
                    <p class="mt-1 text-on-surface-variant">{{ t('Kelola lembaga penerima layanan di luar anggota dan kelompok.') }}</p>
                </div>
                <div v-if="can('institutions.manage')" class="flex flex-wrap items-center gap-2">
                    <CsvImportExport
                        export-url="/master-data/institutions/export"
                        import-url="/master-data/institutions/import"
                        :columns="csvColumns"
                        :title="t('Impor Lembaga Lain')"
                        hint="nomor_identitas wajib & unik. status: aktif/nonaktif. Duplikat nomor identitas dilewati."
                    />
                    <Link href="/master-data/institutions/create"><AppButton icon="add">{{ t('Tambah Lembaga') }}</AppButton></Link>
                </div>
            </header>
            <AppCard :padded="false">
                <SmartDataTable
                        :rows="institutions.data"
                        :columns="columns"
                        :pagination="institutions"
                        url="/master-data/institutions"
                        :search="search"
                        :per-page="perPage"
                        :sort="sort"
                        :direction="direction"
                        search-label="Cari lembaga"
                        :search-placeholder="t('Cari institusi...')"
                        empty-title="Belum ada lembaga"
                        empty-description="Tambahkan lembaga lain untuk mulai mengelola master data."
                    >
                        <template #cell-name="{ row }">
                            <Link :href="`/master-data/institutions/${row.row_id}`" class="font-semibold text-primary hover:underline">
                                {{ row.name }}
                            </Link>
                            <span class="block text-xs text-on-surface-variant">{{ row.institution_identity_number }}</span>
                        </template>
                        <template #cell-village="{ row }">{{ row.village?.name || '—' }}</template>
                        <template #cell-is_active="{ row }">
                            <AppBadge :tone="row.is_active ? 'success' : 'neutral'">
                                {{ row.is_active ? t('Aktif') : t('Nonaktif') }}
                            </AppBadge>
                        </template>
                        <template #actions="{ row }">
                            <div class="flex justify-end gap-1">
                                <Link :href="`/master-data/institutions/${row.row_id}`">
                                    <AppButton variant="ghost" size="compact" icon="visibility" :aria-label="t('Detail lembaga')">{{ t('Detail') }}</AppButton>
                                </Link>
                                <Link v-if="can('institutions.manage')" :href="`/master-data/institutions/${row.row_id}/edit`">
                                    <AppButton variant="ghost" size="compact" icon="edit" :aria-label="t('Edit lembaga')">{{ t('Edit') }}</AppButton>
                                </Link>
                            </div>
                        </template>
                    </SmartDataTable>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
