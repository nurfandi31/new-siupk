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
import AppLanguageSwitcher from '../Components/AppLanguageSwitcher.vue';
import { useKeyboardShortcuts } from '../composables/useKeyboardShortcuts';
import { useI18n } from '../i18n';

const { t, locale } = useI18n();
const props = defineProps({ unitName: { type: String, default: null } });

const page = usePage();
const logoPath = computed(() => page.props.logoPath ?? null);
const user = computed(() => page.props.auth?.user);
const permissions = computed(() => page.props.auth?.permissions ?? []);
const navMap = computed(() => page.props.auth?.nav_map ?? {});
const menuAccess = computed(() => page.props.auth?.menu_access ?? null);
const menuMap = computed(() => page.props.auth?.menu_map ?? {});
const assistantEnabled = computed(() => {
    if (!page.props.assistant?.enabled) return false;
    return can('assistant.use');
});
const currentPath = computed(() => page.url.split('?')[0]);
const sidebarNav = ref(null);
const mobileMenuOpen = ref(false);
const sidebarCollapsed = ref(false);
const expanded = ref({});
const logoutForm = useForm({});
const isTrainingMode = computed(() => page.props.tenant?.is_training_mode === true);
const savingsEnabled = computed(() => page.props.modules?.simpanan === true);
const impersonatedBy = computed(() => page.props.auth?.impersonated_by);
const impersonatorName = computed(() => page.props.auth?.impersonator_name);
const leaveForm = useForm({});
const avatarError = ref(false);
const { showShortcutsModal } = useKeyboardShortcuts();

const SIDEBAR_KEY = 'siupk-auth-sidebar';

watch(() => user.value?.photo_url, () => {
    avatarError.value = false;
});

onMounted(() => {
    try {
        const v = localStorage.getItem(SIDEBAR_KEY);
        if (v === '1') sidebarCollapsed.value = true;
    } catch (e) {
        // ignore
    }
});

watch(sidebarCollapsed, (val) => {
    try {
        localStorage.setItem(SIDEBAR_KEY, val ? '1' : '0');
    } catch (e) {
        // ignore
    }
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
    // Exact match wins first.
    if (map[href] !== undefined) return map[href];
    // Longest prefix that is followed by a path/word boundary (so '/settings'
    // does not incorrectly match '/settings-archive').
    for (const prefix of keys) {
        if (href.length > prefix.length && href.startsWith(prefix)) {
            const next = href.charAt(prefix.length);
            if (next === '/' || next === '?' || next === '#') {
                return map[prefix];
            }
        }
    }
    return null;
}

function menuKeyForHref(href) {
    if (!href) return null;
    const map = menuMap.value || {};
    const path = href.split('?')[0];
    if (map[path] !== undefined) return map[path];
    const keys = Object.keys(map).sort((a, b) => b.length - a.length);
    for (const prefix of keys) {
        if (path.length > prefix.length && path.startsWith(prefix)) {
            const next = path.charAt(prefix.length);
            if (next === '/') return map[prefix];
        }
    }
    return null;
}

function menuAllowed(href) {
    const access = menuAccess.value;
    if (!Array.isArray(access)) return true; // null / not restricted
    const key = menuKeyForHref(href);
    if (!key) return true; // menus outside the catalog keep their default behavior
    return access.includes(key);
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
                if (!menuAllowed(item.href)) return null;
            }
            return item;
        })
        .filter(Boolean);
}

const visibleSections = computed(() =>
    sections
        .map((section) => {
            if (section.module === 'simpanan' && !savingsEnabled.value) return null;
            const items = filterNavItems(section.items);
            if (!items.length) return null;
            return { ...section, items };
        })
        .filter(Boolean),
);

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
        // navigation handled by Link
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

function navLabel(label) {
    if (label === 'Beranda') return locale.value === 'en' ? 'Dashboard' : t('Beranda');
    return t(label);
}

