<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppAccordion from '../../../Components/AppAccordion.vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
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

const canManage = computed(() => can('groups.manage'));

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

const statusLabels = { active: 'Aktif', inactive: 'Tidak aktif' };
const positionLabels = {
    chair: 'Ketua',
    secretary: 'Sekretaris',
    treasurer: 'Bendahara',
};
const positionIcons = {
    chair: 'workspace_premium',
    secretary: 'edit_note',
    treasurer: 'account_balance',
};
const officerOrder = ['chair', 'secretary', 'treasurer'];

const profileRows = computed(() => [
    { label: 'Kode', value: props.group.code || '—', icon: 'tag' },
    { label: 'Desa', value: props.group.village?.name || '—', icon: 'location_city' },
    { label: 'Tanggal berdiri', value: formatDate(props.group.established_at), icon: 'event' },
    { label: 'No. HP', value: props.group.phone || '—', icon: 'phone' },
    { label: 'Jenis usaha', value: props.group.business_type || '—', icon: 'storefront' },
    { label: 'Jenis kegiatan', value: props.group.activity_type || '—', icon: 'event_note' },
    { label: 'Tingkatan', value: props.group.level || '—', icon: 'military_tech' },
    { label: 'Fungsi kelompok', value: props.group.function || '—', icon: 'workspaces' },
]);

const officers = computed(() => {
    const list = props.group.officers || [];
    const map = new Map(list.map((o) => [o.position, o]));
    return officerOrder.map((position) => {
        const existing = map.get(position);
        return existing
            ? { position, ...existing }
            : { position, name: null, member_href: null };
    });
});

const accordionItems = computed(() => [
    { key: 'profile', title: 'Profil Kelompok', icon: 'info', defaultOpen: true },
    { key: 'address', title: 'Alamat', icon: 'home', subtitle: props.group.address ? null : 'Belum diisi' },
    { key: 'officers', title: 'Pengurus', icon: 'workspace_premium' },
    { key: 'members', title: 'Anggota', icon: 'people', badge: props.group.members_count ?? 0 },
]);
</script>

