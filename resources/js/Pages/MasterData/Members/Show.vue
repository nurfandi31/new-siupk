<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import LoanHistoryTable from '../../../Components/LoanHistoryTable.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { useCan } from '../../../composables/useCan';
import { useConfirm } from '../../../composables/useConfirm';

const props = defineProps({
    member: { type: Object, required: true },
    loans: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
});

const { can } = useCan();
const { confirm: confirmAction } = useConfirm();

const identityPhotoForm = useForm({
    identity_photo: null,
});

const identityPhotoPreview = computed(() => {
    if (!props.member.identity_photo_path) return '';

    return `/storage/${props.member.identity_photo_path}?v=${props.member.identity_photo_updated_at ?? ''}`;
});

function onIdentityPhotoChange(event) {
    identityPhotoForm.identity_photo = event.target.files?.[0] ?? null;

    if (!identityPhotoForm.identity_photo) return;

    identityPhotoForm.post(`/master-data/members/${props.member.row_id}/identity-photo`, {
        preserveScroll: true,
        onSuccess: () => {
            identityPhotoForm.reset();
            event.target.value = '';
        },
        onError: () => {
            event.target.value = '';
        },
    });
}

async function confirmDeleteIdentityPhoto() {
    if (!await confirmAction({
        title: 'Hapus Foto KTP',
        message: 'Apakah Anda yakin ingin menghapus foto KTP anggota ini?',
        confirmText: 'Ya, Hapus Foto',
        variant: 'danger',
    })) return;

    router.delete(`/master-data/members/${props.member.row_id}/identity-photo`, {
        preserveScroll: true,
    });
}

