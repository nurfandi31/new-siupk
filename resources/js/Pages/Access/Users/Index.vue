<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppModal from '../../../Components/AppModal.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    users: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'name' },
    direction: { type: String, default: 'asc' },
});

const columns = [
    { key: 'name', label: 'Nama', sortable: true },
    { key: 'username', label: 'Username' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Role / Peran' },
    { key: 'tenure', label: 'Masa Jabatan' },
    { key: 'status', label: 'Status' },
    { key: 'last_login_at', label: 'Login Terakhir' },
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

function confirmDelete(user) {
    if (confirm(`Apakah Anda yakin ingin menghapus pengguna "${user.name}"? Tindakan ini tidak dapat dibatalkan.`)) {
        router.delete(`/access/users/${user.row_id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Manajemen Pengguna" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">Manajemen Pengguna</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">Kelola akun staf, operator desa, dan penugasan peran (role) pada sistem.</p>
                </div>
                <div class="flex items-center gap-3">
                    <Link href="/access/roles">
                        <AppButton variant="secondary" icon="shield_person">Kelola Role</AppButton>
                    </Link>
                    <Link href="/access/users/create">
                        <AppButton icon="person_add">Tambah Pengguna</AppButton>
                    </Link>
                </div>
            </header>

            <AppCard :padded="false">
                <div class="p-6">
                    <SmartDataTable
                        :rows="users.data"
                        :columns="columns"
                        :pagination="users"
                        url="/access/users"
                        :search="search"
                        :per-page="perPage"
                        :sort="sort"
                        :direction="direction"
                        search-placeholder="Cari nama, username, atau email..."
                        empty-title="Belum ada pengguna"
                        empty-description="Tambahkan pengguna staf atau operator untuk unit ini."
                    >
                        <template #cell-name="{ row }">
                            <div class="font-bold text-primary">
                                {{ row.name }}
                                <span v-if="row.is_self" class="ml-1.5 text-xs text-tertiary font-normal">(Anda)</span>
                            </div>
                        </template>

                        <template #cell-role="{ row }">
                            <span v-if="row.role_name" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="row.role_code === 'admin' ? 'bg-primary/10 text-primary' : 'bg-surface-container-high text-on-surface'">
                                <AppIcon v-if="row.role_code === 'admin'" name="lock" class="text-xs" />
                                {{ row.role_name }}
                            </span>
                            <span v-else class="text-xs italic text-on-surface-variant">Akses Penuh (Legacy)</span>
                            <span v-if="row.role_code === 'anggota' && row.member_name" class="ml-1 text-xs text-on-surface-variant">· {{ row.member_name }}</span>
                        </template>

                        <template #cell-tenure="{ row }">
                            <span v-if="row.appointed_at || row.term_end_at" class="text-xs text-on-surface">
                                {{ row.appointed_at || '—' }} s.d. {{ row.term_end_at || 'Sekarang' }}
                            </span>
                            <span v-else class="text-xs text-on-surface-variant">—</span>
                        </template>

                        <template #cell-status="{ row }">
                            <AppBadge :tone="row.status === 'active' ? 'success' : (row.status === 'suspended' ? 'warning' : 'neutral')">
                                {{ row.status === 'active' ? 'Aktif' : (row.status === 'suspended' ? 'Ditangguhkan' : 'Nonaktif') }}
                            </AppBadge>
                        </template>

                        <template #cell-last_login_at="{ row }">
                            <span class="text-xs text-on-surface-variant">{{ row.last_login_at || 'Belum pernah login' }}</span>
                        </template>

                        <template #actions="{ row }">
                            <div class="flex items-center justify-end gap-1">
                                <AppButton variant="ghost" size="compact" icon="key" tooltip="Reset Password" @click="openResetModal(row)" />
                                <Link :href="`/access/users/${row.row_id}/edit`">
                                    <AppButton variant="ghost" size="compact" icon="edit" tooltip="Edit Pengguna" />
                                </Link>
                                <AppButton
                                    v-if="!row.is_self"
                                    variant="ghost"
                                    size="compact"
                                    icon="delete"
                                    tone="error"
                                    tooltip="Hapus Pengguna"
                                    @click="confirmDelete(row)"
                                />
                            </div>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>

            <!-- Reset Password Modal -->
            <AppModal v-model="resetModalOpen" :title="`Reset Password: ${resetTargetUser?.name ?? ''}`" size="md">
                <form class="space-y-4" @submit.prevent="submitResetPassword">
                    <p class="text-sm text-on-surface-variant">Masukkan password baru untuk pengguna <strong>{{ resetTargetUser?.username }}</strong>.</p>
                    <AppInput v-model="resetForm.password" label="Password Baru" type="password" required :error="resetForm.errors.password" />
                    <AppInput v-model="resetForm.password_confirmation" label="Konfirmasi Password" type="password" required />
                </form>
                <template #footer>
                    <AppButton variant="secondary" @click="resetModalOpen = false">Batal</AppButton>
                    <AppButton :loading="resetForm.processing" icon="lock_reset" @click="submitResetPassword">Simpan Password</AppButton>
                </template>
            </AppModal>
        </div>
    </AuthenticatedLayout>
</template>
