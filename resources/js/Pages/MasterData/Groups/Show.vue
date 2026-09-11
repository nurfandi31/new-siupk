<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import LoanHistoryTable from '../../../Components/LoanHistoryTable.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';
import { useConfirm } from '../../../composables/useConfirm';

const props = defineProps({
    group: { type: Object, required: true },
    loans: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
});

const { can } = useCan();
const { confirm: confirmAction } = useConfirm();

async function confirmDelete() {
    if (!await confirmAction({
        title: 'Hapus Kelompok',
        message: `Apakah Anda yakin ingin menghapus kelompok "${props.group.name}"? Penghapusan hanya berhasil jika kelompok belum pernah memiliki riwayat pinjaman dan tidak memiliki anggota aktif.`,
        confirmText: 'Ya, Hapus Kelompok',
        variant: 'danger',
    })) return;

    router.delete(`/master-data/groups/${props.group.row_id}`);
}

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}
function formatDate(v) {
    if (!v) return '—';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return v;
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
}

const positionLabels = {
    chair: 'Ketua',
    secretary: 'Sekretaris',
    treasurer: 'Bendahara',
};
</script>

<template>
    <Head :title="`Kelompok · ${group.name}`" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Link href="/master-data/groups" class="text-sm font-semibold text-primary hover:underline">
                        ← Daftar kelompok
                    </Link>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold text-primary">{{ group.name }}</h1>
                        <AppBadge :tone="group.status === 'active' ? 'success' : 'neutral'">
                            {{ group.status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                        </AppBadge>
                    </div>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        #{{ group.id }} · {{ group.code }}
                        <span v-if="group.village?.name"> · {{ group.village.name }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="can('groups.manage')" :href="`/master-data/groups/${group.row_id}/edit`">
                        <AppButton variant="secondary" icon="edit" size="compact">Edit</AppButton>
                    </Link>
                    <AppButton
                        v-if="can('groups.manage')"
                        variant="danger"
                        icon="delete_outline"
                        size="compact"
                        @click="confirmDelete"
                    >
                        Hapus Kelompok
                    </AppButton>
                </div>
            </header>

            <div class="grid gap-3 sm:grid-cols-3">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Anggota aktif</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ group.members_count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Riwayat pinjaman</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ summary.loan_count }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ summary.active_loan_count }} aktif</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sisa pokok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(summary.principal_remaining) }}</p>
                </AppCard>
            </div>

            <div class="grid gap-6 lg:grid-cols-5">
                <div class="space-y-6 lg:col-span-2">
                    <AppCard class="p-5">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">Profil</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-on-surface-variant">Desa</dt>
                                <dd class="font-medium">{{ group.village?.name || '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-on-surface-variant">Berdiri</dt>
                                <dd class="font-medium">{{ formatDate(group.established_at) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-on-surface-variant">No. HP</dt>
                                <dd class="font-medium">{{ group.phone || '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-on-surface-variant">Jenis usaha</dt>
                                <dd class="font-medium">{{ group.business_type || '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-on-surface-variant">Kegiatan</dt>
                                <dd class="font-medium">{{ group.activity_type || '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-on-surface-variant">Tingkat</dt>
                                <dd class="font-medium">{{ group.level || '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-on-surface-variant">Fungsi</dt>
                                <dd class="font-medium">{{ group.function || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-on-surface-variant">Alamat</dt>
                                <dd class="mt-1 font-medium">{{ group.address || '—' }}</dd>
                            </div>
                        </dl>
                    </AppCard>

                    <AppCard class="p-5">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">Pengurus</h2>
                        <ul v-if="group.officers?.length" class="mt-3 space-y-2 text-sm">
                            <li v-for="(o, i) in group.officers" :key="i" class="flex justify-between gap-3">
                                <span class="text-on-surface-variant">{{ positionLabels[o.position] || o.position }}</span>
                                <Link
                                    v-if="o.member_href"
                                    :href="o.member_href"
                                    class="font-semibold text-primary hover:underline"
                                >
                                    {{ o.name || '—' }}
                                </Link>
                                <span v-else class="font-medium">{{ o.name || '—' }}</span>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-on-surface-variant">Belum ada pengurus.</p>
                    </AppCard>

                    <AppCard class="p-5">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">
                            Anggota ({{ group.members_count }})
                        </h2>
                        <ul v-if="group.members?.length" class="mt-3 max-h-64 space-y-1 overflow-y-auto text-sm">
                            <li v-for="m in group.members" :key="m.row_id">
                                <Link :href="m.href" class="font-semibold text-primary hover:underline">
                                    {{ m.name || '—' }}
                                </Link>
                                <span class="text-xs text-on-surface-variant"> · {{ m.member_number || m.row_id }}</span>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-on-surface-variant">Belum ada anggota aktif.</p>
                    </AppCard>
                </div>

                <AppCard class="lg:col-span-3 overflow-hidden p-0">
                    <div class="border-b border-outline-variant px-5 py-4">
                        <h2 class="font-bold text-primary">Riwayat pinjaman kelompok</h2>
                        <p class="text-xs text-on-surface-variant">Klik nomor pinjaman untuk membuka detail.</p>
                    </div>
                    <LoanHistoryTable :loans="loans" empty-description="Kelompok ini belum memiliki pinjaman." />
                </AppCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
