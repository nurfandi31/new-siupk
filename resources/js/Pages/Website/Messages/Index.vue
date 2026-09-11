<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import AppModal from '../../../Components/AppModal.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';
import { useConfirm } from '../../../composables/useConfirm';

const props = defineProps({
    messages: { type: Object, required: true },
    search: { type: String, default: '' },
    unreadCount: { type: Number, default: 0 },
});

const page = usePage();
const { can } = useCan();
const { confirm } = useConfirm();

const canManage = computed(() => can('website.manage'));
const q = ref(props.search);
const detail = ref(null);
const showDetail = ref(false);

function applySearch() {
    router.get(route('website.messages.index'), { q: q.value || undefined }, { preserveState: true, preserveScroll: true });
}

function openDetail(row) {
    detail.value = row;
    showDetail.value = true;
    if (!row.is_read && canManage.value) {
        router.post(route('website.messages.read', row.row_id), {}, { preserveScroll: true });
        row.is_read = true;
    }
}

function remove(row) {
    confirm({
        title: 'Hapus pesan?',
        message: `Pesan dari "${row.name}" akan dihapus permanen.`,
        confirmText: 'Hapus',
        variant: 'danger',
        onConfirm: () => router.delete(route('website.messages.destroy', row.row_id)),
    });
}

function markRead(row) {
    router.post(route('website.messages.read', row.row_id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Pesan Masuk" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Pesan Masuk</h1>
                    <p class="mt-1 text-on-surface-variant">
                        Pesan dari formulir kontak publik
                        <span v-if="unreadCount" class="ml-2 inline-flex items-center rounded-full bg-warning-container px-2 py-0.5 text-xs font-bold text-on-warning-container">{{ unreadCount }} belum dibaca</span>
                    </p>
                </div>
            </header>
            <AppCard :padded="false">
                <div class="p-6">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex min-w-[16rem] flex-1 items-center gap-2">
                            <input v-model="q" type="search" placeholder="Cari nama / subjek / isi..." class="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" @keydown.enter="applySearch" />
                            <AppButton variant="secondary" size="compact" icon="search" @click="applySearch">Cari</AppButton>
                        </div>
                    </div>

                    <div v-if="messages.data.length === 0" class="py-8">
                        <AppEmptyState icon="inbox" title="Belum ada pesan" description="Pesan dari halaman kontak publik akan tampil di sini." />
                    </div>

                    <div v-else class="mt-5 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                        <thead class="border-b border-outline-variant text-xs font-bold uppercase tracking-wider text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2">Pengirim</th>
                                <th class="px-3 py-2">Subjek</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Tanggal</th>
                                <th class="px-3 py-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/60">
                            <tr v-for="row in messages.data" :key="row.row_id" class="hover:bg-surface-container-low/60" :class="!row.is_read && 'bg-warning-container/20'">
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-primary">{{ row.name }}</p>
                                    <p v-if="row.email || row.phone" class="text-xs text-on-surface-variant">{{ [row.email, row.phone].filter(Boolean).join(' · ') }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="max-w-[20rem] truncate font-medium">{{ row.subject || '—' }}</p>
                                    <p class="max-w-[20rem] truncate text-xs text-on-surface-variant">{{ row.message }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <AppBadge :tone="row.is_read ? 'neutral' : 'warning'">{{ row.is_read ? 'Sudah dibaca' : 'Baru' }}</AppBadge>
                                </td>
                                <td class="px-3 py-3 text-xs text-on-surface-variant">{{ row.created_at ? new Date(row.created_at).toLocaleString('id-ID') : '—' }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <AppButton variant="outline" size="compact" icon="visibility" @click="openDetail(row)">Lihat</AppButton>
                                        <AppButton v-if="!row.is_read && canManage" variant="secondary" size="compact" icon="mark_email_read" @click="markRead(row)">Tandai dibaca</AppButton>
                                        <AppButton v-if="canManage" variant="danger" size="compact" icon="delete" @click="remove(row)">Hapus</AppButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        </table>
                    </div>

                    <div v-if="messages.links?.length > 3" class="mt-4 flex flex-wrap gap-1.5">
                        <Link v-for="link in messages.links" :key="link.label" :href="link.url ?? '#'" :class="['rounded-lg px-3 py-1.5 text-sm', link.active ? 'bg-primary text-on-primary' : 'border border-outline-variant hover:bg-surface-container-low', !link.url && 'pointer-events-none opacity-40']" v-html="link.label" />
                    </div>
                    </div>
                </AppCard>

            <AppModal v-model="showDetail" :title="detail ? `Pesan dari ${detail.name}` : 'Detail pesan'" size="md">
                <div v-if="detail" class="space-y-3 text-sm">
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div><p class="font-bold uppercase tracking-wider text-on-surface-variant">Email</p><p class="mt-1 text-primary">{{ detail.email || '—' }}</p></div>
                        <div><p class="font-bold uppercase tracking-wider text-on-surface-variant">Telepon</p><p class="mt-1 text-primary">{{ detail.phone || '—' }}</p></div>
                    </div>
                    <div v-if="detail.subject"><p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Subjek</p><p class="mt-1 font-semibold text-primary">{{ detail.subject }}</p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pesan</p><p class="mt-1 whitespace-pre-wrap leading-relaxed text-on-surface">{{ detail.message }}</p></div>
                    <p class="text-xs text-on-surface-variant">{{ detail.created_at ? new Date(detail.created_at).toLocaleString('id-ID') : '' }}</p>
                </div>
                <template #footer>
                    <AppButton variant="secondary" @click="showDetail = false">Tutup</AppButton>
                </template>
            </AppModal>
        </div>
    </AuthenticatedLayout>
</template>
