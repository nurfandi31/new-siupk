<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import AppIconButton from '../Components/AppIconButton.vue';
import AppConfirmDialog from '../Components/AppConfirmDialog.vue';
import AppToast from '../Components/AppToast.vue';
import AppModal from '../Components/AppModal.vue';
import AppButton from '../Components/AppButton.vue';
import AppLanguageSwitcher from '../Components/AppLanguageSwitcher.vue';
import ThemeMenu from '../Components/ThemeMenu.vue';
import NotificationDropdown from '../Components/NotificationDropdown.vue';
import { useI18n } from '../i18n';

const { t, locale } = useI18n();
const page = usePage();
const user = computed(() => page.props.auth?.user);
const appName = computed(() => page.props.appName || 'siupk Next');
const currentPath = computed(() => page.url.split('?')[0]);
const mobileMenuOpen = ref(false);
const sidebarCollapsed = ref(false);
const logoutForm = useForm({});
const logoutOpen = ref(false);
const themeOpen = ref(false);
const avatarError = ref(false);

const SIDEBAR_KEY = 'siupk-admin-sidebar';
onMounted(() => {
    try {
        const v = localStorage.getItem(SIDEBAR_KEY);
        if (v !== null) sidebarCollapsed.value = v === '1';
    } catch (e) { /* ignore */ }
});
watch(sidebarCollapsed, (v) => {
    try { localStorage.setItem(SIDEBAR_KEY, v ? '1' : '0'); } catch (e) { /* ignore */ }
});

function navLabel(label) {
    if (label === 'Beranda') return locale.value === 'en' ? 'Dashboard' : 'Beranda';
    return t(label);
}

const navigation = [
    { label: 'Beranda', icon: 'dashboard', href: '/admin', exact: true },
    { label: 'Tenant', icon: 'domain', href: '/admin/tenants', exact: true },
    { label: 'Tambah Tenant', icon: 'domain_add', href: '/admin/tenants/create', exact: true },
    { label: 'Plan', icon: 'workspace_premium', href: '/admin/plans' },
    { label: 'Invoice', icon: 'receipt_long', href: '/admin/invoices' },
    { label: 'Pendapatan', icon: 'monitoring', href: '/admin/revenue' },
    { label: 'Log Audit', icon: 'history', href: '/admin/audit-logs' },
    { label: 'Pengguna Platform', icon: 'group', href: '/admin/users' },
    { label: 'Shard & Cutover', icon: 'storage', href: '/admin/shards' },
    { label: 'WhatsApp', icon: 'chat', href: '/admin/whatsapp' },
    { label: 'Platform Settings', icon: 'tune', href: '/admin/settings' },
    { label: 'Payment Gateway', icon: 'payments', href: '/admin/payment-gateways' },
    { label: 'AI Assistant', icon: 'smart_toy', href: '/admin/ai-assistant' },
    { label: 'Migrasi Data', icon: 'transform', href: '/admin/migration' },
];

function isActive(item) {
    if (!item.href) return false;
    return item.exact ? currentPath.value === item.href : currentPath.value.startsWith(item.href);
}

function toggleSidebar() {
    if (window.matchMedia('(max-width: 1023px)').matches) {
        mobileMenuOpen.value = !mobileMenuOpen.value;
    } else {
        sidebarCollapsed.value = !sidebarCollapsed.value;
    }
}

function closeMobileMenu() {
    mobileMenuOpen.value = false;
}

const isDesktop = ref(false);
function updateIsDesktop() {
    isDesktop.value = typeof window !== 'undefined' && window.matchMedia('(min-width: 1024px)').matches;
}
onMounted(() => {
    updateIsDesktop();
    window.addEventListener('resize', updateIsDesktop);
});
onBeforeUnmount(() => {
    window.removeEventListener('resize', updateIsDesktop);
});

const sidebarToggleIcon = computed(() => {
    if (mobileMenuOpen.value) return 'close';
    if (isDesktop.value) return sidebarCollapsed.value ? 'chevron_right' : 'chevron_left';
    return 'menu';
});

