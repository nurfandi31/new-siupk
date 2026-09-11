<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import AppIconButton from '../Components/AppIconButton.vue';
import AppConfirmDialog from '../Components/AppConfirmDialog.vue';
import AppToast from '../Components/AppToast.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const currentPath = computed(() => page.url.split('?')[0]);
const mobileMenuOpen = ref(false);
const logoutForm = useForm({});
const avatarError = ref(false);

const navigation = [
    { label: 'Dashboard', icon: 'dashboard', href: '/province/dashboard', exact: true },
    { label: 'Paket 5 Laporan (PDF)', icon: 'picture_as_pdf', href: '/province/reports/pack' },
    { label: 'Neraca', icon: 'account_balance', href: '/province/reports/balance-sheet' },
    { label: 'Laba Rugi', icon: 'trending_up', href: '/province/reports/income-statement' },
    { label: 'Arus Kas', icon: 'payments', href: '/province/reports/cash-flow' },
    { label: 'Perubahan Ekuitas', icon: 'balance', href: '/province/reports/equity-changes' },
    { label: 'CALK', icon: 'description', href: '/province/reports/calk' },
];

function isActive(item) {
    if (!item.href) return false;
    return item.exact ? currentPath.value === item.href : currentPath.value.startsWith(item.href);
}

function logout() {
    logoutForm.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-surface">
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <button
                v-if="mobileMenuOpen"
                type="button"
                class="fixed inset-0 z-40 bg-primary/45 backdrop-blur-xs lg:hidden"
                aria-label="Tutup navigasi"
                @click="mobileMenuOpen = false"
            />
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-primary py-6 shadow-xl transition-transform duration-300 ease-in-out lg:translate-x-0"
            :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="mb-8 px-6">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-on-primary-container">Portal Provinsi</p>
                <p class="mt-1 text-lg font-bold text-on-primary">{{ user?.province_name || 'Provinsi' }}</p>
                <p class="text-xs text-primary-fixed-dim">Konsolidasi Multi-Kabupaten</p>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3">
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-4 py-2.5 transition-colors"
                    :class="isActive(item) ? 'bg-primary-container text-on-primary font-semibold' : 'text-primary-fixed-dim hover:bg-primary-container hover:text-on-primary'"
                    @click="mobileMenuOpen = false"
                >
                    <AppIcon :name="item.icon" :filled="isActive(item)" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="mt-4 border-t border-primary-container px-4 pt-4">
                <div class="flex items-center gap-3 rounded-xl bg-primary-container/50 p-3">
                    <div class="relative grid size-10 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-fixed text-sm font-bold text-primary"><img v-if="user?.photo_url && !avatarError" :src="user.photo_url" :alt="user?.name || 'Provinsi'" class="size-full object-cover" @error="avatarError = true" /><span v-else>{{ user?.name?.charAt(0).toUpperCase() || 'P' }}</span></div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold text-on-primary">{{ user?.name || 'Supervisor' }}</p>
                        <p class="truncate text-xs text-primary-fixed-dim">Level Provinsi</p>
                    </div>
                    <AppIconButton name="logout" tone="neutral" size="sm" rounded="lg" aria-label="Keluar" class="text-primary-fixed-dim hover:bg-on-primary/10 hover:text-on-primary" @click="logout" />
                </div>
            </div>
        </aside>

        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-outline-variant bg-surface px-4 lg:ml-64 lg:px-6">
            <div class="flex items-center gap-3">
                <AppIconButton name="menu" tone="primary" size="sm" rounded="lg" aria-label="Buka navigasi" class="lg:hidden" @click="mobileMenuOpen = true" />
                <div>
                    <p class="text-sm font-bold text-primary">Monitoring & Konsolidasi Laporan Provinsi</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Link
                    href="/changelog"
                    class="grid size-10 shrink-0 place-items-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                    :class="currentPath === '/changelog' && 'bg-surface-container text-primary'"
                    title="Catatan Rilis & Changelog"
                    aria-label="Catatan Rilis & Changelog"
                >
                    <AppIcon name="history_edu" class="text-2xl leading-none" />
                </Link>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-fixed px-3 py-1 text-xs font-semibold text-primary">
                    <span class="size-1.5 rounded-full bg-primary"></span>
                    {{ user?.province_name || 'Provinsi' }}
                </span>
            </div>
        </header>

        <main class="p-4 sm:p-6 lg:ml-64 lg:p-8">
            <Transition name="page" mode="out-in" appear>
                <div :key="currentPath" class="min-w-0 flex-1">
                    <slot />
                </div>
            </Transition>
        </main>

        <AppConfirmDialog />
        <AppToast />
    </div>
</template>