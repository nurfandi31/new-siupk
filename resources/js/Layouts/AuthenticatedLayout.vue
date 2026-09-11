<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppButton from '../Components/AppButton.vue';
import AppIcon from '../Components/AppIcon.vue';
import AppIconButton from '../Components/AppIconButton.vue';
import AppModal from '../Components/AppModal.vue';
import AppConfirmDialog from '../Components/AppConfirmDialog.vue';
import AppToast from '../Components/AppToast.vue';
import AssistantWidget from '../Components/AssistantWidget.vue';
import ThemeMenu from '../Components/ThemeMenu.vue';
import NotificationDropdown from '../Components/NotificationDropdown.vue';
import KeyboardShortcutsModal from '../Components/KeyboardShortcutsModal.vue';
import { useKeyboardShortcuts } from '../composables/useKeyboardShortcuts';

const props = defineProps({ unitName: { type: String, default: null } });

const page = usePage();
const logoPath = computed(() => page.props.logoPath ?? null);
const user = computed(() => page.props.auth?.user);
const permissions = computed(() => page.props.auth?.permissions ?? []);
const navMap = computed(() => page.props.auth?.nav_map ?? {});
const assistantEnabled = computed(() => {
    if (!page.props.assistant?.enabled) return false;
    return can('assistant.use');
});
const currentPath = computed(() => page.url.split('?')[0]);
const sidebarNav = ref(null);
const sidebarScrollTop = ref(0);
const mobileMenuOpen = ref(false);
const expanded = ref({});
const logoutForm = useForm({});
const isTrainingMode = computed(() => page.props.tenant?.is_training_mode === true);
const impersonatedBy = computed(() => page.props.auth?.impersonated_by);
const impersonatorName = computed(() => page.props.auth?.impersonator_name);
const leaveForm = useForm({});
const avatarError = ref(false);
const { showShortcutsModal } = useKeyboardShortcuts();

watch(() => user.value?.photo_url, () => {
    avatarError.value = false;
});

function leaveImpersonation() {
    leaveForm.post('/auth/impersonate/leave');
}

function can(permission) {
    if (!permission) return true;
    const perms = permissions.value;
    if (!Array.isArray(perms) || perms.length === 0) return true; // legacy unrestricted / not loaded
    if (perms.includes('*')) return true;
    return perms.includes(permission);
}

function permissionForHref(href) {
    if (!href) return null;
    const map = navMap.value || {};
    const keys = Object.keys(map).sort((a, b) => b.length - a.length);
    for (const prefix of keys) {
        if (href === prefix || href.startsWith(prefix + '/') || href.startsWith(prefix + '?')) {
            return map[prefix];
        }
        // exact prefix match for bare paths like /budgeting
        if (href === prefix) return map[prefix];
    }
    // also try startsWith without trailing nuances
    for (const prefix of keys) {
        if (href.startsWith(prefix)) return map[prefix];
    }
    return null;
}

function filterNavItems(items) {
    if (!Array.isArray(items)) return [];
    return items
        .map((item) => {
            if (item.children) {
                const children = filterNavItems(item.children);
                if (!children.length) return null;
                return { ...item, children };
            }
            if (item.href) {
                const need = permissionForHref(item.href);
                if (need && !can(need)) return null;
            }
            return item;
        })
        .filter(Boolean);
}

const visibleSections = computed(() =>
    sections
        .map((section) => {
            const items = filterNavItems(section.items);
            if (!items.length) return null;
            return { ...section, items };
        })
        .filter(Boolean),
);

// Command-palette search
const searchOpen = ref(false);
const searchQ = ref('');
const searchLoading = ref(false);
const searchGroups = ref([]);
const searchInput = ref(null);
const searchHighlight = ref(0);
let searchTimer;
let searchAbort;
let previousOverflow = '';

const flatResults = computed(() =>
    searchGroups.value.flatMap((g) => g.items.map((item) => ({ ...item, group: g.label }))),
);