<template>
    <Head :title="`Kelompok · ${group.name}`" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Header utama -->
            <header>
                <div class="flex flex-wrap items-center gap-2">
                    <AppIcon name="groups" tone="primary" :container-size="11" />
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">{{ group.name }}</h1>
                    <AppBadge :tone="group.status === 'active' ? 'success' : 'neutral'">
                        {{ statusLabels[group.status] || group.status }}
                    </AppBadge>
                </div>
                <p class="mt-1 text-sm text-on-surface-variant">
                    <span class="font-mono">{{ group.code || '—' }}</span>
                    <span v-if="group.village?.name"> · {{ group.village.name }}</span>
                </p>
            </header>

            <!-- Ringkasan KPI -->
            <div class="grid gap-3 sm:grid-cols-3">
                <AppCard>
                    <div class="flex items-center gap-3">
                        <AppIcon name="groups" tone="primary" :container-size="11" />
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Anggota aktif</p>
                            <p class="text-2xl font-bold text-primary">{{ group.members_count }}</p>
                        </div>
                    </div>
                </AppCard>
                <AppCard>
                    <div class="flex items-center gap-3">
                        <AppIcon name="receipt_long" tone="secondary" :container-size="11" />
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Riwayat pinjaman</p>
                            <p class="text-2xl font-bold text-primary">{{ summary.loan_count }}</p>
                            <p class="text-xs text-on-surface-variant">{{ summary.active_loan_count }} aktif</p>
                        </div>
                    </div>
                </AppCard>
                <AppCard>
                    <div class="flex items-center gap-3">
                        <AppIcon name="payments" tone="warning" :container-size="11" />
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sisa pokok</p>
                            <p class="text-2xl font-bold tabular-nums text-primary">Rp {{ formatMoney(summary.principal_remaining) }}</p>
                        </div>
                    </div>
                </AppCard>
            </div>

            <!-- Konten utama: 2 kolom di lg+ -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Kolom kiri: accordion profil + pengurus + alamat + anggota -->
                <div class="lg:col-span-1">
                    <AppAccordion
                        multiple
                        :items="accordionItems"
                    >
                        <template #content-profile>
                            <dl class="space-y-3">
                                <div v-for="row in profileRows" :key="row.label" class="flex items-start justify-between gap-4">
                                    <dt class="flex items-center gap-2 text-sm text-on-surface-variant">
                                        <AppIcon :name="row.icon" class="text-base text-outline" />
                                        {{ row.label }}
                                    </dt>
                                    <dd class="text-right text-sm font-medium text-primary">{{ row.value }}</dd>
                                </div>
                            </dl>
                        </template>
                        <template #content-address>
                            <p v-if="group.address" class="text-sm leading-relaxed text-primary">{{ group.address }}</p>
                            <p v-else class="text-sm text-on-surface-variant">Belum ada alamat.</p>
                        </template>
                        <template #content-officers>
                            <ul class="space-y-3">
                                <li v-for="o in officers" :key="o.position" class="flex items-center justify-between gap-3">
                                    <span class="flex items-center gap-2 text-sm text-on-surface-variant">
                                        <AppIcon :name="positionIcons[o.position]" class="text-base text-outline" />
                                        {{ positionLabels[o.position] || o.position }}
                                    </span>
                                    <Link
                                        v-if="o.member_href"
                                        :href="o.member_href"
                                        class="truncate text-right text-sm font-semibold text-primary hover:underline"
                                    >{{ o.name || '—' }}</Link>
                                    <span v-else class="truncate text-right text-sm text-on-surface-variant">—</span>
                                </li>
                            </ul>
                        </template>
                        <template #content-members>
                            <ul v-if="group.members?.length" class="max-h-72 space-y-1 overflow-y-auto pr-1">
                                <li v-for="m in group.members" :key="m.row_id" class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm transition-colors hover:bg-surface-container-low">
                                    <Link :href="m.href" class="min-w-0 truncate font-semibold text-primary hover:underline">
                                        {{ m.name || '—' }}
                                    </Link>
                                    <span class="shrink-0 font-mono text-xs text-on-surface-variant">{{ m.member_number || m.row_id }}</span>
                                </li>
                            </ul>
                            <p v-else class="text-sm text-on-surface-variant">Belum ada anggota aktif.</p>
                        </template>
                    </AppAccordion>
                </div>

                <!-- Kolom kanan: riwayat pinjaman -->
                <AppCard class="overflow-hidden p-0 lg:col-span-2">
                    <template #header>
                        <div class="flex items-center gap-2">
                            <AppIcon name="receipt_long" tone="primary" :container-size="9" />
                            <h2 class="text-sm font-bold text-primary">Riwayat Pinjaman</h2>
                        </div>
                        <p class="text-xs text-on-surface-variant">Klik nomor pinjaman untuk membuka detail.</p>
                    </template>
                    <LoanHistoryTable fill :loans="loans" empty-description="Kelompok ini belum memiliki pinjaman." />
                </AppCard>
            </div>

            <!-- Action bar (paling bawah: Kembali · Edit · Hapus) -->
            <AppCard class="!rounded-md !p-1.5 shadow-none sm:!p-2 md:!p-2.5">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-2 md:gap-3">
                    <Link href="/master-data/groups" class="contents">
                        <AppButton variant="secondary" icon="arrow_back" size="compact">
                            Kembali ke daftar
                        </AppButton>
                    </Link>
                    <div class="flex flex-wrap items-center gap-1 sm:justify-end">
                        <Link v-if="canManage" :href="`/master-data/groups/${group.row_id}/edit`" class="contents">
                            <AppButton variant="tertiary" icon="edit" size="compact">
                                Edit kelompok
                            </AppButton>
                        </Link>
                        <AppButton
                            v-if="canManage"
                            variant="danger"
                            icon="delete_outline"
                            size="compact"
                            @click="confirmDelete"
                        >
                            Hapus kelompok
                        </AppButton>
                    </div>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