const sidebarToggleLabel = computed(() => {
    if (mobileMenuOpen.value) return t('Tutup navigasi');
    if (isDesktop.value) return sidebarCollapsed.value ? t('Buka sidebar') : t('Tutup sidebar');
    return t('Buka navigasi');
});

function askLogout() {
    logoutOpen.value = true;
}

function logout() {
    logoutForm.post('/logout', {
        onFinish: () => {
            logoutOpen.value = false;
        },
    });
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
                class="fixed inset-0 z-40 bg-zinc-900/60 backdrop-blur-xs lg:hidden"
                :aria-label="t('Tutup navigasi')"
                @click="closeMobileMenu"
            />
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-zinc-900 text-zinc-100 shadow-xl transition-all duration-300 ease-in-out lg:translate-x-0"
            :class="[
                mobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
                sidebarCollapsed ? 'lg:w-16' : 'lg:w-64',
            ]"
        >
            <!-- Toggle button: floating at sidebar edge -->
            <button
                type="button"
                class="absolute top-5 -right-3 z-10 grid size-8 place-items-center rounded-full chip-shadow bg-surface text-on-surface ring-1 ring-outline-variant transition hover:bg-surface-container hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container lg:top-7"
                :aria-label="sidebarToggleLabel"
                :title="sidebarToggleLabel"
                @click="toggleSidebar"
            >
                <AppIcon :name="sidebarToggleIcon" class="text-xl leading-none" />
            </button>

            <div class="flex items-center gap-3 px-4 py-6 lg:px-6" :class="sidebarCollapsed && 'lg:justify-center lg:px-2'">
                <div class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-zinc-800 text-zinc-100 ring-1 ring-white/10">
                    <AppIcon name="account_balance" />
                </div>
                <div class="min-w-0 transition-opacity duration-200 lg:opacity-100" :class="sidebarCollapsed && 'lg:hidden'">
                    <p class="truncate font-bold leading-none text-white">{{ t('Platform Admin') }}</p>
                    <p class="mt-1 truncate text-[10px] font-semibold uppercase tracking-widest text-zinc-400">siupk Next</p>
                </div>
            </div>

            <nav class="scrollbar-hidden flex-1 space-y-5 overflow-y-auto px-2" :aria-label="t('Navigasi admin')">
                <section>
                    <h2 class="mb-1 px-4 text-[10px] font-bold uppercase tracking-[0.18em] text-zinc-500 transition-opacity duration-200 lg:opacity-100" :class="sidebarCollapsed && 'lg:hidden'">{{ t('Manajemen Platform') }}</h2>
                    <div class="space-y-1">
                        <Link
                            v-for="item in navigation"
                            :key="item.label"
                            :href="item.href"
                            class="group flex items-center gap-3 rounded-lg px-4 py-2.5 transition-all duration-200"
                            :class="[
                                isActive(item)
                                    ? 'bg-primary text-on-primary shadow-md shadow-primary/30 ring-1 ring-primary/40'
                                    : 'text-zinc-400 hover:bg-white/5 hover:text-white',
                                sidebarCollapsed && 'lg:justify-center lg:px-2',
                            ]"
                            :title="sidebarCollapsed ? navLabel(item.label) : undefined"
                            @click="closeMobileMenu"
                        >
                            <AppIcon :name="item.icon" :filled="isActive(item)" class="shrink-0 text-xl leading-none transition-colors" :class="isActive(item) ? 'text-on-primary' : 'text-zinc-400 group-hover:text-white'" />
                            <span class="truncate transition-opacity duration-200 lg:opacity-100" :class="sidebarCollapsed && 'lg:hidden'">{{ navLabel(item.label) }}</span>
                        </Link>
                    </div>
                </section>
            </nav>
        </aside>

        <header
            class="sticky top-0 z-30 flex h-16 items-center header-shadow bg-surface px-4 transition-all duration-300 lg:px-6"
            :class="sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64'"
        >
            <AppIconButton
                name="menu"
                tone="primary"
                size="sm"
                rounded="lg"
                :aria-label="t('Buka navigasi')"
                class="mr-3 lg:hidden"
                @click="toggleSidebar"
            />
            <p class="font-bold text-primary">{{ t('Panel Admin Platform') }}</p>
            <p class="ml-3 hidden items-center gap-1 text-xs text-on-surface-variant md:flex">
                <AppIcon name="admin_panel_settings" class="text-base text-primary" />
                {{ appName }}
            </p>

            <div class="ml-auto flex items-center gap-1 pl-3">
                <AppIconButton
                    name="palette"
                    tone="neutral"
                    rounded="full"
                    data-theme-trigger
                    class="shrink-0 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                    :class="themeOpen && 'bg-surface-container text-primary'"
                    :aria-label="t('Pilih tema tampilan')"
                    aria-haspopup="menu"
                    :aria-expanded="themeOpen"
                    @click="themeOpen = !themeOpen"
                />
                <Link
                    href="/changelog"
                    class="grid size-10 shrink-0 place-items-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                    :class="currentPath === '/changelog' && 'bg-surface-container text-primary'"
                    :title="t('Catatan Rilis & Changelog')"
                    :aria-label="t('Catatan Rilis & Changelog')"
                >
                    <AppIcon name="history_edu" class="text-2xl leading-none" />
                </Link>
                <NotificationDropdown />

                <!-- Profile pill -->
                <Link
                    href="/profile"
                    class="ml-1 flex shrink-0 items-center gap-2 rounded-full chip-shadow bg-surface-container-lowest py-1 pl-1 pr-3 ring-1 ring-outline-variant transition hover:bg-surface-container-low hover:ring-primary/40"
                    :aria-label="t('Buka Profil')"
                >
                    <span class="relative grid size-8 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-fixed text-xs font-bold text-primary"><img v-if="user?.photo_url && !avatarError" :src="user.photo_url" :alt="user?.name || 'Admin'" class="size-full object-cover" @error="avatarError = true" /><span v-else>{{ user?.name?.charAt(0).toUpperCase() || 'A' }}</span></span>
                    <span class="hidden min-w-0 flex-col leading-tight sm:flex">
                        <span class="truncate text-xs font-bold text-primary">{{ user?.name || 'Superadmin' }}</span>
                        <span class="truncate text-[10px] font-medium text-on-surface-variant">{{ t('Superadmin Platform') }}</span>
                    </span>
                </Link>

                <AppLanguageSwitcher />
                <AppIconButton name="logout" tone="danger" size="md" rounded="full" :aria-label="t('Keluar')" class="shrink-0" @click="askLogout" />
            </div>
        </header>

        <main
            class="p-4 sm:p-6 transition-all duration-300 lg:p-8"
            :class="sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64'"
        >
            <Transition name="page" mode="out-in" appear>
                <div :key="currentPath" class="min-w-0 flex-1">
                    <slot />
                </div>
            </Transition>
        </main>

        <AppConfirmDialog />
        <AppToast />
        <ThemeMenu v-model="themeOpen" />

        <AppModal v-model="logoutOpen" :title="t('Keluar dari Panel Admin?')" size="sm">
            <p class="text-sm text-on-surface-variant">
                {{ t('Sesi') }} <span class="font-semibold text-primary">{{ user?.name || 'Admin' }}</span> {{ t('akan diakhiri. Lanjutkan?') }}
            </p>
            <template #footer>
                <AppButton variant="secondary" :disabled="logoutForm.processing" @click="logoutOpen = false">{{ t('Batal') }}</AppButton>
                <AppButton variant="danger" icon="logout" :loading="logoutForm.processing" @click="logout">{{ t('Keluar') }}</AppButton>
            </template>
        </AppModal>
    </div>
</template>

<style scoped>
.page-enter-active,
.page-leave-active {
    transition: opacity 200ms ease, transform 200ms ease;
}
.page-enter-from {
    opacity: 0;
    transform: translateY(8px);
}
.page-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@media (prefers-reduced-motion: reduce) {
    .page-enter-active,
    .page-leave-active {
        transition: none;
    }
}
</style>