const sections = [
    {
        label: t('Beranda'),
        items: [{ label: t('Beranda'), icon: 'dashboard', href: '/dashboard', exact: true }],
    },
    {
        label: t('Pengaturan'),
        items: [
            { label: t('Pengaturan'), icon: 'settings', href: '/settings', exact: true },
            { label: t('Gerbang WhatsApp'), icon: 'chat', href: '/settings/whatsapp' },
        ],
    },
    {
        label: t('Anggota'),
        items: [{ label: t('Portal Saya'), icon: 'account_circle', href: '/portal' }],
    },
    {
        label: 'siupk',
        items: [
            {
                key: 'master-data',
                label: t('Master Data'),
                icon: 'database',
                children: [
                    { label: t('Data Desa'), icon: 'location_city', href: '/master-data/villages' },
                    {
                        key: 'members',
                        label: t('Anggota'),
                        icon: 'person',
                        children: [
                            { label: t('Tambah Anggota'), href: '/master-data/members/create', exact: true },
                            { label: t('Daftar Anggota'), href: '/master-data/members', exclude: '/master-data/members/create' },
                        ],
                    },
                    {
                        key: 'groups',
                        label: t('Kelompok'),
                        icon: 'groups',
                        children: [
                            { label: t('Tambah Kelompok'), href: '/master-data/groups/create', exact: true },
                            { label: t('Daftar Kelompok'), href: '/master-data/groups', exclude: '/master-data/groups/create' },
                        ],
                    },
                    {
                        key: 'institutions',
                        label: t('Lembaga Lain'),
                        icon: 'business',
                        children: [
                            { label: t('Tambah Lembaga'), href: '/master-data/institutions/create', exact: true },
                            { label: t('Daftar Lembaga'), href: '/master-data/institutions', exclude: '/master-data/institutions/create' },
                        ],
                    },
                ],
            },
            {
                key: 'loans',
                label: t('Pinjaman'),
                icon: 'account_balance',
                children: [
                    {
                        key: 'register-proposal',
                        label: t('Register Proposal'),
                        icon: 'assignment_add',
                        children: [
                            { label: t('Proposal Kelompok'), href: '/lending/loans/create', exact: true },
                            { label: t('Proposal Individu'), href: '/lending/member-loans/create', exact: true },
                        ],
                    },
                    {
                        key: 'tahapan-perguliran',
                        label: t('Tahapan Perguliran'),
                        icon: 'sync_alt',
                        children: [
                            { label: t('Kelompok'), href: '/lending/loans', exclude: '/lending/loans/create' },
                            { label: t('Individu'), href: '/lending/member-loans', exclude: '/lending/member-loans/create' },
                        ],
                    },
                    { label: t('Simulasi Pinjaman'), icon: 'calculate', href: '/lending/simulation' },
                ],
            },
        ],
    },
    {
        label: t('Simpanan'),
        module: 'simpanan',
        accent: true,
        items: [
            { label: t('Buka Rekening'), icon: 'add_card', href: '/simpanan/create' },
            { label: t('Rekening Simpanan'), icon: 'savings', href: '/simpanan', exclude: ['/simpanan/create', '/simpanan/bunga'] },
            { label: t('Bunga & Pajak'), icon: 'percent', href: '/simpanan/bunga' },
        ],
    },
    {
        label: t('Keuangan'),
        items: [
            {
                key: 'transactions',
                label: t('Transaksi'),
                icon: 'receipt_long',
                children: [
                    { label: t('Jurnal Umum'), href: '/accounting/journal-entries/create' },
                    {
                        key: 'jurnal-angsuran',
                        label: t('Jurnal Angsuran'),
                        icon: 'payments',
                        children: [
                            { label: t('Kelompok'), href: '/accounting/journal-entries/installment' },
                            { label: t('Individu'), href: '/accounting/journal-entries/installment-individual' },
                        ],
                    },
                    { label: t('Daftar Jurnal'), href: '/accounting/journals' },
                    { label: t('Daftar Inventaris'), href: '/accounting/assets' },
                ],
            },
            { label: t('Bagan Akun'), icon: 'account_tree', href: '/accounting/chart-of-accounts' },
            {
                key: 'finance-periodic',
                label: t('Periodik'),
                icon: 'event_note',
                children: [
                    { label: t('E-Budgeting'), href: '/budgeting' },
                    { label: t('Tutup Buku'), href: '/accounting/period-close' },
                    { label: t('Taksiran Pajak'), href: '/accounting/tax-estimate' },
                ],
            },
            {
                key: 'reports',
                label: t('Pelaporan'),
                icon: 'assessment',
                children: [
                    { label: t('Ringkasan Laporan'), href: '/accounting/reports', exact: true },
                    { label: t('Portofolio Pinjaman'), href: '/lending/reports/portfolio' },
                    { label: t('Rencana vs Realisasi'), href: '/lending/reports/schedule-vs-actual' },
                    {
                        key: 'lpp',
                        label: t('LPP (Perkembangan Piutang)'),
                        icon: 'trending_up',
                        children: [
                            { label: t('Rekap Desa'), href: '/lending/reports/lpp-desa' },
                            { label: t('Rekap Desa Individu'), href: '/lending/reports/lpp-desa-individu' },
                            { label: t('Rincian Kelompok'), href: '/lending/reports/lpp-kelompok' },
                            { label: t('Rincian Individu'), href: '/lending/reports/lpp-individu' },
                        ],
                    },
                    {
                        key: 'kolek',
                        label: t('Kolektibilitas'),
                        icon: 'fact_check',
                        children: [
                            { label: t('Rekap Desa'), href: '/lending/reports/kolek-desa' },
                            { label: t('Rincian Kelompok'), href: '/lending/reports/kolek-kelompok' },
                            { label: t('Rekap Desa Individu'), href: '/lending/reports/kolek-desa-individu' },
                            { label: t('Rincian Individu'), href: '/lending/reports/kolek-individu' },
                        ],
                    },
                    {
                        key: 'lending-weekly',
                        label: t('Laporan Mingguan'),
                        icon: 'calendar_view_week',
                        children: [
                            {
                                label: t('LPP Individu Mingguan'),
                                href: '/lending/reports/weekly/lpp-individu',
                            },
                            {
                                label: t('LPP Kelompok Mingguan'),
                                href: '/lending/reports/weekly/lpp-kelompok',
                            },
                            {
                                label: t('Kolektibilitas Individu Mingguan'),
                                href: '/lending/reports/weekly/kolek-individu',
                            },
                            {
                                label: t('Kolektibilitas Kelompok Mingguan'),
                                href: '/lending/reports/weekly/kolek-kelompok',
                            },
                        ],
                    },
                    { label: t('Cadangan Penghapusan (CKPN)'), href: '/lending/reports/cadangan-penghapusan' },
                    { label: t('CKPN Pinjaman Individu'), href: '/lending/reports/cadangan-penghapusan-individu' },
                    // === Phase 1: Pipeline Pinjaman (proses awal pengajuan) ===
                    {
                        key: 'lending-pipeline',
                        label: t('Pipeline Pinjaman'),
                        icon: 'inventory',
                        children: [
                            { label: t('Daftar Proposal'), icon: 'description', href: '/lending/reports/proposals' },
                            { label: t('Daftar Verifikasi'), icon: 'pending_actions', href: '/lending/reports/verifications' },
                            { label: t('Daftar Waiting List'), icon: 'event', href: '/lending/reports/waiting-list' },
                        ],
                    },
                    // === Phase 1: Status Pinjaman (lunas & hapus buku) ===
                    {
                        key: 'lending-status',
                        label: t('Status Pinjaman'),
                        icon: 'checklist',
                        children: [
                            { label: t('Daftar Pinjaman Lunas'), icon: 'check_circle', href: '/lending/reports/paid' },
                            { label: t('Pinjaman Dihapusbukukan'), icon: 'cancel', href: '/lending/reports/write-offs' },
                            { label: t('Pinjaman Dihapusbukukan Individu'), icon: 'person_off', href: '/lending/reports/write-offs-individu' },
                            { label: t('Anggota Hapus Buku Kelompok'), icon: 'group_remove', href: '/lending/reports/write-offs/beneficiaries' },
                        ],
                    },
                    // === Phase 1: Tracking & Monitoring (tagihan & tunggakan) ===
                    {
                        key: 'lending-tracking',
                        label: t('Tracking & Monitoring'),
                        icon: 'monitor_heart',
                        children: [
                            { label: t('Tagihan Jatuh Tempo Hari Ini'), icon: 'today', href: '/lending/reports/due-today' },
                            { label: t('Daftar Tunggakan'), icon: 'warning', href: '/lending/reports/overdue' },
                            {
                                label: t('Pelunasan 3 Bulan ke Depan'),
                                icon: 'event_available',
                                href: '/lending/reports/near-settlement',
                            },
                        ],
                    },
                    // === Phase 1: Laporan Tambahan (rencana & realisasi khusus individu) ===
                    {
                        key: 'lending-extra',
                        label: t('Laporan Tambahan'),
                        icon: 'summarize',
                        children: [
                            { label: t('Rencana & Realisasi Individu'), icon: 'compare_arrows', href: '/lending/reports/schedule-vs-actual-individu' },
                        ],
                    },
                    // === Phase 1: Monitoring Aktif (kelompok & pemanfaat aktif) ===
                    {
                        key: 'lending-active',
                        label: t('Monitoring Aktif'),
                        icon: 'online_prediction',
                        children: [
                            { label: t('Kelompok Aktif'), icon: 'group', href: '/lending/reports/groups/active' },
                            { label: t('Individu Aktif'), icon: 'person', href: '/lending/reports/members/active' },
                            { label: t('Pemanfaat Aktif'), icon: 'groups_2', href: '/lending/reports/beneficiaries/active' },
                        ],
                    },
                    {
                        key: 'ojk',
                        label: t('Laporan OJK'),
                        icon: 'account_balance',
                        children: [
                            { label: t('Cover OJK'), href: '/regulatory/ojk/cover' },
                            { label: t('Profil OJK'), href: '/regulatory/ojk/profile' },
                            { label: t('Neraca OJK'), href: '/regulatory/ojk/balance-sheet' },
                            { label: t('Laba Rugi OJK'), href: '/regulatory/ojk/income-statement' },
                            { label: t('Rincian Pinjaman Aktif'), href: '/regulatory/ojk/active-loans' },
                            { label: t('Rincian Pinjaman Lunas Kelompok'), href: '/regulatory/ojk/paid-group' },
                            { label: t('Rincian Pinjaman Lunas Individu'), href: '/regulatory/ojk/paid-individu' },
                            { label: t('Rincian Tabungan'), href: '/regulatory/ojk/savings' },
                            { label: t('Rincian Pinjaman Diterima'), href: '/regulatory/ojk/loans-received' },
                            { label: t('Kolektibilitas OJK'), href: '/regulatory/ojk/collectibility' },
                            { label: t('Kolektibilitas OJK v2'), href: '/regulatory/ojk/collectibility-v2' },
                            { label: t('Penyisihan Cadangan'), href: '/regulatory/ojk/allowance' },
                            { label: t('Tingkat Kesehatan (POJK 41)'), href: '/regulatory/ojk/health?framework=41' },
                            { label: t('Tingkat Kesehatan (POJK 19)'), href: '/regulatory/ojk/health?framework=19' },
                            { label: t('Rincian Bunga'), href: '/regulatory/ojk/interest' },
                            { label: t('Simpanan Piutang'), href: '/regulatory/ojk/savings-receivables' },
                        ],
                    },
                    { label: t('Jurnal Transaksi'), href: '/accounting/reports/journals' },
                    { label: t('Neraca Saldo'), href: '/accounting/reports/trial-balance' },
                    { label: t('Neraca'), href: '/accounting/reports/balance-sheet' },
                    { label: t('Laba Rugi'), href: '/accounting/reports/income-statement' },
                    { label: t('Arus Kas'), href: '/accounting/reports/cash-flow' },
                    { label: t('Perubahan Ekuitas'), href: '/accounting/reports/equity-change' },
                    { label: t('CALK'), href: '/accounting/reports/calk' },
                    { label: t('Buku Besar'), href: '/accounting/reports/general-ledger' },
                    { label: t('Daftar Simpanan'), href: '/accounting/reports/simpanan' },
                ],
            },
        ],
    },
    {
        label: t('Tagihan'),
        items: [
            { label: t('Daftar Tagihan'), icon: 'receipt_long', href: '/billing/invoices' },
        ],
    },
    {
        label: t('Pengguna'),
        items: [
            {
                key: 'users-access',
                label: t('Pengguna'),
                icon: 'manage_accounts',
                children: [
                    { label: t('Manajemen Role'), href: '/access/roles' },
                    { label: t('Manajemen User'), href: '/access/users' },
                ],
            },
        ],
    },
    {
        label: t('Website'),
        items: [
            { label: t('Berita'), icon: 'article', href: '/website/posts' },
            { label: t('Halaman'), icon: 'description', href: '/website/pages' },
            { label: t('Pengaturan Situs'), icon: 'tune', href: '/website/settings' },
            { label: t('Pesan Masuk'), icon: 'inbox', href: '/website/messages' },
        ],
    },
];

