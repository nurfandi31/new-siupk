<script setup>
import { useI18n } from '../../../i18n';
const { t } = useI18n();
import { Head, Link, router } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AuthenticatedLayout from '../../../Layouts/TenantAdminLayout.vue';
import { useConfirm } from '../../../composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    roles: { type: Array, required: true },
});

async function confirmDelete(role) {
    if (role.is_system || role.is_locked) return;
    const ok = await confirm({
        title: t('Hapus Role Kustom'),
        message: `Role "${role.name}" akan dihapus permanen. Pengguna dengan role ini perlu ditetapkan ulang.`,
        confirmLabel: t('Ya, Hapus'),
        variant: 'danger',
    });
    if (!ok) return;
    router.delete(`/access/roles/${role.row_id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('Manajemen Role & Hak Akses')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">{{ t('Manajemen Role & Hak Akses') }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ t('Atur paket peran (roles) dan perizinan modul (permissions) untuk staf unit tenant.') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <Link href="/access/users">
                        <AppButton variant="secondary" icon="group">{{ t('Daftar Pengguna') }}</AppButton>
                    </Link>
                    <Link href="/access/roles/create">
                        <AppButton icon="add_moderator">{{ t('Tambah Role Kustom') }}</AppButton>
                    </Link>
                </div>
            </header>

            <AppCard :padded="false">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wider text-on-surface-variant">
                            <tr>
                                <th class="px-6 py-3.5">{{ t('Nama Role') }}</th>
                                <th class="px-6 py-3.5">{{ t('Kode') }}</th>
                                <th class="px-6 py-3.5">{{ t('Tipe') }}</th>
                                <th class="px-6 py-3.5 text-center">{{ t('Pengguna') }}</th>
                                <th class="px-6 py-3.5 text-center">{{ t('Hak Akses Aktif') }}</th>
                                <th class="px-6 py-3.5 text-right">{{ t('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="role in roles" :key="role.row_id" class="transition-colors hover:bg-surface-container-lowest">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <AppIcon v-if="role.is_locked" name="lock" class="text-sm text-primary" />
                                        <span class="font-bold text-primary">{{ role.name }}</span>
                                    </div>
                                    <p v-if="role.description" class="mt-0.5 text-xs text-on-surface-variant">{{ role.description }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <code class="rounded bg-surface-container px-2 py-0.5 text-xs font-mono">{{ role.code }}</code>
                                </td>
                                <td class="px-6 py-4">
                                    <AppBadge v-if="role.is_locked" tone="primary">{{ t('Terkunci (Admin)') }}</AppBadge>
                                    <AppBadge v-else-if="role.is_system" tone="neutral">{{ t('Bawaan Sistem') }}</AppBadge>
                                    <AppBadge v-else tone="info">{{ t('Kustom') }}</AppBadge>
                                </td>
                                <td class="px-6 py-4 text-center font-medium">
                                    <span class="inline-flex size-7 items-center justify-center rounded-full bg-surface-container text-xs font-semibold">
                                        {{ role.user_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span v-if="role.is_locked" class="font-semibold text-primary text-xs">{{ t('Semua Akses (*)') }}</span>
                                    <span v-else class="text-xs font-medium text-on-surface-variant">{{ role.permissions_count }} izin</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="`/access/roles/${role.row_id}/edit`">
                                            <AppButton variant="ghost" size="compact" icon="edit" :tooltip="t('Lihat / Edit Hak Akses')" />
                                        </Link>
                                        <AppButton
                                            v-if="!role.is_system && !role.is_locked"
                                            variant="ghost"
                                            size="compact"
                                            icon="delete"
                                            tone="error"
                                            :tooltip="t('Hapus Role')"
                                            :disabled="role.user_count > 0"
                                            @click="confirmDelete(role)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
