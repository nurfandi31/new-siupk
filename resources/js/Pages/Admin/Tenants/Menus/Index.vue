<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppBadge from '../../../../Components/AppBadge.vue';
import AppButton from '../../../../Components/AppButton.vue';
import AppCard from '../../../../Components/AppCard.vue';
import AppIcon from '../../../../Components/AppIcon.vue';
import AppIconButton from '../../../../Components/AppIconButton.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { useI18n } from '../../../../i18n';

const { t } = useI18n();

defineProps({
    tenant: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    totalMenus: { type: Number, default: 0 },
    error: { type: String, default: null },
});
</script>

<template>
    <Head :title="`Hak Akses Menu · ${tenant.name}`" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-4 sm:space-y-6">
            <header class="space-y-3">
                <Link :href="`/admin/tenants/${tenant.row_id}`" class="inline-flex items-center gap-1 text-sm font-semibold text-primary transition hover:text-primary-container">
                    <AppIconButton name="arrow_back" size="sm" tone="primary" rounded="full" :aria-label="t('Kembali')" />
                    <span>{{ tenant.name }}</span>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-primary">{{ t('Hak Akses Menu') }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('Atur menu yang boleh dibuka tiap role tenant. Role yang belum dibatasi mengikuti izin modul seperti biasa.') }}
                    </p>
                </div>
            </header>

            <div v-if="error" class="flex items-start gap-2 rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error">
                <AppIcon name="warning" class="mt-0.5 text-lg" />
                <p>{{ error }}</p>
            </div>

            <AppCard :padded="false">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wider text-on-surface-variant">
                            <tr>
                                <th class="px-6 py-3.5">{{ t('Role') }}</th>
                                <th class="px-6 py-3.5">{{ t('Kode') }}</th>
                                <th class="px-6 py-3.5">{{ t('Tipe') }}</th>
                                <th class="px-6 py-3.5 text-center">{{ t('Pengguna') }}</th>
                                <th class="px-6 py-3.5">{{ t('Akses Menu') }}</th>
                                <th class="px-6 py-3.5 text-right">{{ t('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="role in roles" :key="role.row_id" class="transition-colors hover:bg-surface-container-lowest">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-primary">{{ role.name }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <code class="rounded bg-surface-container-low px-1.5 py-0.5 font-mono text-xs text-on-surface-variant">{{ role.code }}</code>
                                </td>
                                <td class="px-6 py-4">
                                    <AppBadge v-if="role.is_system" tone="neutral">{{ t('Bawaan Sistem') }}</AppBadge>
                                    <AppBadge v-else tone="info-soft">{{ t('Kustom') }}</AppBadge>
                                </td>
                                <td class="px-6 py-4 text-center font-semibold">{{ role.user_count }}</td>
                                <td class="px-6 py-4">
                                    <span v-if="role.is_locked" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary">
                                        <AppIcon name="lock" class="text-base" />
                                        {{ t('Akses penuh (terkunci)') }}
                                    </span>
                                    <span v-else-if="role.restricted" class="inline-flex items-center gap-1.5 text-xs font-semibold text-tertiary">
                                        <AppIcon name="filter_alt" class="text-base" />
                                        {{ role.menu_count }} / {{ totalMenus }} {{ t('menu') }}
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1.5 text-xs font-semibold text-secondary">
                                        <AppIcon name="check_circle" class="text-base" />
                                        {{ t('Semua menu (default)') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link v-if="!role.is_locked" :href="`/admin/tenants/${tenant.row_id}/menus/${role.row_id}`">
                                        <AppButton variant="secondary" size="compact" icon="tune">{{ t('Atur Menu') }}</AppButton>
                                    </Link>
                                    <span v-else class="text-xs text-on-surface-variant">{{ t('Tidak dapat diubah') }}</span>
                                </td>
                            </tr>
                            <tr v-if="!roles.length">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-on-surface-variant">
                                    {{ t('Belum ada role pada tenant ini.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <div class="flex items-start gap-2 rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-on-surface-variant">
                <AppIcon name="info" class="mt-0.5 text-lg text-primary" />
                <p>
                    {{ t('Hak akses menu hanya mempersempit tampilan dan akses halaman. Role tetap harus memiliki izin modul terkait (contoh: Laporan Keuangan butuh izin "Lihat Laporan Keuangan").') }}
                </p>
            </div>
        </div>
    </AdminLayout>
</template>
