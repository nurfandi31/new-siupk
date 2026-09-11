<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';
import { useConfirm } from '../../../composables/useConfirm';

const props = defineProps({
    pages: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'title' },
    direction: { type: String, default: 'asc' },
});

const { can } = useCan();
const { confirm: confirmAction } = useConfirm();

const columns = [
    { key: 'title', label: 'Judul', sortable: true },
    { key: 'slug', label: 'Slug', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'published_at', label: 'Tanggal Terbit', sortable: true },
];

function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' });
}

async function destroy(page) {
    if (!await confirmAction({
        title: 'Hapus Halaman',
        message: `Pindahkan halaman "${page.title}" ke sampah?`,
    })) return;
    router.delete(`/website/pages/${page.row_id}`, { preserveScroll: true });
}

async function restore(page) {
    if (!await confirmAction({
        title: 'Pulihkan Halaman',
        message: `Pulihkan halaman "${page.title}"?`,
    })) return;
    router.post(`/website/pages/${page.row_id}/restore`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Halaman Website" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Halaman Website</h1>
                    <p class="mt-1 text-on-surface-variant">Kelola halaman statis (Tentang, Program, Kontak, dsb.) yang tampil di situs publik.</p>
                </div>
                <div v-if="can('website.manage')">
                    <Link href="/website/pages/create"><AppButton icon="add">Buat Halaman</AppButton></Link>
                </div>
            </header>
            <AppCard :padded="false">
                <div class="p-6">
                    <SmartDataTable
                        :rows="pages.data"
                        :columns="columns"
                        :pagination="pages"
                        url="/website/pages"
                        :search="search"
                        :per-page="perPage"
                        :sort="sort"
                        :direction="direction"
                        search-label="Cari halaman"
                        search-placeholder="Judul atau slug"
                        empty-title="Belum ada halaman"
                        empty-description="Buat halaman statis seperti Tentang Kami atau Program Kerja."
                    >
                        <template #cell-title="{ row }">
                            <Link :href="`/website/pages/${row.row_id}/edit`" class="font-semibold text-primary hover:underline">
                                {{ row.title }}
                            </Link>
                        </template>
                        <template #cell-slug="{ row }">
                            <span class="text-xs text-on-surface-variant">/p/{{ row.slug }}</span>
                        </template>
                        <template #cell-status="{ row }">
                            <AppBadge :tone="row.status === 'published' ? 'success' : 'neutral'">
                                {{ row.status === 'published' ? 'Terbit' : 'Draf' }}
                            </AppBadge>
                            <span v-if="row.deleted_at" class="ml-1 inline-flex"><AppBadge tone="error">Terhapus</AppBadge></span>
                        </template>
                        <template #cell-published_at="{ row }">
                            {{ formatDateTime(row.published_at) }}
                        </template>
                        <template #actions="{ row }">
                            <div class="flex justify-end gap-1">
                                <template v-if="row.deleted_at">
                                    <AppButton v-if="can('website.manage')" variant="ghost" size="compact" icon="restore" aria-label="Pulihkan halaman" @click="restore(row)">Pulihkan</AppButton>
                                </template>
                                <template v-else>
                                    <Link v-if="can('website.manage')" :href="`/website/pages/${row.row_id}/edit`">
                                        <AppButton variant="ghost" size="compact" icon="edit" aria-label="Edit halaman">Edit</AppButton>
                                    </Link>
                                    <AppButton v-if="can('website.manage')" variant="ghost" size="compact" icon="delete" aria-label="Hapus halaman" @click="destroy(row)">Hapus</AppButton>
                                </template>
                            </div>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
