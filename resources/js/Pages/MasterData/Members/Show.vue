<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import LoanHistoryTable from '../../../Components/LoanHistoryTable.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
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

const canManage = computed(() => can('members.manage'));
const initials = computed(() => {
    const source = (props.member.name || '').trim();
    if (!source) return '?';
    const parts = source.split(/\s+/).filter(Boolean);
    return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase() || '?';
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

const statusBadgeTone = computed(() => (props.member.status === 'active' ? 'success-soft' : 'neutral'));

function infoField(label, value, { mono = false, multiline = false } = {}) {
    return { label, value, mono, multiline };
}

const profileSections = computed(() => {
    const fields = [];
    fields.push(infoField('Jenis kelamin', genderLabels[props.member.gender] || props.member.gender || '—'));
    fields.push(infoField('Tempat / Tgl Lahir', `${props.member.birth_place || '—'}${
        props.member.birth_date ? ` · ${formatDate(props.member.birth_date)}` : ''
    }`));
    fields.push(infoField('No. HP', props.member.phone || '—', { mono: true }));
    fields.push(infoField('No. KK', props.member.family_card_number || '—', { mono: true }));
    fields.push(infoField('Desa', props.member.village?.name || '—'));
    fields.push(infoField('Terdaftar', formatDate(props.member.registered_at)));
    return fields;
});

const addressField = computed(() => infoField('Alamat', props.member.address || '—', { multiline: true }));
</script>

<template>
    <Head :title="`Anggota · ${member.name || member.member_number}`" />
    <AuthenticatedLayout>
        <div class="space-y-2 sm:space-y-3">
            <!-- Hero / Identity Header -->
            <AppCard padded class="relative overflow-hidden">
                <!-- Decorative background -->
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary-container/40 via-transparent to-secondary-container/30" aria-hidden="true" />
                <div class="pointer-events-none absolute -right-12 -top-12 size-48 rounded-full bg-primary-container/30 blur-3xl" aria-hidden="true" />

                <div class="relative flex flex-col gap-5 md:flex-row md:items-stretch md:gap-6">
                    <!-- Avatar block -->
                    <div class="flex items-center gap-4 md:flex-col md:items-center md:justify-center md:gap-3 md:border-r md:border-outline-variant/60 md:pr-6">
                        <div class="grid size-20 shrink-0 place-items-center rounded-2xl bg-primary text-3xl font-bold text-on-primary shadow-md md:size-24 md:text-4xl">
                            {{ initials }}
                        </div>
                        <dl class="text-center">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">ID</dt>
                            <dd class="mt-0.5 font-mono text-sm font-bold text-primary">#{{ member.id }}</dd>
                        </dl>
                    </div>

                    <!-- Identity block (full) -->
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-bold leading-tight text-primary md:text-3xl">
                                {{ member.name || '—' }}
                            </h1>
                            <AppBadge :tone="statusBadgeTone">
                                {{ statusLabels[member.status] || member.status }}
                            </AppBadge>
                        </div>
                        <p class="mt-1.5 text-sm text-on-surface-variant">
                            <span class="font-mono font-semibold">{{ member.member_number || '—' }}</span>
                            <span v-if="member.nik" class="ml-1">
                                · NIK <span class="font-mono font-semibold">{{ member.nik }}</span>
                            </span>
                        </p>

                        <!-- Quick facts grid (full, no duplicate mobile/desktop) -->
                        <dl class="mt-5 grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-4">
                            <div class="rounded-lg bg-surface-container-low/60 px-3 py-2.5">
                                <dt class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                    <AppIcon name="location_on" class="text-sm" />
                                    Desa
                                </dt>
                                <dd class="mt-1 truncate font-semibold text-primary">{{ member.village?.name || '—' }}</dd>
                            </div>
                            <div class="rounded-lg bg-surface-container-low/60 px-3 py-2.5">
                                <dt class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                    <AppIcon name="event" class="text-sm" />
                                    Terdaftar
                                </dt>
                                <dd class="mt-1 truncate font-semibold text-primary">{{ formatDate(member.registered_at) }}</dd>
                            </div>
                            <div class="rounded-lg bg-surface-container-low/60 px-3 py-2.5">
                                <dt class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                    <AppIcon name="person" class="text-sm" />
                                    Jenis Kelamin
                                </dt>
                                <dd class="mt-1 truncate font-semibold text-primary">{{ genderLabels[member.gender] || member.gender || '—' }}</dd>
                            </div>
                            <div class="rounded-lg bg-surface-container-low/60 px-3 py-2.5">
                                <dt class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                    <AppIcon name="call" class="text-sm" />
                                    No. HP
                                </dt>
                                <dd class="mt-1 truncate font-mono font-semibold text-primary">{{ member.phone || '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </AppCard>

            <!-- KPI strip -->
            <div class="grid gap-3 grid-cols-1 sm:grid-cols-3 md:gap-4">
                <AppCard class="flex items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-lg bg-primary-container text-primary md:size-12">
                        <AppIcon name="receipt_long" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Riwayat pinjaman</p>
                        <p class="mt-0.5 truncate text-lg font-bold text-primary md:text-xl">
                            {{ summary.loan_count }}
                            <span class="text-xs font-medium text-on-surface-variant">pinjaman</span>
                        </p>
                        <p class="text-[11px] text-on-surface-variant">{{ summary.active_loan_count }} aktif</p>
                    </div>
                </AppCard>
                <AppCard class="flex items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-lg bg-tertiary-fixed text-tertiary md:size-12">
                        <AppIcon name="payments" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Sisa pokok</p>
                        <p class="mt-0.5 truncate text-lg font-bold text-primary tabular-nums md:text-xl">
                            Rp {{ formatMoney(summary.principal_remaining) }}
                        </p>
                        <p class="text-[11px] text-on-surface-variant">belum dilunasi</p>
                    </div>
                </AppCard>
                <AppCard class="flex items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-lg bg-secondary-container text-secondary md:size-12">
                        <AppIcon name="groups" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Kelompok</p>
                        <p class="mt-0.5 truncate text-lg font-bold text-primary md:text-xl">
                            {{ (member.groups || []).length }}
                            <span class="text-xs font-medium text-on-surface-variant">kelompok</span>
                        </p>
                        <p class="text-[11px] text-on-surface-variant">keanggotaan aktif</p>
                    </div>
                </AppCard>
            </div>

            <!-- Two-column main content -->
            <div class="grid gap-4 lg:grid-cols-5 lg:gap-6">
                <!-- Profile card -->
                <AppCard class="lg:col-span-3">
                    <header class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-bold text-primary">Profil</h2>
                            <p class="text-xs text-on-surface-variant">Data identitas dan kontak anggota.</p>
                        </div>
                        <AppIcon name="badge" tone="primary" />
                    </header>

                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 md:gap-x-8 md:gap-y-4">
                        <div v-for="field in profileSections" :key="field.label" class="flex flex-col gap-0.5 border-b border-outline-variant/40 pb-2 last:border-0 sm:border-0 sm:pb-0">
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant">{{ field.label }}</dt>
                            <dd :class="['font-medium text-primary', field.mono && 'font-mono']">{{ field.value }}</dd>
                        </div>
                        <div class="sm:col-span-2 flex flex-col gap-0.5 border-t border-outline-variant pt-3">
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant">{{ addressField.label }}</dt>
                            <dd class="whitespace-pre-line font-medium text-primary">{{ addressField.value }}</dd>
                        </div>
                    </dl>

                    <!-- Sub-section: Business -->
                    <section v-if="member.business?.name" class="mt-6 border-t border-outline-variant pt-4">
                        <header class="mb-2 flex items-center gap-2">
                            <AppIcon name="storefront" tone="success" container-size="7" />
                            <h3 class="text-sm font-bold text-primary">Usaha</h3>
                        </header>
                        <p class="font-semibold text-primary">{{ member.business.name }}</p>
                        <p v-if="member.business.description" class="mt-1 whitespace-pre-line text-sm text-on-surface-variant">
                            {{ member.business.description }}
                        </p>
                    </section>

                    <!-- Sub-section: Guarantor -->
                    <section v-if="member.guarantor?.name" class="mt-6 border-t border-outline-variant pt-4">
                        <header class="mb-2 flex items-center gap-2">
                            <AppIcon name="verified_user" tone="info" container-size="7" />
                            <h3 class="text-sm font-bold text-primary">Penjamin</h3>
                        </header>
                        <p class="font-semibold text-primary">{{ member.guarantor.name }}</p>
                        <p class="text-sm text-on-surface-variant">
                            {{ member.guarantor.relationship || '—' }}
                            <span v-if="member.guarantor.nik" class="ml-1">
                                · NIK <span class="font-mono">{{ member.guarantor.nik }}</span>
                            </span>
                        </p>
                    </section>

                    <!-- Sub-section: Group membership -->
                    <section v-if="(member.groups || []).length" class="mt-6 border-t border-outline-variant pt-4">
                        <header class="mb-2 flex items-center gap-2">
                            <AppIcon name="groups" tone="primary" container-size="7" />
                            <h3 class="text-sm font-bold text-primary">Keanggotaan kelompok</h3>
                        </header>
                        <ul class="space-y-1.5">
                            <li v-for="g in member.groups" :key="g.row_id" class="flex items-baseline gap-2 text-sm">
                                <AppIcon name="chevron_right" class="text-base text-outline" />
                                <Link :href="g.href" class="font-semibold text-primary hover:underline">
                                    {{ g.name }}
                                </Link>
                                <span class="font-mono text-xs text-on-surface-variant">{{ g.code }}</span>
                            </li>
                        </ul>
                    </section>
                </AppCard>

                <!-- KTP / Document card -->
                <AppCard class="lg:col-span-2">
                    <header class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-bold text-primary">Foto KTP</h2>
                            <p class="text-xs text-on-surface-variant">Digunakan pada dokumen pinjaman.</p>
                        </div>
                        <AppIcon name="contact_page" tone="primary" />
                    </header>

                    <div class="grid place-items-center overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest">
                        <img
                            v-if="identityPhotoPreview"
                            :src="identityPhotoPreview"
                            alt="Foto KTP anggota"
                            class="max-h-64 w-full object-contain sm:max-h-72 md:max-h-80"
                        />
                        <div v-else class="flex flex-col items-center gap-2 px-6 py-10 text-on-surface-variant">
                            <AppIcon name="image_not_supported" class="text-4xl" />
                            <p class="text-xs font-medium">Belum ada foto KTP</p>
                        </div>
                    </div>

                    <p class="mt-3 text-sm">
                        <span class="text-on-surface-variant">NIK:</span>
                        <span class="ml-1 font-mono font-semibold text-primary">{{ member.nik || 'Belum tersedia' }}</span>
                    </p>
                    <p v-if="identityPhotoForm.errors.identity_photo" class="mt-1 text-xs text-error">
                        {{ identityPhotoForm.errors.identity_photo }}
                    </p>

                    <!-- Upload control -->
                    <div v-if="canManage" class="mt-4 space-y-2">
                        <label class="block cursor-pointer rounded-lg border-2 border-dashed border-outline-variant bg-surface-container-lowest p-3 text-center transition-colors hover:border-primary hover:bg-surface-container-low">
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                @change="onIdentityPhotoChange"
                            />
                            <div class="flex items-center justify-center gap-2">
                                <AppIcon name="upload" class="text-lg text-primary" />
                                <span class="text-sm font-semibold text-primary">
                                    {{ identityPhotoForm.processing ? 'Mengunggah...' : (identityPhotoPreview ? 'Ganti foto KTP' : 'Unggah foto KTP') }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-on-surface-variant">JPG, PNG, atau WebP · Maks 4 MB</p>
                        </label>

                        <div v-if="identityPhotoPreview" class="flex justify-end">
                            <AppButton
                                type="button"
                                variant="ghost"
                                size="compact"
                                icon="delete"
                                :disabled="identityPhotoForm.processing"
                                @click="confirmDeleteIdentityPhoto"
                            >
                                Hapus foto
                            </AppButton>
                        </div>
                    </div>
                </AppCard>

                <!-- Loan history (full width) -->
                <AppCard class="lg:col-span-5 overflow-hidden p-0">
                    <div class="flex items-center justify-between gap-3 border-b border-outline-variant px-4 py-3 sm:px-5 sm:py-4">
                        <div>
                            <h2 class="font-bold text-primary">Riwayat pinjaman</h2>
                            <p class="text-xs text-on-surface-variant">Klik nomor pinjaman untuk membuka detail.</p>
                        </div>
                        <AppIcon name="history" tone="primary" />
                    </div>
                    <LoanHistoryTable :loans="loans" />
                </AppCard>
            </div>

            <!-- Action bar (paling bawah: Kembali · Edit · Hapus) -->
            <AppCard class="!rounded-md !p-1.5 shadow-none sm:!p-2 md:!p-2.5">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-2 md:gap-3">
                    <Link href="/master-data/members" class="contents">
                        <AppButton variant="secondary" icon="arrow_back" size="compact">
                            Kembali ke daftar
                        </AppButton>
                    </Link>
                    <div class="flex flex-wrap items-center gap-1 sm:justify-end">
                        <Link v-if="canManage" :href="`/master-data/members/${member.row_id}/edit`" class="contents">
                            <AppButton variant="tertiary" icon="edit" size="compact">
                                Edit anggota
                            </AppButton>
                        </Link>
                        <AppButton
                            v-if="canManage"
                            variant="danger"
                            icon="delete_outline"
                            size="compact"
                            @click="confirmDelete"
                        >
                            Hapus anggota
                        </AppButton>
                    </div>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