async function confirmDelete() {
    if (!await confirmAction({
        title: 'Hapus Anggota',
        message: `Apakah Anda yakin ingin menghapus anggota "${props.member.name}"? Penghapusan hanya berhasil jika anggota tidak terdaftar di kelompok dan tidak memiliki riwayat pinjaman.`,
        confirmText: 'Ya, Hapus Anggota',
        variant: 'danger',
    })) return;

    router.delete(`/master-data/members/${props.member.row_id}`);
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

const statusLabels = { active: 'Aktif', exited: 'Keluar', deceased: 'Meninggal' };
const genderLabels = { male: 'Laki-laki', female: 'Perempuan', L: 'Laki-laki', P: 'Perempuan' };
</script>

<template>
    <Head :title="`Anggota · ${member.name || member.member_number}`" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Link href="/master-data/members" class="text-sm font-semibold text-primary hover:underline">
                        ← Daftar anggota
                    </Link>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold text-primary">{{ member.name || '—' }}</h1>
                        <AppBadge :tone="member.status === 'active' ? 'success' : 'neutral'">
                            {{ statusLabels[member.status] || member.status }}
                        </AppBadge>
                    </div>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        #{{ member.id }} · {{ member.member_number || '—' }}
                        <span v-if="member.nik"> · NIK {{ member.nik }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="can('members.manage')" :href="`/master-data/members/${member.row_id}/edit`">
                        <AppButton variant="secondary" icon="edit" size="compact">Edit</AppButton>
                    </Link>
                    <AppButton
                        v-if="can('members.manage')"
                        variant="danger"
                        icon="delete_outline"
                        size="compact"
                        @click="confirmDelete"
                    >
                        Hapus Anggota
                    </AppButton>
                </div>
            </header>

            <div class="grid gap-3 sm:grid-cols-3">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Riwayat pinjaman</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ summary.loan_count }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ summary.active_loan_count }} aktif</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sisa pokok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(summary.principal_remaining) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Kelompok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ (member.groups || []).length }}</p>
                </AppCard>
            </div>

            <div class="grid gap-6 lg:grid-cols-5">
                <AppCard class="lg:col-span-2 p-5">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">Profil</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant">Jenis kelamin</dt>
                            <dd class="font-medium">{{ genderLabels[member.gender] || member.gender || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant">Tempat/tgl lahir</dt>
                            <dd class="text-right font-medium">
                                {{ member.birth_place || '—' }}
                                <span v-if="member.birth_date"> · {{ formatDate(member.birth_date) }}</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant">No. HP</dt>
                            <dd class="font-medium">{{ member.phone || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant">No. KK</dt>
                            <dd class="font-medium">{{ member.family_card_number || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant">Desa</dt>
                            <dd class="font-medium">{{ member.village?.name || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant">Terdaftar</dt>
                            <dd class="font-medium">{{ formatDate(member.registered_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-on-surface-variant">Alamat</dt>
                            <dd class="mt-1 font-medium">{{ member.address || '—' }}</dd>
                        </div>
                    </dl>

                    <div v-if="member.business?.name" class="mt-6 border-t border-outline-variant pt-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant">Usaha</h3>
                        <p class="mt-2 font-semibold text-primary">{{ member.business.name }}</p>
                        <p v-if="member.business.description" class="mt-1 text-sm text-on-surface-variant">
                            {{ member.business.description }}
                        </p>
                    </div>

                    <div v-if="member.guarantor?.name" class="mt-6 border-t border-outline-variant pt-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant">Penjamin</h3>
                        <p class="mt-2 font-semibold">{{ member.guarantor.name }}</p>
                        <p class="text-sm text-on-surface-variant">
                            {{ member.guarantor.relationship || '—' }}
                            <span v-if="member.guarantor.nik"> · NIK {{ member.guarantor.nik }}</span>
                        </p>
                    </div>

                    <div v-if="(member.groups || []).length" class="mt-6 border-t border-outline-variant pt-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant">Keanggotaan kelompok</h3>
                        <ul class="mt-2 space-y-1">
                            <li v-for="g in member.groups" :key="g.row_id">
                                <Link :href="g.href" class="text-sm font-semibold text-primary hover:underline">
                                    {{ g.name }}
                                </Link>
                                <span class="text-xs text-on-surface-variant"> · {{ g.code }}</span>
                            </li>
                        </ul>
                    </div>
                </AppCard>

                <AppCard class="lg:col-span-3">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">Dokumen</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">Foto FC KTP digunakan pada dokumen pinjaman.</p>

                    <div class="mt-4 grid gap-4 md:grid-cols-[10rem_1fr] md:items-start">
                        <div class="grid h-[5.4rem] place-items-center overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest text-center">
                            <img
                                v-if="identityPhotoPreview"
                                :src="identityPhotoPreview"
                                alt="Foto KTP anggota"
                                class="size-full object-contain"
                            />
                            <AppIcon v-else name="badge" class="text-3xl text-on-surface-variant" />
                        </div>

                        <form class="space-y-3" @submit.prevent>
                            <label class="block cursor-pointer rounded-lg border-2 border-dashed border-outline-variant bg-surface-container-lowest p-4 text-center transition-colors hover:border-primary hover:bg-surface-container-low">
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onIdentityPhotoChange" />
                                <AppIcon name="upload" class="text-xl text-on-surface-variant" />
                                <p class="mt-1 text-sm font-semibold text-primary">Ganti foto KTP</p>
                                <p class="text-xs text-on-surface-variant">JPG, PNG, atau WebP · Maks 4 MB</p>
                            </label>

                            <p class="text-sm"><span class="text-on-surface-variant">NIK:</span> {{ member.nik || 'Belum tersedia' }}</p>
                            <p v-if="identityPhotoForm.errors.identity_photo" class="text-sm text-error">
                                {{ identityPhotoForm.errors.identity_photo }}
                            </p>

                            <div class="flex justify-end border-t border-outline-variant pt-3">
                                <AppButton
                                    v-if="member.identity_photo_path"
                                    type="button"
                                    variant="danger"
                                    size="compact"
                                    icon="delete"
                                    :disabled="identityPhotoForm.processing"
                                    @click="confirmDeleteIdentityPhoto"
                                >
                                    Hapus Foto
                                </AppButton>
                            </div>
                        </form>
                    </div>
                </AppCard>

                <AppCard class="lg:col-span-5 overflow-hidden p-0">
                    <div class="border-b border-outline-variant px-5 py-4">
                        <h2 class="font-bold text-primary">Riwayat pinjaman</h2>
                        <p class="text-xs text-on-surface-variant">Klik nomor pinjaman untuk membuka detail.</p>
                    </div>
                    <LoanHistoryTable :loans="loans" />
                </AppCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