async function runSearch(q) {
    searchAbort?.abort();
    if (!q || q.trim().length < 2) {
        searchGroups.value = [];
        searchLoading.value = false;
        searchHighlight.value = 0;
        return;
    }
    const controller = new AbortController();
    searchAbort = controller;
    searchLoading.value = true;
    try {
        const res = await fetch(`/search?q=${encodeURIComponent(q.trim())}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        searchGroups.value = data.groups || [];
        searchHighlight.value = 0;
    } catch (e) {
        if (e?.name === 'AbortError') return;
        searchGroups.value = [];
    } finally {
        if (searchAbort === controller) searchLoading.value = false;
    }
}

function openSearch() {
    searchOpen.value = true;
}

function closeSearch() {
    searchOpen.value = false;
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => runSearch(searchQ.value), 280);
}

function clearSearchQuery() {
    searchQ.value = '';
    searchGroups.value = [];
    searchHighlight.value = 0;
    searchInput.value?.focus();
}

function pickResult(item) {
    closeSearch();
    if (item?.href) {
        // Link click handles navigation; keyboard path uses router via <a>
    }
}

function onPaletteKeydown(e) {
    if (e.key === 'Escape') {
        e.preventDefault();
        closeSearch();
        return;
    }
    const items = flatResults.value;
    if (!items.length) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        searchHighlight.value = (searchHighlight.value + 1) % items.length;
        return;
    }
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        searchHighlight.value = (searchHighlight.value - 1 + items.length) % items.length;
        return;
    }
    if (e.key === 'Enter') {
        e.preventDefault();
        const hit = items[searchHighlight.value];
        if (hit?.href) {
            closeSearch();
            window.location.assign(hit.href);
        }
    }
}

watch(searchOpen, async (open) => {
    if (open) {
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        await nextTick();
        searchInput.value?.focus();
        if (searchQ.value.trim().length >= 2) runSearch(searchQ.value);
        return;
    }
    document.body.style.overflow = previousOverflow;
    searchAbort?.abort();
    clearTimeout(searchTimer);
});

function onGlobalKey(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        openSearch();
    }
}
onMounted(() => window.addEventListener('keydown', onGlobalKey));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onGlobalKey);
    clearTimeout(searchTimer);
    searchAbort?.abort();
    if (searchOpen.value) document.body.style.overflow = previousOverflow;
});
const sections = [
    {
        label: 'Dashboard',
        items: [{ label: 'Dashboard', icon: 'dashboard', href: '/dashboard', exact: true }],
    },
    {
        label: 'Anggota',
        items: [{ label: 'Portal Saya', icon: 'account_circle', href: '/portal' }],
    },
    {
        label: 'siupk',
        items: [
            {
                key: 'master-data',
                label: 'Master Data',
                icon: 'database',
                children: [
                    { label: 'Data Desa', icon: 'location_city', href: '/master-data/villages' },
                    {
                        key: 'members',
                        label: 'Anggota',
                        icon: 'person',
                        children: [
                            { label: 'Tambah Anggota', href: '/master-data/members/create', exact: true },
                            { label: 'Daftar Anggota', href: '/master-data/members', exclude: '/master-data/members/create' },
                        ],
                    },
                    {
                        key: 'groups',
                        label: 'Kelompok',
                        icon: 'groups',
                        children: [
                            { label: 'Tambah Kelompok', href: '/master-data/groups/create', exact: true },
                            { label: 'Daftar Kelompok', href: '/master-data/groups', exclude: '/master-data/groups/create' },
                        ],
                    },
                    {
                        key: 'institutions',
                        label: 'Lembaga Lain',
                        icon: 'business',
                        children: [
                            { label: 'Tambah Lembaga', href: '/master-data/institutions/create', exact: true },
                            { label: 'Daftar Lembaga', href: '/master-data/institutions', exclude: '/master-data/institutions/create' },
                        ],
                    },
                ],
            },
            { label: 'Register Proposal', icon: 'assignment_add', href: '/lending/loans/create', exact: true },
            { label: 'Tahapan Perguliran', icon: 'sync_alt', href: '/lending/loans', exclude: '/lending/loans/create' },
            { label: 'Simulasi Pinjaman', icon: 'calculate', href: '/lending/simulation' },
        ],
    },
    {
        label: 'Keuangan',
        items: [
            {
                key: 'transactions',
                label: 'Transaksi',
                icon: 'receipt_long',
                children: [
                    { label: 'Daftar Jurnal', href: '/accounting/journals' },
                    { label: 'Daftar Inventaris', href: '/accounting/assets' },
                    { label: 'Jurnal Umum', href: '/accounting/journal-entries/create' },
                    { label: 'Jurnal Angsuran', href: '/accounting/journal-entries/installment' },
                ],
            },
            { label: 'Bagan Akun', icon: 'account_tree', href: '/accounting/chart-of-accounts' },
            {
                key: 'finance-periodic',
                label: 'Periodik',
                icon: 'event_note',
                children: [
                    { label: 'E-Budgeting', href: '/budgeting' },
                    { label: 'Tutup Buku', href: '/accounting/period-close' },
                    { label: 'Taksiran Pajak', href: '/accounting/tax-estimate' },
                ],
            },
            {
                key: 'reports',
                label: 'Pelaporan',
                icon: 'assessment',
                children: [
                    { label: 'Ringkasan Laporan', href: '/accounting/reports', exact: true },
                    { label: 'Portofolio Pinjaman', href: '/lending/reports/portfolio' },
                    { label: 'Rencana vs Realisasi', href: '/lending/reports/schedule-vs-actual' },
                    { label: 'Jurnal Transaksi', href: '/accounting/reports/journals' },
                    { label: 'Neraca Saldo', href: '/accounting/reports/trial-balance' },
                    { label: 'Neraca', href: '/accounting/reports/balance-sheet' },
                    { label: 'Laba Rugi', href: '/accounting/reports/income-statement' },
                    { label: 'Arus Kas', href: '/accounting/reports/cash-flow' },
                    { label: 'Perubahan Ekuitas', href: '/accounting/reports/equity-change' },
                    { label: 'CALK', href: '/accounting/reports/calk' },
                    { label: 'Buku Besar', href: '/accounting/reports/general-ledger' },
                ],
            },
        ],
    },
    {
        label: 'Tagihan',
        items: [
            { label: 'Daftar Tagihan', icon: 'receipt_long', href: '/billing/invoices' },
        ],
    },
    {
        label: 'Pengguna',
        items: [
            {
                key: 'users-access',
                label: 'Pengguna',
                icon: 'manage_accounts',
                children: [
                    { label: 'Manajemen Role', href: '/access/roles' },
                    { label: 'Manajemen User', href: '/access/users' },
                ],
            },
        ],
    },
    {
        label: 'Website',
        items: [
            { label: 'Berita', icon: 'article', href: '/website/posts' },
            { label: 'Halaman', icon: 'description', href: '/website/pages' },
            { label: 'Pengaturan Situs', icon: 'tune', href: '/website/settings' },
            { label: 'Pesan Masuk', icon: 'inbox', href: '/website/messages' },
        ],
    },
    {
        label: 'Pengaturan',
        items: [
            { label: 'Pengaturan', icon: 'settings', href: '/settings' },
            { label: 'WhatsApp Gateway', icon: 'chat', href: '/settings/whatsapp' },
        ],
    },
];
const platformNavigation = [{ label: 'Panel Admin', icon: 'admin_panel_settings', href: '/admin' }];

function isActive(item) {
    if (item.children) return item.children.some(isActive);
    if (!item.href || (item.exclude && currentPath.value.startsWith(item.exclude))) return false;

    return item.exact ? currentPath.value === item.href : currentPath.value.startsWith(item.href);
}

function toggle(key) {
    expanded.value[key] = !expanded.value[key];
}

function openActiveGroups(items) {
    items.forEach((item) => {
        if (!item.children) return;
        if (isActive(item)) expanded.value[item.key] = true;
        openActiveGroups(item.children);
    });
}

watch(currentPath, () => visibleSections.value.forEach((section) => openActiveGroups(section.items)), { immediate: true });

const logoutOpen = ref(false);
const themeOpen = ref(false);

function askLogout() {
    logoutOpen.value = true;
}

function preserveSidebarScroll() {
    if (sidebarNav.value) {
        sidebarScrollTop.value = sidebarNav.value.scrollTop;
    }
}

function restoreSidebarScroll() {
    nextTick(() => {
        if (sidebarNav.value) {
            sidebarNav.value.scrollTop = sidebarScrollTop.value;
        }
    });
}

router.on('before', preserveSidebarScroll);
router.on('navigate', restoreSidebarScroll);

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
            <button v-if="mobileMenuOpen" type="button" class="fixed inset-0 z-40 bg-primary/45 backdrop-blur-xs lg:hidden" aria-label="Tutup navigasi" @click="mobileMenuOpen = false" />
        </Transition>
        <aside class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-primary py-6 shadow-xl transition-transform duration-300 ease-in-out lg:translate-x-0" :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'">
            <div class="mb-6 flex items-center gap-3 px-6">
                <div class="grid size-10 place-items-center overflow-hidden rounded-lg bg-surface-container-lowest text-primary">
                    <img v-if="logoPath" :src="logoPath" alt="Logo lembaga" class="size-full object-contain" />
                    <AppIcon v-else name="account_balance" />
                </div>
                <div><p class="font-bold leading-none text-on-primary">BUMDesma/LKD</p><p class="mt-1 text-[10px] font-semibold uppercase tracking-widest text-primary-fixed-dim">Financial Management</p></div>
            </div>

            <nav ref="sidebarNav" class="scrollbar-hidden flex-1 space-y-5 overflow-y-auto px-2" aria-label="Navigasi utama">
                <section v-for="section in visibleSections" :key="section.label">
                    <h2 class="mb-1 px-4 text-[10px] font-bold uppercase tracking-[0.18em] text-primary-fixed-dim/70">{{ section.label }}</h2>
                    <div class="space-y-1">
                        <template v-for="item in section.items" :key="item.key || item.label">
                            <button
                                v-if="item.children"
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-left transition-colors hover:bg-primary-container hover:text-on-primary focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-fixed/30"
                                :class="isActive(item) ? 'text-on-primary' : 'text-primary-fixed-dim'"
                                :aria-expanded="Boolean(expanded[item.key])"
                                @click="toggle(item.key)"
                            >
                                <AppIcon :name="item.icon" :filled="isActive(item)" />
                                <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                                <AppIcon name="expand_more" class="text-lg transition-transform duration-200" :class="expanded[item.key] && 'rotate-180'" />
                            </button>
                            <Link
                                v-else-if="item.href"
                                :href="item.href"
                                class="flex items-center gap-3 rounded-lg px-4 py-2.5 transition-colors"
                                :class="isActive(item) ? 'bg-primary-container text-on-primary' : 'text-primary-fixed-dim hover:bg-primary-container hover:text-on-primary'"
                                @click="mobileMenuOpen = false"
                            >
                                <AppIcon :name="item.icon" :filled="isActive(item)" /><span>{{ item.label }}</span>
                            </Link>
                            <button v-else type="button" disabled class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-left text-primary-fixed-dim/45" :title="`${item.label} belum tersedia`"><AppIcon :name="item.icon" /><span>{{ item.label }}</span></button>

                            <Transition name="sidebar-menu">
                                <div v-if="item.children && expanded[item.key]" class="ml-5 space-y-1 overflow-hidden border-l border-primary-container pl-2">
                                <template v-for="child in item.children" :key="child.key || child.label">
                                    <button
                                        v-if="child.children"
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-primary-container hover:text-on-primary focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-fixed/30"
                                        :class="isActive(child) ? 'text-on-primary' : 'text-primary-fixed-dim'"
                                        :aria-expanded="Boolean(expanded[child.key])"
                                        @click="toggle(child.key)"
                                    >
                                        <AppIcon :name="child.icon" :filled="isActive(child)" class="text-xl" />
                                        <span class="min-w-0 flex-1 truncate">{{ child.label }}</span>
                                        <AppIcon name="expand_more" class="text-lg transition-transform duration-200" :class="expanded[child.key] && 'rotate-180'" />
                                    </button>
                                    <Link
                                        v-else-if="child.href"
                                        :href="child.href"
                                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors"
                                        :class="isActive(child) ? 'bg-primary-container text-on-primary' : 'text-primary-fixed-dim hover:bg-primary-container hover:text-on-primary'"
                                        @click="mobileMenuOpen = false"
                                    >
                                        <AppIcon :name="child.icon" :filled="isActive(child)" class="text-xl" /><span>{{ child.label }}</span>
                                    </Link>
                                    <button v-else type="button" disabled class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-primary-fixed-dim/45" :title="`${child.label} belum tersedia`"><AppIcon :name="child.icon" class="text-xl" /><span>{{ child.label }}</span></button>

                                    <Transition name="sidebar-menu">
                                        <div v-if="child.children && expanded[child.key]" class="ml-5 space-y-1 overflow-hidden border-l border-primary-container pl-2">
                                            <template v-for="leaf in child.children" :key="leaf.label">
                                                <Link
                                                    v-if="leaf.href"
                                                    :href="leaf.href"
                                                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors"
                                                    :class="isActive(leaf) ? 'bg-primary-container text-on-primary' : 'text-primary-fixed-dim hover:bg-primary-container hover:text-on-primary'"
                                                    @click="mobileMenuOpen = false"
                                                ><span class="size-1.5 rounded-full bg-current" />{{ leaf.label }}</Link>
                                                <button v-else type="button" disabled class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-primary-fixed-dim/45" :title="`${leaf.label} belum tersedia`"><span class="size-1.5 rounded-full bg-current" />{{ leaf.label }}</button>
                                            </template>
                                        </div>
                                    </Transition>
                                </template>
                                </div>
                            </Transition>
                        </template>
                    </div>
                </section>

                <section v-if="user?.is_superadmin">
                    <h2 class="mb-1 px-4 text-[10px] font-bold uppercase tracking-[0.18em] text-primary-fixed-dim/70">Platform</h2>
                    <Link v-for="item in platformNavigation" :key="item.label" :href="item.href" class="flex items-center gap-3 rounded-lg px-4 py-2.5 transition-colors" :class="isActive(item) ? 'bg-primary-container text-on-primary' : 'text-primary-fixed-dim hover:bg-primary-container hover:text-on-primary'" @click="mobileMenuOpen = false"><AppIcon :name="item.icon" :filled="isActive(item)" /><span>{{ item.label }}</span></Link>
                </section>
            </nav>

            <div class="mt-4 border-t border-primary-container px-4 pt-4">
                <div class="flex items-center gap-3 rounded-xl bg-primary-container/50 p-3">
                    <Link href="/profile" class="relative grid size-10 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-fixed text-sm font-bold text-primary transition hover:opacity-85" aria-label="Buka Profil"><img v-if="user?.photo_url && !avatarError" :src="user.photo_url" :alt="user?.name || 'User'" class="size-full object-cover" @error="avatarError = true" /><span v-else>{{ user?.name?.charAt(0).toUpperCase() || 'U' }}</span></Link>
                    <Link href="/profile" class="min-w-0 flex-1 group" aria-label="Buka Profil"><p class="truncate font-bold text-on-primary group-hover:underline">{{ user?.name || 'Pengguna' }}</p><p class="truncate text-xs text-primary-fixed-dim">{{ props.unitName || 'Unit belum dipilih' }}</p></Link>
                    <AppIconButton name="logout" tone="neutral" size="sm" rounded="lg" aria-label="Keluar" class="text-primary-fixed-dim hover:bg-on-primary/10 hover:text-on-primary" @click="askLogout" />
                </div>
            </div>
        </aside>

        <header class="sticky top-0 z-30 flex h-16 items-center border-b border-outline-variant bg-surface px-4 lg:ml-64 lg:px-6">
            <AppIconButton name="menu" tone="primary" size="sm" rounded="lg" aria-label="Buka navigasi" class="mr-3 lg:hidden" @click="mobileMenuOpen = true" />
            <button
                type="button"
                class="flex w-full max-w-md items-center gap-3 rounded-full border-0 bg-surface-container-low py-2 pr-3 pl-3 text-left text-sm text-on-surface-variant transition hover:bg-surface-container focus:outline-none focus:ring-2 focus:ring-primary-container/30"
                aria-label="Buka pencarian"
                @click="openSearch"
            >
                <AppIcon name="search" class="text-on-surface-variant" />
                <span class="min-w-0 flex-1 truncate">Cari anggota, kelompok, pinjaman...</span>
                <kbd class="hidden rounded-md border border-outline-variant bg-surface px-1.5 py-0.5 text-[10px] font-semibold text-on-surface-variant sm:inline">Ctrl K</kbd>
            </button>
            <p v-if="props.unitName" class="ml-6 hidden items-center gap-2 text-sm font-bold text-primary xl:flex"><AppIcon name="location_on" class="text-secondary" />{{ props.unitName }}</p>
            <div class="ml-auto flex items-center gap-1 pl-3">
                <AppIconButton
                    name="palette"
                    tone="neutral"
                    rounded="full"
                    data-theme-trigger
                    class="shrink-0 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                    :class="themeOpen && 'bg-surface-container text-primary'"
                    aria-label="Pilih tema tampilan"
                    aria-haspopup="menu"
                    :aria-expanded="themeOpen"
                    @click="themeOpen = !themeOpen"
                />
                <Link
                    href="/changelog"
                    class="grid size-10 shrink-0 place-items-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary"
                    :class="currentPath === '/changelog' && 'bg-surface-container text-primary'"
                    title="Catatan Rilis & Changelog"
                    aria-label="Catatan Rilis & Changelog"
                >
                    <AppIcon name="history_edu" class="text-2xl leading-none" />
                </Link>
                <NotificationDropdown />
                <Link href="/profile" class="grid size-10 shrink-0 place-items-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary" aria-label="Profil"><AppIcon name="account_circle" class="text-2xl leading-none" /></Link>
            </div>
        </header>
        <main class="p-4 sm:p-6 lg:ml-64 lg:p-8">
            <div
                v-if="impersonatedBy"
                class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/40 bg-primary-container/30 px-4 py-3 text-sm text-primary shadow-sm"
            >
                <div class="flex items-center gap-2.5">
                    <AppIcon name="admin_panel_settings" tone="primary" />
                    <p class="font-medium">
                        <span class="font-bold">Mode Impersonasi Superadmin:</span>
                        Anda sedang mengakses tenant sebagai <span class="font-bold">{{ user?.name }}</span> (diinisiasi oleh Superadmin <span class="font-bold">{{ impersonatorName || 'Superadmin' }}</span>).
                    </p>
                </div>
                <form @submit.prevent="leaveImpersonation">
                    <AppButton size="compact" variant="secondary" icon="logout" :loading="leaveForm.processing">
                        Kembali ke Superadmin
                    </AppButton>
                </form>
            </div>
            <div
                v-if="isTrainingMode"
                class="mb-6 flex items-center justify-between gap-3 rounded-xl border border-warning/40 bg-warning-container/30 px-4 py-3 text-sm text-primary shadow-sm"
            >
                <div class="flex items-center gap-2.5">
                    <span class="inline-block size-2.5 rounded-full bg-warning animate-pulse" />
                    <p class="font-medium">
                        <span class="font-bold">Mode Pelatihan Aktif:</span>
                        Transaksi yang di-input pada periode ini adalah data simulasi latihan dan dapat dibersihkan oleh Superadmin.
                    </p>
                </div>
            </div>
            <Transition name="page" mode="out-in" appear>
                <div :key="currentPath" class="min-w-0 flex-1">
                    <slot />
                </div>
            </Transition>
        </main>
        <AssistantWidget v-if="assistantEnabled" />
        <AppConfirmDialog />
        <AppToast />

        <AppModal v-model="logoutOpen" title="Keluar dari aplikasi?" size="sm">
            <p class="text-sm text-on-surface-variant">
                Sesi <span class="font-semibold text-primary">{{ user?.name || 'Anda' }}</span> akan diakhiri.
                Lanjutkan?
            </p>
            <template #footer>
                <AppButton variant="secondary" :disabled="logoutForm.processing" @click="logoutOpen = false">Batal</AppButton>
                <AppButton variant="danger" icon="logout" :loading="logoutForm.processing" @click="logout">Keluar</AppButton>
            </template>
        </AppModal>

        <ThemeMenu v-model="themeOpen" />
        <KeyboardShortcutsModal v-model="showShortcutsModal" />

        <Teleport to="body">
            <Transition name="cmdk">
                <div
                    v-if="searchOpen"
                    class="fixed inset-0 z-[60] flex items-start justify-center bg-primary/50 p-4 pt-[12vh] sm:pt-[15vh]"
                    role="presentation"
                    @click.self="closeSearch"
                >
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-label="Pencarian"
                        class="flex max-h-[min(36rem,75vh)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
                        @keydown="onPaletteKeydown"
                    >
                        <div class="flex shrink-0 items-center gap-2 border-b border-outline-variant px-3">
                            <AppIcon name="search" class="text-xl text-on-surface-variant" />
                            <input
                                ref="searchInput"
                                v-model="searchQ"
                                type="search"
                                placeholder="Cari anggota, kelompok, pinjaman..."
                                aria-label="Kata kunci pencarian"
                                class="h-14 min-w-0 flex-1 border-0 bg-transparent text-base text-primary placeholder:text-on-surface-variant focus:outline-none focus:ring-0"
                                autocomplete="off"
                                @input="onSearchInput"
                            />
                            <AppIconButton
                                v-if="searchQ"
                                name="close"
                                tone="neutral"
                                rounded="full"
                                size="sm"
                                aria-label="Hapus kata kunci"
                                class="shrink-0 text-on-surface-variant hover:bg-surface-container-low hover:text-primary"
                                @click="clearSearchQuery"
                            />
                            <AppIconButton
                                v-else
                                name="close"
                                tone="neutral"
                                rounded="full"
                                size="sm"
                                aria-label="Tutup pencarian"
                                class="shrink-0 text-on-surface-variant hover:bg-surface-container-low hover:text-primary"
                                @click="closeSearch"
                            />
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto py-2" role="listbox">
                            <p v-if="searchQ.trim().length < 2" class="px-4 py-10 text-center text-sm text-on-surface-variant">
                                Ketik minimal 2 karakter untuk mencari.
                            </p>
                            <p v-else-if="searchLoading" class="px-4 py-10 text-center text-sm text-on-surface-variant">Mencari...</p>
                            <template v-else-if="searchGroups.length">
                                <div v-for="group in searchGroups" :key="group.key" class="mb-1">
                                    <p class="px-4 py-1.5 text-xs font-semibold text-on-surface-variant">{{ group.label }}</p>
                                    <Link
                                        v-for="(item, idx) in group.items"
                                        :key="`${group.key}-${idx}`"
                                        :href="item.href"
                                        role="option"
                                        class="mx-2 flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors"
                                        :class="flatResults[searchHighlight]?.href === item.href && flatResults[searchHighlight]?.title === item.title
                                            ? 'bg-primary-container/40 text-primary'
                                            : 'text-on-surface hover:bg-surface-container-low'"
                                        @mouseenter="searchHighlight = flatResults.findIndex((r) => r.href === item.href && r.title === item.title)"
                                        @click="pickResult(item)"
                                    >
                                        <AppIcon :name="item.icon || 'search'" class="shrink-0 text-xl text-primary" />
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-semibold">{{ item.title }}</span>
                                            <span v-if="item.subtitle" class="block truncate text-xs text-on-surface-variant">{{ item.subtitle }}</span>
                                        </span>
                                    </Link>
                                </div>
                            </template>
                            <p v-else class="px-4 py-10 text-center text-sm text-on-surface-variant">
                                Tidak ada hasil untuk "{{ searchQ.trim() }}".
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-3 border-t border-outline-variant px-4 py-2 text-[11px] text-on-surface-variant">
                            <span><kbd class="rounded border border-outline-variant px-1 font-mono">??</kbd> pilih</span>
                            <span><kbd class="rounded border border-outline-variant px-1 font-mono">Enter</kbd> buka</span>
                            <span><kbd class="rounded border border-outline-variant px-1 font-mono">Esc</kbd> tutup</span>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.sidebar-menu-enter-active,
.sidebar-menu-leave-active {
    max-height: 40rem;
    opacity: 1;
    transform: translateY(0);
    transition: max-height 240ms ease, opacity 180ms ease, transform 240ms ease;
}

.sidebar-menu-enter-from,
.sidebar-menu-leave-to {
    max-height: 0;
    opacity: 0;
    transform: translateY(-0.25rem);
}

.cmdk-enter-active,
.cmdk-leave-active {
    transition: opacity 160ms ease;
}
.cmdk-enter-active > div,
.cmdk-leave-active > div {
    transition: transform 160ms ease, opacity 160ms ease;
}
.cmdk-enter-from,
.cmdk-leave-to {
    opacity: 0;
}
.cmdk-enter-from > div,
.cmdk-leave-to > div {
    opacity: 0;
    transform: translateY(-0.5rem) scale(0.98);
}

@media (prefers-reduced-motion: reduce) {
    .sidebar-menu-enter-active,
    .sidebar-menu-leave-active,
    .cmdk-enter-active,
    .cmdk-leave-active,
    .cmdk-enter-active > div,
    .cmdk-leave-active > div {
        transition: none;
    }
}
</style>