const platformNavigation = [{ label: t('Panel Admin'), icon: 'admin_panel_settings', href: '/admin' }];

function isActive(item) {
    if (item.children) return item.children.some(isActive);
    if (!item.href) return false;
    if (item.exclude) {
        const excludes = Array.isArray(item.exclude) ? item.exclude : [item.exclude];
        if (excludes.some((prefix) => currentPath.value.startsWith(prefix))) return false;
    }
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

function scrollSidebarToActive() {
    nextTick(() => {
        const nav = sidebarNav.value;
        if (!nav) return;
        const active = nav.querySelector('[data-sidebar-active="true"]');
        if (!active) {
            nav.scrollTop = 0;
            return;
        }
        const navTop = nav.getBoundingClientRect().top;
        const activeTop = active.getBoundingClientRect().top;
        const offset = activeTop - navTop - 16;
        const navBottom = navTop + nav.clientHeight;
        if (activeTop < navTop || activeTop > navBottom - 40) {
            nav.scrollTop = Math.max(0, nav.scrollTop + offset);
        }
    });
}

router.on('navigate', scrollSidebarToActive);

function logout() {
    logoutForm.post('/logout', {
        onFinish: () => {
            logoutOpen.value = false;
        },
    });
}

function closeMobileMenu() {
    mobileMenuOpen.value = false;
}

function onParentItemClick(item, event) {
    if (sidebarCollapsed.value && !mobileMenuOpen.value) {
        event.preventDefault();
        sidebarCollapsed.value = false;
        return;
    }
    toggle(item.key);
}
</script>

<template>
    <div class="min-h-screen bg-surface text-on-surface">
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <button v-if="mobileMenuOpen" type="button" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden" :aria-label="t('Tutup navigasi')" @click="closeMobileMenu" />
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex flex-col border-r border-white/5 bg-zinc-900 text-zinc-100 shadow-2xl shadow-black/40 transition-[width,transform] duration-300 ease-in-out lg:translate-x-0"
            :class="[
                mobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
                sidebarCollapsed && !mobileMenuOpen ? 'lg:w-16' : 'lg:w-64',
                'w-64',
            ]"
        >
            <button
                type="button"
                class="absolute -right-3 top-5 z-10 hidden size-8 place-items-center rounded-full chip-shadow bg-surface text-on-surface ring-1 ring-outline-variant transition hover:scale-110 hover:bg-surface-container focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-container lg:grid lg:top-7"
                :aria-label="sidebarCollapsed ? t('Buka sidebar') : t('Tutup sidebar')"
                :title="sidebarCollapsed ? t('Buka sidebar') : t('Tutup sidebar')"
                @click="sidebarCollapsed = !sidebarCollapsed"
            >
                <AppIcon :name="sidebarCollapsed ? 'chevron_right' : 'chevron_left'" class="text-xl leading-none" />
            </button>

            <div class="flex items-center gap-3 px-4 py-6 transition-[padding] duration-300 lg:px-6" :class="sidebarCollapsed && !mobileMenuOpen ? 'lg:justify-center lg:px-2' : ''">
                <div class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-zinc-800 text-zinc-100 ring-1 ring-white/10">
                    <img v-if="logoPath" :src="logoPath" :alt="t('Logo lembaga')" class="size-full object-contain" />
                    <AppIcon v-else name="account_balance" class="text-zinc-100" />
                </div>
                <div v-show="!(sidebarCollapsed && !mobileMenuOpen)" class="min-w-0 flex-1 transition-opacity duration-200 lg:opacity-100" :class="sidebarCollapsed && !mobileMenuOpen ? 'lg:hidden' : 'lg:opacity-100'">
                    <p class="truncate font-bold leading-none text-white">{{ page.props.auth?.tenant?.name || t('BUMDesma/LKD') }}</p>
                    <p class="mt-1 truncate text-[10px] font-semibold uppercase tracking-widest text-zinc-400">{{ t('Financial Management') }}</p>
                </div>
                <button
                    type="button"
                    class="grid size-9 shrink-0 place-items-center rounded-lg text-zinc-400 transition-colors hover:bg-white/5 hover:text-white lg:hidden"
                    :aria-label="t('Tutup navigasi')"
                    @click="closeMobileMenu"
                >
                    <AppIcon name="close" class="text-xl" />
                </button>
            </div>

            <nav
                ref="sidebarNav"
                class="scrollbar-hidden flex-1 space-y-5 overflow-y-auto px-2 py-2"
                :aria-label="t('Navigasi utama')"
            >
                <section v-for="(section, sIdx) in visibleSections" :key="section.label">
                    <h2 class="mb-1 flex items-center gap-2 px-4 text-[10px] font-bold uppercase tracking-[0.18em] text-zinc-500 transition-opacity duration-200 lg:opacity-100" :class="sidebarCollapsed && !mobileMenuOpen ? 'lg:hidden' : 'lg:opacity-100'">
                        <span v-if="section.accent" class="size-1.5 shrink-0 rounded-full bg-secondary" aria-hidden="true" />
                        {{ navLabel(section.label) }}
                    </h2>
                    <div class="space-y-1">
                        <template v-for="item in section.items" :key="item.key || item.label">
                            <button
                                v-if="item.children"
                                type="button"
                                class="sidebar-item group relative flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-left transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary/30"
                                :class="[
                                    isActive(item)
                                        ? 'is-parent-active'
                                        : 'text-zinc-400 hover:bg-white/[0.04] hover:text-zinc-100',
                                    sidebarCollapsed && !mobileMenuOpen ? 'lg:justify-center lg:px-0' : '',
                                ]"
                                :title="sidebarCollapsed && !mobileMenuOpen ? navLabel(item.label) : undefined"
                                :aria-expanded="Boolean(expanded[item.key])"
                                :data-sidebar-active="isActive(item) ? 'true' : null"
                                @click="onParentItemClick(item, $event)"
                            >
                                <span
                                    v-if="isActive(item)"
                                    class="sidebar-active-bar absolute inset-y-0 left-0 w-1 rounded-r-full"
                                    aria-hidden="true"
                                />
                                <AppIcon :name="item.icon" :filled="isActive(item)" class="shrink-0 text-xl leading-none transition-colors duration-150" />
                                <span class="min-w-0 flex-1 truncate text-sm font-medium transition-opacity duration-200" :class="sidebarCollapsed && !mobileMenuOpen ? 'lg:hidden' : 'lg:opacity-100'">{{ navLabel(item.label) }}</span>
                                <AppIcon v-show="!(sidebarCollapsed && !mobileMenuOpen)" name="expand_more" class="text-lg transition-transform duration-200" :class="expanded[item.key] && 'rotate-180'" />
                            </button>
                            <Link
                                v-else-if="item.href"
                                :href="item.href"
                                class="sidebar-item group relative flex items-center gap-3 rounded-lg px-4 py-2.5 transition-colors duration-150"
                                :class="[
                                    isActive(item)
                                        ? ''
                                        : 'text-zinc-400 hover:bg-white/[0.04] hover:text-zinc-100',
                                    sidebarCollapsed && !mobileMenuOpen ? 'lg:justify-center lg:px-0' : '',
                                ]"
                                :title="sidebarCollapsed && !mobileMenuOpen ? navLabel(item.label) : undefined"
                                :data-sidebar-active="isActive(item) ? 'true' : null"
                                @click="closeMobileMenu"
                            >
                                <span
                                    v-if="isActive(item)"
                                    class="sidebar-active-bar absolute inset-y-0 left-0 w-1 rounded-r-full"
                                    aria-hidden="true"
                                />
                                <AppIcon :name="item.icon" :filled="isActive(item)" class="shrink-0 text-xl leading-none transition-colors duration-150" /><span class="text-sm font-semibold transition-opacity duration-200" :class="sidebarCollapsed && !mobileMenuOpen ? 'lg:hidden' : 'lg:opacity-100'">{{ navLabel(item.label) }}</span>
                            </Link>
                            <button v-else type="button" disabled class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-left text-zinc-600" :title="`${navLabel(item.label)} — ${t('Belum tersedia')}`"><AppIcon :name="item.icon" /><span>{{ navLabel(item.label) }}</span></button>

                            <Transition name="sidebar-menu">
                                <div v-if="item.children && expanded[item.key] && !(sidebarCollapsed && !mobileMenuOpen)" class="ml-5 space-y-1 overflow-hidden border-l border-white/5 pl-2">
                                    <template v-for="child in item.children" :key="child.key || child.label">
                                        <button
                                            v-if="child.children"
                                            type="button"
                                            class="sidebar-item is-child group relative flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary/30"
                                            :class="isActive(child)
                                                ? ''
                                                : 'text-zinc-400 hover:bg-white/[0.04] hover:text-zinc-100'"
                                            :aria-expanded="Boolean(expanded[child.key])"
                                            :data-sidebar-active="isActive(child) ? 'true' : null"
                                            @click="toggle(child.key)"
                                        >
                                            <span
                                                v-if="isActive(child)"
                                                class="sidebar-active-bar absolute inset-y-0 left-[-9px] w-1 rounded-full"
                                                aria-hidden="true"
                                            />
                                            <AppIcon :name="child.icon" :filled="isActive(child)" class="text-xl transition-colors duration-150" />
                                            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ t(child.label) }}</span>
                                            <AppIcon name="expand_more" class="text-lg transition-transform duration-200" :class="expanded[child.key] && 'rotate-180'" />
                                        </button>
                                        <Link
                                            v-else-if="child.href"
                                            :href="child.href"
                                            class="sidebar-item is-child group relative flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors duration-150"
                                            :class="isActive(child)
                                                ? ''
                                                : 'text-zinc-400 hover:bg-white/[0.04] hover:text-zinc-100'"
                                            :data-sidebar-active="isActive(child) ? 'true' : null"
                                            @click="closeMobileMenu"
                                        >
                                            <span
                                                v-if="isActive(child)"
                                                class="sidebar-active-bar absolute inset-y-0 left-[-9px] w-1 rounded-full"
                                                aria-hidden="true"
                                            />
                                            <AppIcon :name="child.icon" :filled="isActive(child)" class="text-xl transition-colors duration-150" /><span class="text-sm font-semibold">{{ t(child.label) }}</span>
                                        </Link>
                                        <button v-else type="button" disabled class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-600" :title="`${t(child.label)} — ${t('Belum tersedia')}`"><AppIcon :name="child.icon" class="text-xl" /><span>{{ t(child.label) }}</span></button>

                                        <Transition name="sidebar-menu">
                                            <div v-if="child.children && expanded[child.key]" class="ml-5 space-y-1 overflow-hidden border-l border-white/10 pl-2">
                                                <template v-for="leaf in child.children" :key="leaf.label">
                                                    <Link
                                                        v-if="leaf.href"
                                                        :href="leaf.href"
                                                        class="sidebar-item is-child group relative flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors duration-150"
                                                        :class="isActive(leaf)
                                                            ? ''
                                                            : 'text-zinc-400 hover:bg-white/[0.04] hover:text-zinc-100'"
                                                        :data-sidebar-active="isActive(leaf) ? 'true' : null"
                                                        @click="closeMobileMenu"
                                                    >
                                                        <span
                                                            v-if="isActive(leaf)"
                                                            class="sidebar-active-bar absolute inset-y-0 left-[-9px] w-1 rounded-full"
                                                            aria-hidden="true"
                                                        />
                                                        <AppIcon v-if="leaf.icon" :name="leaf.icon" :filled="isActive(leaf)" class="text-base transition-colors duration-150" />
                                                        <span v-else class="size-1.5 rounded-full bg-current" />{{ t(leaf.label) }}
                                                    </Link>
                                                    <button v-else type="button" disabled class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-600" :title="`${t(leaf.label)} — ${t('Belum tersedia')}`"><span class="size-1.5 rounded-full bg-current" />{{ t(leaf.label) }}</button>
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
                    <h2 class="mb-1 px-4 text-[10px] font-bold uppercase tracking-[0.18em] text-zinc-500 transition-opacity duration-200" :class="sidebarCollapsed && !mobileMenuOpen ? 'lg:hidden' : 'lg:opacity-100'">{{ t('Platform') }}</h2>
                    <Link
                        v-for="item in platformNavigation"
                        :key="item.label"
                        :href="item.href"
                        class="sidebar-item group relative flex items-center gap-3 rounded-lg px-4 py-2.5 transition-colors duration-150"
                        :class="[isActive(item) ? '' : 'text-zinc-400 hover:bg-white/[0.04] hover:text-zinc-100', sidebarCollapsed && !mobileMenuOpen ? 'lg:justify-center lg:px-0' : '']"
                        :title="sidebarCollapsed && !mobileMenuOpen ? navLabel(item.label) : undefined"
                        :data-sidebar-active="isActive(item) ? 'true' : null"
                        @click="closeMobileMenu"
                    >
                        <span v-if="isActive(item)" class="sidebar-active-bar absolute inset-y-0 left-0 w-1 rounded-r-full" aria-hidden="true" />
                        <AppIcon :name="item.icon" :filled="isActive(item)" class="shrink-0 text-xl leading-none transition-colors duration-150" />
                        <span class="text-sm font-semibold transition-opacity duration-200" :class="sidebarCollapsed && !mobileMenuOpen ? 'lg:hidden' : 'lg:opacity-100'">{{ navLabel(item.label) }}</span>
                    </Link>
                </section>
            </nav>
        </aside>

        <header
            class="sticky top-0 z-30 flex h-16 items-center gap-2 header-shadow bg-surface/90 px-4 backdrop-blur transition-all duration-300 sm:gap-3 lg:px-6"
            :class="sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64'"
        >
            <button
                type="button"
                class="grid size-10 shrink-0 place-items-center rounded-lg text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary lg:hidden"
                :aria-label="t('Buka navigasi')"
                @click="mobileMenuOpen = true"
            >
                <AppIcon name="menu" class="text-2xl leading-none" />
            </button>
            <button
                type="button"
                class="flex min-w-0 flex-1 items-center gap-2.5 rounded-full bg-surface-container-low py-2 px-3 text-left text-sm text-on-surface-variant transition hover:bg-surface-container focus:outline-none focus:ring-2 focus:ring-primary-container/30 sm:max-w-md sm:gap-3"
                :aria-label="t('Buka pencarian')"
                @click="openSearch"
            >

                <AppIcon name="search" class="shrink-0 text-on-surface-variant" />
                <span class="min-w-0 flex-1 truncate">{{ t('Cari cepat...') }}</span>
                <kbd class="hidden rounded-md chip-shadow bg-surface px-1.5 py-0.5 text-[10px] font-semibold text-on-surface-variant ring-1 ring-outline-variant sm:inline">Ctrl K</kbd>
            </button>
            <p v-if="false" class="ml-2 hidden items-center gap-1.5 text-sm font-bold text-primary xl:flex"><AppIcon name="location_on" class="text-secondary" />{{ props.unitName }}</p>
            <div class="ml-auto flex shrink-0 items-center gap-0.5 pl-1 sm:gap-1 sm:pl-3">
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
                    <AppIcon name="history_edu" class="text-xl leading-none" />
                </Link>
                <NotificationDropdown />

                <!-- Profile pill -->
                <Link
                    href="/profile"
                    class="ml-1 flex shrink-0 items-center gap-2 rounded-full chip-shadow bg-surface-container-lowest py-1 pl-1 pr-3 ring-1 ring-outline-variant transition hover:bg-surface-container-low hover:ring-primary/40"
                    :aria-label="t('Buka Profil')"
                >
                    <span class="relative grid size-8 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-fixed text-xs font-bold text-primary"><img v-if="user?.photo_url && !avatarError" :src="user.photo_url" :alt="user?.name || t('User')" class="size-full object-cover" @error="avatarError = true" /><span v-else>{{ user?.name?.charAt(0).toUpperCase() || 'U' }}</span></span>
                    <span class="hidden min-w-0 flex-col leading-tight sm:flex">
                        <span class="truncate text-xs font-bold text-primary">{{ user?.name || t('Pengguna') }}</span>
                        <span class="truncate text-[10px] font-medium text-on-surface-variant">{{ props.unitName || t('Unit belum dipilih') }}</span>
                    </span>
                </Link>

                <AppLanguageSwitcher />
                <AppIconButton name="logout" tone="danger" size="md" rounded="full" :aria-label="t('Keluar')" class="shrink-0" @click="askLogout" />
            </div>
        </header>
        <main
            class="min-w-0 flex-1 p-4 transition-all duration-300 sm:p-6 lg:p-8"
            :class="sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64'"
        >
            <div class="mx-auto w-full max-w-7xl space-y-6">
                <div
                    v-if="impersonatedBy"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl chip-shadow bg-primary-container/30 px-4 py-3 text-sm text-primary ring-1 ring-primary/40"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-2.5">
                        <AppIcon name="admin_panel_settings" tone="primary" />
                        <p class="min-w-0 flex-1 break-words font-medium">
                            <span class="font-bold">{{ t('Mode Impersonasi Superadmin:') }}</span>
                            {{ t('Anda sedang mengakses tenant sebagai') }} <span class="font-bold">{{ user?.name }}</span> ({{ t('diinisiasi oleh Superadmin') }} <span class="font-bold">{{ impersonatorName || t('Superadmin') }}</span>).
                        </p>
                    </div>
                    <form class="shrink-0" @submit.prevent="leaveImpersonation">
                        <AppButton size="compact" variant="secondary" icon="logout" :loading="leaveForm.processing">
                            {{ t('Kembali ke Superadmin') }}
                        </AppButton>
                    </form>
                </div>
                <div
                    v-if="isTrainingMode"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl chip-shadow bg-warning-container/30 px-4 py-3 text-sm text-primary ring-1 ring-warning/40"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-2.5">
                        <span class="inline-block size-2.5 shrink-0 rounded-full bg-warning animate-pulse" />
                        <p class="min-w-0 flex-1 break-words font-medium">
                            <span class="font-bold">{{ t('Mode Pelatihan Aktif:') }}</span>
                            {{ t('Transaksi yang di-input pada periode ini adalah data simulasi latihan dan dapat dibersihkan oleh Superadmin.') }}
                        </p>
                    </div>
                </div>
                <Transition name="page" mode="out-in" appear>
                    <div :key="currentPath" class="min-w-0">
                        <slot />
                    </div>
                </Transition>
            </div>
        </main>
        <AssistantWidget v-if="assistantEnabled" />
        <AppConfirmDialog />
        <AppToast />

        <AppModal v-model="logoutOpen" :title="t('Keluar dari aplikasi?')" size="sm">
            <p class="text-sm text-on-surface-variant">
                {{ t('Sesi') }} <span class="font-semibold text-primary">{{ user?.name || t('Anda') }}</span> {{ t('akan diakhiri.') }}
                {{ t('Lanjutkan?') }}
            </p>
            <template #footer>
                <AppButton variant="secondary" :disabled="logoutForm.processing" @click="logoutOpen = false">{{ t('Batal') }}</AppButton>
                <AppButton variant="danger" icon="logout" :loading="logoutForm.processing" @click="logout">{{ t('Keluar') }}</AppButton>
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
                        :aria-label="t('Pencarian')"
                        class="flex max-h-[min(36rem,75vh)] w-full max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl floating-shadow bg-surface-container-lowest ring-1 ring-outline-variant/60 sm:max-w-3xl"
                        @keydown="onPaletteKeydown"
                    >
                        <div class="flex shrink-0 items-center gap-2 inset-divider px-3">
                            <AppIcon name="search" class="text-base text-on-surface-variant" />
                            <input
                                ref="searchInput"
                                v-model="searchQ"
                                type="search"
                                :placeholder="t('Cari cepat...')"
                                :aria-label="t('Kata kunci pencarian')"
                                class="h-14 min-w-0 flex-1 border-0 bg-transparent text-sm text-primary placeholder:text-on-surface-variant focus:outline-none focus:ring-0"
                                autocomplete="off"
                                @input="onSearchInput"
                            />
                            <AppIconButton
                                v-if="searchQ"
                                name="close"
                                tone="neutral"
                                rounded="full"
                                size="sm"
                                :aria-label="t('Hapus kata kunci')"
                                class="shrink-0 text-on-surface-variant hover:bg-surface-container-low hover:text-primary"
                                @click="clearSearchQuery"
                            />
                            <AppIconButton
                                v-else
                                name="close"
                                tone="neutral"
                                rounded="full"
                                size="sm"
                                :aria-label="t('Tutup pencarian')"
                                class="shrink-0 text-on-surface-variant hover:bg-surface-container-low hover:text-primary"
                                @click="closeSearch"
                            />
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto py-2" role="listbox">
                            <p v-if="searchQ.trim().length < 2" class="px-4 py-10 text-center text-sm text-on-surface-variant">
                                {{ t('Ketik minimal 2 karakter untuk mencari.') }}
                            </p>
                            <p v-else-if="searchLoading" class="px-4 py-10 text-center text-sm text-on-surface-variant">{{ t('Mencari...') }}</p>
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
                                {{ t('Tidak ada hasil untuk') }} "{{ searchQ.trim() }}".
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-3 inset-divider-t px-4 py-2 text-[11px] text-on-surface-variant">
                            <span><kbd class="rounded chip-shadow px-1 font-mono ring-1 ring-outline-variant/70">↑↓</kbd> {{ t('pilih') }}</span>
                            <span><kbd class="rounded chip-shadow px-1 font-mono ring-1 ring-outline-variant/70">Enter</kbd> {{ t('buka') }}</span>
                            <span><kbd class="rounded chip-shadow px-1 font-mono ring-1 ring-outline-variant/70">Esc</kbd> {{ t('tutup') }}</span>
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
