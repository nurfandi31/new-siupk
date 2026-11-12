<script setup>
import { useI18n } from '../../../i18n';
const { t } = useI18n();
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppModal from '../../../Components/AppModal.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AuthenticatedLayout from '../../../Layouts/TenantAdminLayout.vue';
import { useConfirm } from '../../../composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    users: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'name' },
    direction: { type: String, default: 'asc' },
});

const columns = [
    { key: 'name', label: t('Nama'), sortable: true },
    { key: 'username', label: t('Username') },
    { key: 'email', label: t('Email') },
    { key: 'role', label: t('Role / Peran') },
    { key: 'tenure', label: t('Masa Jabatan') },
    { key: 'status', label: t('Status') },
    { key: 'last_login_at', label: t('Login Terakhir') },
];

// Password Reset Modal State
const resetModalOpen = ref(false);
const resetTargetUser = ref(null);
const resetForm = useForm({
    password: '',
    password_confirmation: '',
});

function openResetModal(user) {
    resetTargetUser.value = user;
    resetForm.reset();
    resetForm.clearErrors();
    resetModalOpen.value = true;
}

function submitResetPassword() {
    if (!resetTargetUser.value) return;
    resetForm.post(`/access/users/${resetTargetUser.value.row_id}/reset-password`, {
        preserveScroll: true,
        onSuccess: () => {
            resetModalOpen.value = false;
            resetForm.reset();
        },
    });
}

async function confirmDelete(user) {
    const ok = await confirm({
        title: t('Hapus Pengguna'),
        message: `Akun "${user.name}" akan dihapus permanen beserta aksesnya. Tindakan ini tidak dapat dibatalkan.`,
        confirmLabel: t('Ya, Hapus'),
        variant: 'danger',
    });
    if (!ok) return;
    router.delete(`/access/users/${user.row_id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('Manajemen Pengguna')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-4 sm:space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">{{ t('Manajemen Pengguna') }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ t('Kelola akun staf, operator desa, dan penugasan peran (role) pada sistem.') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <Link href="/access/roles">
                        <AppButton variant="secondary" icon="shield_person">{{ t('Kelola Role') }}</AppButton>
                    </Link>
                    <Link href="/access/users/create">
                        <AppButton icon="person_add">{{ t('Tambah Pengguna') }}</AppButton>
                    </Link>
                </div>
            </header>

            <AppCard :padded="false">
                <SmartDataTable
                        :rows="users.data"
                        :columns="columns"
                        :pagination="users"
                        url="/access/users"
                        :search="search"
                        :per-page="perPage"
                        :sort="sort"
                        :direction="direction"
                        :search-placeholder="t('Cari pengguna...')"
                        empty-title="Belum ada pengguna"
                        empty-description="Tambahkan pengguna staf atau operator untuk unit ini."
                    >
                        <template #cell-name="{ row }">
                            <div class="font-bold text-primary">
                                {{ row.name }}
                                <span v-if="row.is_self" class="ml-1.5 text-xs text-tertiary font-normal">{{ t('(Anda)') }}</span>
                            </div>
                        </template>

                        <template #cell-role="{ row }">
                            <span v-if="row.role_name" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="row.role_code === 'admin' ? 'bg-primary/10 text-primary' : 'bg-surface-container-high text-on-surface'">
                                <AppIcon v-if="row.role_code === 'admin'" name="lock" class="text-xs" />
                                {{ row.role_name }}
                            </span>
                            <span v-else class="text-xs italic text-on-surface-variant">{{ t('Akses Penuh (Legacy)') }}</span>
                            <span v-if="row.role_code === 'anggota' && row.member_name" class="ml-1 text-xs text-on-surface-variant">· {{ row.member_name }}</span>
                        </template>

                        <template #cell-tenure="{ row }">
                            <span v-if="row.appointed_at || row.term_end_at" class="text-xs text-on-surface">
                                {{ row.appointed_at || '—' }} s.d. {{ row.term_end_at || t('Sekarang') }}
                            </span>
                            <span v-else class="text-xs text-on-surface-variant">—</span>
                        </template>

                        <template #cell-status="{ row }">
                            <AppBadge :tone="row.status === 'active' ? 'success' : (row.status === 'suspended' ? 'warning' : 'neutral')">
                                {{ row.status === 'active' ? 'Aktif' : (row.status === 'suspended' ? 'Ditangguhkan' : 'Nonaktif') }}
                            </AppBadge>
                        </template>

                        <template #cell-last_login_at="{ row }">
                            <span class="text-xs text-on-surface-variant">{{ row.last_login_at || t('Belum pernah login') }}</span>
                        </template>

                        <template #actions="{ row }">
                            <div class="flex items-center justify-end gap-1">
                                <AppButton variant="ghost" size="compact" icon="key" :tooltip="t('Reset Password')" @click="openResetModal(row)" />
                                <Link :href="`/access/users/${row.row_id}/edit`">
                                    <AppButton variant="ghost" size="compact" icon="edit" :tooltip="t('Edit Pengguna')" />
                                </Link>
                                <AppButton
                                    v-if="!row.is_self"
                                    variant="ghost"
                                    size="compact"
                                    icon="delete"
                                    tone="error"
                                    :tooltip="t('Hapus Pengguna')"
                                    @click="confirmDelete(row)"
                                />
                            </div>
                        </template>
                    </SmartDataTable>
            </AppCard>

            <!-- Reset Password Modal -->
            <AppModal v-model="resetModalOpen" :title="`Reset Password: ${resetTargetUser?.name ?? ''}`" size="md">
                <form class="space-y-4" @submit.prevent="submitResetPassword">
                    <p class="text-sm text-on-surface-variant">{{ t('Masukkan password baru untuk pengguna') }} <strong>{{ resetTargetUser?.username }}</strong>.</p>
                    <AppInput v-model="resetForm.password" :label="t('Password Baru')" type="password" required :error="resetForm.errors.password" />
                    <AppInput v-model="resetForm.password_confirmation" :label="t('Konfirmasi Password')" type="password" required />
                </form>
                <template #footer>
                    <AppButton variant="secondary" @click="resetModalOpen = false">{{ t('Batal') }}</AppButton>
                    <AppButton :loading="resetForm.processing" icon="lock_reset" @click="submitResetPassword">{{ t('Simpan Password') }}</AppButton>
                </template>
            </AppModal>
        </div>
    </AuthenticatedLayout>
</template>
