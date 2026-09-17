<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const mobileNavOpen = ref(false);

function smoothScrollTo(id) {
    mobileNavOpen.value = false;
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

const props = defineProps({
    organization: { type: Object, required: true },
    tenant: { type: Object, required: true },
    settings: { type: Object, default: () => ({}) },
});

const stats = computed(() => ([
    { value: props.organization.stat_total_members ?? '—', label: 'Anggota Aktif', icon: 'group', tone: 'primary' },
    { value: props.organization.stat_total_groups ?? '—', label: 'Kelompok', icon: 'workspaces', tone: 'secondary' },
    { value: props.organization.stat_villages ?? '—', label: 'Desa Layanan', icon: 'location_city', tone: 'tertiary' },
    { value: props.organization.stat_total_funding ?? '—', label: 'Total Penyaluran', icon: 'payments', tone: 'success' },
]));

const features = [
    { icon: 'savings', title: 'Dana Bergulir Terkelola', desc: 'Penyaluran, pengembalian, dan rotasi dana tercatat rapi mengikuti siklus PP No. 11/2021.' },
    { icon: 'rule', title: 'Patuh Regulasi', desc: 'Seluruh tata kelola merujuk pada PP No. 11/2021 tentang Pendirian & Pengelolaan BUMDesma.' },
    { icon: 'verified_user', title: 'Transparan & Akuntabel', desc: 'Laporan keuangan, berita acara, dan dokumentasi dapat diakses publik setiap saat.' },
    { icon: 'shield', title: 'Keamanan Data', desc: 'Enkripsi berlapis, kontrol akses peran, serta audit log untuk setiap transaksi sensitif.' },
    { icon: 'monitoring', title: 'Monitoring Real-time', desc: 'Pantau kolektabilitas, NPL, dan kinerja pelayanan langsung dari dashboard informatif.' },
    { icon: 'support_agent', title: 'Pendampingan', desc: 'Tim provinsi & kabupaten siap mendampingi pengurus BUMDesma di lapangan.' },
];

const quickStats = [
    { icon: 'trending_up', value: '+12,4%', label: 'Pertumbuhan Penyaluran' },
    { icon: 'percent', value: '97,2%', label: 'Kolektabilitas' },
    { icon: 'groups', value: '12.480', label: 'Penerima Manfaat' },
];

const modules = [
    { icon: 'how_to_reg', title: 'Verifikasi Anggota', desc: 'Form digital, validasi NIK, dan lampiran dokumen otomatis.' },
    { icon: 'request_quote', title: 'Pengajuan Kredit', desc: 'Alur berjenjang dari kelompok hingga pengurus dengan berita acara.' },
    { icon: 'account_balance_wallet', title: 'Pencairan & Angsuran', desc: 'Jadwal angsuran,监控 kolektabilitas, dan tanda terima digital.' },
    { icon: 'fact_check', title: 'Berita Acara', desc: 'Template BA tersinkronisasi dengan format kecamatan/kabupaten.' },
    { icon: 'analytics', title: 'Laporan Keuangan', desc: 'Neraca, laba-rugi, arus kas, dan CALK otomatis.' },
    { icon: 'inventory_2', title: 'Aset & Inventaris', desc: 'Manajemen aset tetap, inventaris, dan penyusutan otomatis.' },
];
</script>

<style scoped>
/* ==========================================================================
   Landing palette — consolidated tokens (hijau bertingkat, 4 token saja)
   Berlaku hanya untuk komponen ini, tidak mengotak-atik theme global.
   Token:
     --tenant-deep   (#052e1f)  // base forest, footer & headline dark stop
     --tenant-mid    (#0e5538)  // middle band
     --tenant-bright (#117a4a)  // primary highlight, CTA gradient stop
     --tenant-soft   (#65a30d)  // accent lime pop, used sparingly
   ========================================================================== */
:root {
    --tenant-deep: #052e1f;
    --tenant-mid: #0e5538;
    --tenant-bright: #117a4a;
    --tenant-soft: #65a30d;
}

.landing-topbar {
    background: linear-gradient(95deg, var(--tenant-deep) 0%, var(--tenant-mid) 45%, var(--tenant-bright) 100%);
    box-shadow: 0 2px 14px rgb(5 46 31 / 25%);
}

.landing-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
}

.landing-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -2;
    background:
        radial-gradient(120% 80% at 0% 0%, var(--tenant-mid) 0%, transparent 55%),
        radial-gradient(110% 70% at 100% 100%, var(--tenant-bright) 0%, transparent 60%),
        linear-gradient(135deg, var(--tenant-deep) 0%, var(--tenant-mid) 38%, #145c3f 68%, var(--tenant-bright) 100%);
}

.landing-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    opacity: 0.18;
    background-image:
        linear-gradient(115deg, transparent 0%, transparent 38%, rgb(132 204 22 / 22%) 38%, rgb(132 204 22 / 22%) 42%, transparent 42%),
        linear-gradient(115deg, transparent 0%, transparent 62%, rgb(16 185 129 / 28%) 62%, rgb(16 185 129 / 28%) 66%, transparent 66%);
    pointer-events: none;
}

.landing-hero h1,
.landing-hero .t-h1 {
    background: linear-gradient(120deg, var(--tenant-deep) 0%, var(--tenant-bright) 45%, var(--tenant-soft) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
}

.landing-hero .t-body-lg,
.landing-hero .text-on-surface-variant {
    color: #2d3f37 !important;
}

.landing-hero-card {
    background: linear-gradient(155deg, #ffffff 0%, #f3faf5 100%);
    border-color: rgb(16 185 129 / 22%);
}

.landing-hero-card .text-secondary {
    color: var(--tenant-bright) !important;
}

.landing-cta {
    background:
        radial-gradient(120% 90% at 0% 0%, var(--tenant-bright) 0%, transparent 60%),
        radial-gradient(120% 90% at 100% 100%, var(--tenant-soft) 0%, transparent 60%),
        linear-gradient(135deg, var(--tenant-deep) 0%, var(--tenant-mid) 35%, var(--tenant-bright) 70%, var(--tenant-soft) 100%) !important;
}

.landing-regulasi {
    background:
        radial-gradient(120% 80% at 100% 0%, var(--tenant-bright) 0%, transparent 60%),
        radial-gradient(120% 80% at 0% 100%, var(--tenant-mid) 0%, transparent 60%),
        linear-gradient(135deg, var(--tenant-deep) 0%, var(--tenant-mid) 50%, var(--tenant-mid) 100%);
}

.landing-dana-desa {
    margin: 0;
    line-height: 0.92;
    letter-spacing: -0.035em;
    font-weight: 900;
    font-style: italic;
    color: var(--tenant-deep);
    font-size: clamp(4.5rem, 12vw, 10rem);
}

.landing-badge-pill {
    background: linear-gradient(120deg, var(--tenant-bright) 0%, var(--tenant-soft) 100%) !important;
    box-shadow: 0 6px 18px rgb(17 122 74 / 30%);
}

/* Typography tokens — konsisten di seluruh landing */
.t-eyebrow { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; line-height: 1.2; }
.t-tagline { font-size: 0.8125rem; font-weight: 600; letter-spacing: 0.08em; line-height: 1.3; }
.t-nav { font-size: 0.875rem; font-weight: 600; line-height: 1.3; }
.t-body { font-size: 1rem; line-height: 1.7; font-weight: 400; }
.t-body-lg { font-size: 1.0625rem; line-height: 1.75; font-weight: 400; }
.t-card-title { font-size: 1.0625rem; font-weight: 700; line-height: 1.35; letter-spacing: -0.005em; }
.t-step-num { font-size: 0.875rem; font-weight: 800; line-height: 1; }
.t-stat-value { font-size: 1.625rem; font-weight: 800; line-height: 1.1; letter-spacing: -0.02em; }
.t-stat-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; line-height: 1.3; }
.t-section-eyebrow { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; line-height: 1.2; }
.t-section-title { font-size: 1.875rem; font-weight: 800; line-height: 1.15; letter-spacing: -0.02em; }
@media (min-width: 640px) { .t-section-title { font-size: 2.25rem; } }
@media (min-width: 1024px) { .t-section-title { font-size: 2.5rem; } }
.t-section-lead { font-size: 1rem; line-height: 1.7; font-weight: 400; }
@media (min-width: 640px) { .t-section-lead { font-size: 1.0625rem; } }
.t-h1 { font-size: 2.5rem; font-weight: 800; line-height: 1.05; letter-spacing: -0.03em; }
@media (min-width: 640px) { .t-h1 { font-size: 3.25rem; } }
@media (min-width: 1024px) { .t-h1 { font-size: 3.75rem; } }
@media (min-width: 1280px) { .t-h1 { font-size: 4.25rem; } }
.t-btn { font-size: 0.9375rem; font-weight: 700; line-height: 1; }

/* Container lebar ekstra besar untuk landing */
.container-landing {
    width: 100%;
    margin-inline: auto;
    max-width: 80rem;
    padding-inline: 1rem;
}
@media (min-width: 640px) { .container-landing { padding-inline: 1.5rem; } }
@media (min-width: 1024px) { .container-landing { padding-inline: 2rem; } }
@media (min-width: 1280px) { .container-landing { max-width: 90rem; } }

/* Animations */
@keyframes fade-up {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.anim-fade-up { animation: fade-up 0.5s ease-out both; }

/* Mobile drawer transition */
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 220ms ease, transform 220ms ease;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@media (prefers-reduced-motion: reduce) {
    .drawer-enter-active,
    .drawer-leave-active {
        transition: none;
    }
    .anim-fade-up {
        animation: none;
    }
}
</style>

<template>
    <Head :title="`${organization.name} — Sistem Tata Kelola Keuangan & Dana Bergulir BUMDesma LKD`">
        <meta head-key="description" name="description" :content="settings.hero_description ?? settings.about_short ?? `Situs resmi ${organization.name} — Sistem Tata Kelola Keuangan & Dana Bergulir BUMDesma LKD sesuai regulasi PP No. 11/2021.`" />
        <meta head-key="og:title" property="og:title" :content="`${organization.name} — Situs Resmi`" />
        <meta head-key="og:description" property="og:description" :content="settings.hero_description ?? settings.about_short ?? `Situs resmi ${organization.name} — pengelolaan dana bergulir masyarakat.`" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:url" property="og:url" :content="$page.url" />
        <meta v-if="organization.logo_url" head-key="og:image" property="og:image" :content="organization.logo_url" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="`${organization.name} — Situs Resmi`" />
        <meta head-key="twitter:description" name="twitter:description" :content="settings.hero_description ?? settings.about_short ?? `Situs resmi ${organization.name}.`" />
    </Head>

    <div class="flex min-h-screen flex-col bg-surface font-sans text-on-surface antialiased">
        <!-- TOP ANNOUNCEMENT BAR -->
        <div class="landing-topbar text-on-primary">
            <div class="container-landing flex items-center justify-between gap-4 py-2.5">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="grid size-7 shrink-0 place-items-center rounded-md bg-on-primary/15 backdrop-blur">
                        <AppIcon name="verified" class="text-base leading-none" />
                    </span>
                    <p class="t-tagline truncate uppercase">
                        Resmi · PP No. 11/2021 · Sistem Tata Kelola Dana Bergulir Nasional v2.6
                    </p>
                </div>
                <div class="hidden shrink-0 items-center gap-1 sm:flex">
                    <Link href="/berita" class="t-nav inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium text-on-primary/85 transition hover:bg-on-primary/10 hover:text-on-primary">
                        <AppIcon name="article" class="text-base leading-none" />Berita
                    </Link>
                    <Link href="/kontak" class="t-nav inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium text-on-primary/85 transition hover:bg-on-primary/10 hover:text-on-primary">
                        <AppIcon name="mail" class="text-base leading-none" />Kontak
                    </Link>
                    <Link href="/login" class="t-nav ml-1 inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-1.5 font-bold text-emerald-900 shadow-md transition hover:bg-emerald-50 hover:shadow-lg">
                        <AppIcon name="login" class="text-base leading-none" />Masuk Sistem
                    </Link>
                </div>
            </div>
        </div>

        <!-- MAIN HEADER -->
        <header class="sticky top-0 z-40 border-b border-outline-variant/40 bg-surface-container-lowest/90 backdrop-blur-xl">
            <div class="container-landing flex items-center justify-between gap-6 py-4">
                <Link href="/" class="flex min-w-0 items-center gap-3">
                    <div class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-2xl bg-emerald-900 shadow-md ring-1 ring-emerald-700/30">
                        <img v-if="organization.logo_url" :src="organization.logo_url" :alt="`Logo ${organization.name}`" class="size-full object-contain">
                        <span v-else class="text-xl font-extrabold leading-none text-white">{{ organization.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold leading-tight text-on-surface">{{ organization.name }}</p>
                        <p v-if="organization.regency_name || organization.district_name" class="mt-0.5 truncate text-xs leading-snug text-on-surface-variant">
                            {{ [organization.district_name, organization.regency_name].filter(Boolean).join(' · ') }}
                        </p>
                    </div>
                </Link>

                <nav class="hidden items-center gap-1 md:flex">
                    <Link href="/" class="rounded-full px-4 py-2 t-nav text-on-surface transition hover:bg-emerald-50 hover:text-emerald-900">Beranda</Link>
                    <Link href="#fitur" class="rounded-full px-4 py-2 t-nav text-on-surface-variant transition hover:bg-emerald-50 hover:text-emerald-900">Fitur</Link>
                    <Link href="#alur" class="rounded-full px-4 py-2 t-nav text-on-surface-variant transition hover:bg-emerald-50 hover:text-emerald-900">Alur</Link>
                    <Link href="#regulasi" class="rounded-full px-4 py-2 t-nav text-on-surface-variant transition hover:bg-emerald-50 hover:text-emerald-900">Regulasi</Link>
                    <Link href="/berita" class="rounded-full px-4 py-2 t-nav text-on-surface-variant transition hover:bg-emerald-50 hover:text-emerald-900">Berita</Link>
                    <Link href="/kontak" class="rounded-full px-4 py-2 t-nav text-on-surface-variant transition hover:bg-emerald-50 hover:text-emerald-900">Kontak</Link>
                    <Link href="/login" class="ml-3 inline-flex items-center gap-2 rounded-full bg-emerald-900 px-5 py-2.5 t-nav font-bold text-white shadow-md transition hover:bg-emerald-800 hover:shadow-lg">
                        <AppIcon name="login" class="text-base leading-none" />Masuk Sistem
                    </Link>
                </nav>

                <button
                    class="grid size-11 place-items-center rounded-xl bg-surface-container text-on-surface transition hover:bg-surface-container-high md:hidden"
                    :aria-label="mobileNavOpen ? 'Tutup menu' : 'Buka menu'"
                    @click="mobileNavOpen = !mobileNavOpen"
                >
                    <AppIcon :name="mobileNavOpen ? 'close' : 'menu'" class="text-2xl leading-none" />
                </button>
            </div>

            <!-- Mobile drawer -->
            <transition name="drawer">
                <nav v-if="mobileNavOpen" class="border-t border-emerald-900/15 bg-surface-container-lowest/95 backdrop-blur-xl md:hidden">
                    <ul class="container-landing flex flex-col gap-1 py-3">
                        <li>
                            <Link href="/" class="flex items-center justify-between rounded-lg px-3 py-2.5 t-nav text-on-surface transition hover:bg-emerald-50" @click="mobileNavOpen = false">Beranda<AppIcon name="chevron_right" class="text-base text-outline" /></Link>
                        </li>
                        <li>
                            <a href="#fitur" class="flex items-center justify-between rounded-lg px-3 py-2.5 t-nav text-on-surface-variant transition hover:bg-emerald-50" @click.prevent="smoothScrollTo('fitur')">Fitur<AppIcon name="chevron_right" class="text-base text-outline" /></a>
                        </li>
                        <li>
                            <a href="#alur" class="flex items-center justify-between rounded-lg px-3 py-2.5 t-nav text-on-surface-variant transition hover:bg-emerald-50" @click.prevent="smoothScrollTo('alur')">Alur<AppIcon name="chevron_right" class="text-base text-outline" /></a>
                        </li>
                        <li>
                            <a href="#regulasi" class="flex items-center justify-between rounded-lg px-3 py-2.5 t-nav text-on-surface-variant transition hover:bg-emerald-50" @click.prevent="smoothScrollTo('regulasi')">Regulasi<AppIcon name="chevron_right" class="text-base text-outline" /></a>
                        </li>
                        <li>
                            <Link href="/berita" class="flex items-center justify-between rounded-lg px-3 py-2.5 t-nav text-on-surface-variant transition hover:bg-emerald-50" @click="mobileNavOpen = false">Berita<AppIcon name="chevron_right" class="text-base text-outline" /></Link>
                        </li>
                        <li>
                            <Link href="/kontak" class="flex items-center justify-between rounded-lg px-3 py-2.5 t-nav text-on-surface-variant transition hover:bg-emerald-50" @click="mobileNavOpen = false">Kontak<AppIcon name="chevron_right" class="text-base text-outline" /></Link>
                        </li>
                        <li class="mt-2">
                            <Link href="/login" class="flex items-center justify-center gap-2 rounded-full bg-emerald-900 px-5 py-3 t-nav font-bold text-white shadow-md" @click="mobileNavOpen = false">
                                <AppIcon name="login" class="text-base leading-none" />Masuk Sistem
                            </Link>
                        </li>
                    </ul>
                </nav>
            </transition>
        </header>

        <main class="flex-1">
            <!-- HERO -->
            <section class="landing-hero">
                <div class="pointer-events-none absolute -top-40 right-[-15%] size-[32rem] rounded-full bg-emerald-400/25 blur-3xl" />
                <div class="pointer-events-none absolute bottom-[-20%] left-[-15%] size-[28rem] rounded-full bg-lime-400/25 blur-3xl" />
                <div class="pointer-events-none absolute inset-0 -z-10 opacity-[0.06]" style="background-image: radial-gradient(circle, rgb(255 255 255 / 60%) 1px, transparent 1px); background-size: 28px 28px;" />

                <div class="container-landing grid items-center gap-12 py-8 sm:gap-16 md:grid-cols-2 lg:grid-cols-12 lg:gap-14 lg:py-10">
                    <!-- Left: copy -->
                    <div class="lg:col-span-6 anim-fade-up">
                        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-500/40 bg-emerald-50 px-4 py-1.5 t-eyebrow text-emerald-900 backdrop-blur">
                            <AppIcon name="verified" class="text-base leading-none" />
                            {{ settings.hero_tagline ?? 'Resmi · PP No. 11/2021 · Sistem Tata Kelola Dana Bergulir Nasional v2.6' }}
                        </div>

                        <h1 class="mt-6 t-h1 text-on-surface">
                            {{ organization.legal_name }}
                        </h1>

                        <p class="mt-6 max-w-xl t-body-lg text-on-surface-variant">
                            {{ settings.hero_description ?? `Sistem Tata Kelola Dana Bergulir Nasional versi 2.6 — transparan, akuntabel, dan modern; dirancang melayani masyarakat sesuai regulasi PP No. 11/2021.` }}
                        </p>

                        <div class="mt-9 flex flex-wrap items-center gap-3">
                            <Link href="/login" class="landing-badge-pill inline-flex min-h-12 items-center gap-2 rounded-full bg-primary px-7 t-btn text-on-primary shadow-lg shadow-primary/30 transition hover:opacity-95 hover:shadow-xl hover:-translate-y-0.5">
                                <AppIcon name="apartment" class="text-lg leading-none" />
                                Portal Pengelolaan Keuangan
                            </Link>
                            <a v-if="organization.phone" :href="`tel:${organization.phone}`" class="inline-flex min-h-12 items-center gap-2 rounded-full border border-emerald-700/40 bg-surface-container-lowest/85 px-6 t-nav font-semibold text-on-surface backdrop-blur transition hover:border-emerald-700/60 hover:bg-surface-container">
                                <AppIcon name="call" class="text-lg leading-none" />
                                Hubungi Kami
                            </a>
                        </div>

                        <!-- Trust badges -->
                        <div class="mt-10 flex flex-wrap items-center gap-x-7 gap-y-3">
                            <div class="flex items-center gap-2 text-on-surface-variant">
                                <span class="grid size-7 place-items-center rounded-full bg-emerald-900 text-white">
                                    <AppIcon name="check" class="text-base leading-none" />
                                </span>
                                <span class="text-sm font-semibold">Patuh PP No. 11/2021</span>
                            </div>
                            <div class="flex items-center gap-2 text-on-surface-variant">
                                <span class="grid size-7 place-items-center rounded-full bg-emerald-900 text-white">
                                    <AppIcon name="check" class="text-base leading-none" />
                                </span>
                                <span class="text-sm font-semibold">Enkripsi &amp; Audit Log</span>
                            </div>
                            <div class="flex items-center gap-2 text-on-surface-variant">
                                <span class="grid size-7 place-items-center rounded-full bg-emerald-900 text-white">
                                    <AppIcon name="check" class="text-base leading-none" />
                                </span>
                                <span class="text-sm font-semibold">Akses Cloud 24/7</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: dashboard mockup -->
                    <div class="relative lg:col-span-6">
                        <div class="relative mx-auto max-w-xl">
                            <!-- Decorative blobs -->
                            <div class="absolute -top-8 -right-8 size-28 rounded-3xl bg-emerald-700/40 blur-2xl" />
                            <div class="absolute -bottom-10 -left-10 size-36 rounded-full bg-emerald-900/25 blur-2xl" />

                            <!-- Main card -->
                            <div class="landing-hero-card relative overflow-hidden rounded-3xl border border-outline-variant/40 bg-surface-container-lowest shadow-2xl">
                                <!-- Title bar -->
                                <div class="flex items-center justify-between border-b border-outline-variant/40 bg-surface-container-low px-5 py-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="size-2.5 rounded-full bg-emerald-900/70" />
                                        <span class="size-2.5 rounded-full bg-emerald-700/70" />
                                        <span class="size-2.5 rounded-full bg-emerald-500/70" />
                                    </div>
                                    <span class="t-eyebrow text-on-surface-variant">siupk.next / dashboard</span>
                                    <span class="size-5" />
                                </div>

                                <!-- Body -->
                                <div class="space-y-5 p-6">
                                    <!-- KPI row -->
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                        <div v-for="(k, i) in quickStats" :key="i" class="rounded-2xl border border-emerald-700/20 bg-surface-container-lowest/50 p-3.5">
                                            <AppIcon :name="k.icon" class="text-xl leading-none text-emerald-900" />
                                            <p class="mt-2 text-lg font-extrabold leading-tight text-on-surface">{{ k.value }}</p>
                                            <p class="mt-0.5 text-[10px] font-medium leading-tight text-on-surface-variant">{{ k.label }}</p>
                                        </div>
                                    </div>

                                    <!-- Chart placeholder -->
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <p class="t-eyebrow text-on-surface-variant">Penyaluran Bulanan</p>
                                            <span class="text-[11px] font-bold text-emerald-800">+12,4% YoY</span>
                                        </div>
                                        <div class="mt-3 flex h-32 items-end gap-2">
                                            <div v-for="(h, i) in [55, 38, 70, 48, 82, 64, 92, 76]" :key="i" class="flex-1 rounded-t-lg bg-gradient-to-t from-emerald-900 to-emerald-500 transition-all hover:opacity-80" :style="{ height: h + '%' }" />
                                        </div>
                                        <div class="mt-2 flex justify-between text-[10px] font-medium text-on-surface-variant">
                                            <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>Mei</span><span>Jun</span><span>Jul</span><span>Agu</span>
                                        </div>
                                    </div>

                                    <!-- Activity -->
                                    <div class="space-y-2.5 border-t border-outline-variant/40 pt-4">
                                        <div v-for="(item, i) in [
                                            { color: 'bg-emerald-500', label: 'Penyaluran Kelompok Maju Bersama', amount: '+Rp 25.000.000' },
                                            { color: 'bg-emerald-700', label: 'Angsuran Kelompok Lestari', amount: '+Rp 12.500.000' },
                                            { color: 'bg-emerald-900', label: 'Verifikasi SPK Selesai', amount: '7 dokumen' },
                                        ]" :key="i" class="flex items-center gap-3">
                                            <span class="size-2 shrink-0 rounded-full" :class="item.color" />
                                            <span class="flex-1 truncate text-xs font-medium text-on-surface">{{ item.label }}</span>
                                            <span class="text-xs font-bold text-on-surface">{{ item.amount }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Floating notification -->
                            <div class="absolute -bottom-5 -left-5 hidden w-60 rounded-2xl border border-emerald-500/30 bg-surface-container-lowest p-4 shadow-xl sm:block">
                                <div class="flex items-center gap-2.5">
                                    <AppIcon name="verified_user" tone="success" containerShape="pill" containerSize="9" />
                                    <p class="text-xs font-bold leading-tight text-on-surface">Berita Acara Tersimpan</p>
                                </div>
                                <p class="mt-2 text-[11px] leading-relaxed text-on-surface-variant">
                                    Penyaluran tahap II telah diverifikasi pengurus &amp; pendamping kecamatan.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- STATS BAND -->
            <section class="relative border-y border-emerald-900/15 bg-white">
                <div class="container-landing grid divide-emerald-900/10 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 lg:divide-x">
                    <div v-for="(stat, idx) in stats" :key="idx" class="flex items-center gap-4 px-2 py-8 lg:px-6">
                        <span class="grid size-14 shrink-0 place-items-center rounded-full bg-emerald-900 text-white">
                            <AppIcon :name="stat.icon" class="text-2xl leading-none" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-3xl font-extrabold leading-none tracking-tight text-on-surface">{{ stat.value }}</p>
                            <p class="mt-1.5 t-stat-label text-on-surface-variant">{{ stat.label }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- FITUR UTAMA -->
            <section id="fitur" class="relative py-24 sm:py-32">
                <div class="container-landing">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-900 px-4 py-1.5 t-section-eyebrow text-white">
                            <AppIcon name="auto_awesome" class="text-base leading-none" />Fitur Unggulan
                        </span>
                        <h2 class="mt-5 t-section-title text-on-surface">
                            Satu platform untuk seluruh siklus tata kelola
                        </h2>
                        <p class="mx-auto mt-5 max-w-2xl t-section-lead text-on-surface-variant">
                            Setiap modul dikembangkan bersama praktisi koperasi &amp; regulasi desa agar proses berjalan ringan, terukur, dan terdokumentasi.
                        </p>
                    </div>

                    <div class="mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <article v-for="(feature, idx) in features" :key="idx" class="group relative flex flex-col overflow-hidden rounded-3xl border border-emerald-900/15 bg-white p-8 shadow-sm transition hover:-translate-y-1 hover:border-emerald-700/40 hover:shadow-xl">
                            <div class="absolute -right-16 -top-16 size-40 rounded-full bg-emerald-900/5 transition group-hover:bg-emerald-900/10" />
                            <div class="relative">
                                <AppIcon :name="feature.icon" class="text-3xl leading-none text-emerald-900" />
                                <h3 class="mt-6 t-card-title text-on-surface">{{ feature.title }}</h3>
                                <p class="mt-3 text-sm leading-relaxed text-on-surface-variant">{{ feature.desc }}</p>
                            </div>
                            <div class="relative mt-8 inline-flex items-center gap-1.5 text-sm font-bold text-emerald-900">
                                Pelajari
                                <AppIcon name="arrow_forward" class="text-base leading-none transition group-hover:translate-x-1" />
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <!-- ALUR LAYANAN -->
            <section id="alur" class="relative bg-surface-container-low/40 py-24 sm:py-32">
                <div class="container-landing">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-900 px-4 py-1.5 t-section-eyebrow text-white">
                            <AppIcon name="route" class="text-base leading-none" />Alur Layanan
                        </span>
                        <h2 class="mt-5 t-section-title text-on-surface">
                            Dari musyawarah desa hingga pelaporan
                        </h2>
                        <p class="mx-auto mt-5 max-w-2xl t-section-lead text-on-surface-variant">
                            Empat langkah sederhana yang merangkum keseluruhan proses layanan keuangan &amp; dana bergulir.
                        </p>
                    </div>

                    <ol class="relative mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                        <li v-for="(step, idx) in [
                            { icon: 'forum', title: 'Musyawarah Desa', desc: 'Verifikasi calon anggota & rencana kebutuhan oleh pengurus & LKD.' },
                            { icon: 'fact_check', title: 'Verifikasi & SPK', desc: 'Pemeriksaan data, berita acara, dan penandatanganan perjanjian.' },
                            { icon: 'savings', title: 'Penyaluran & Angsuran', desc: 'Pencairan, jadwal angsuran, dan监控 kolektabilitas real-time.' },
                            { icon: 'assignment_turned_in', title: 'Pelaporan', desc: 'Laporan keuangan, berita acara, dan dokumentasi tersimpan otomatis.' },
                        ]" :key="idx" class="relative rounded-3xl border border-emerald-900/15 bg-white p-7 shadow-sm">
                            <div class="absolute -top-4 left-7 grid size-10 place-items-center rounded-2xl bg-emerald-900 t-step-num text-white shadow-lg shadow-emerald-900/30">
                                {{ idx + 1 }}
                            </div>
                            <div class="mt-3">
                                <AppIcon :name="step.icon" class="text-3xl leading-none text-emerald-900" />
                                <h3 class="mt-5 t-card-title text-on-surface">{{ step.title }}</h3>
                                <p class="mt-3 text-sm leading-relaxed text-on-surface-variant">{{ step.desc }}</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- REGULASI -->
            <section id="regulasi" class="landing-regulasi relative isolate overflow-hidden py-24 sm:py-28">
                <div class="pointer-events-none absolute -top-32 left-1/3 size-96 rounded-full bg-white/8 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-32 right-1/4 size-96 rounded-full bg-white/8 blur-3xl" />

                <div class="container-landing text-center">
                    <p class="landing-dana-desa">Dana desa</p>
                </div>
            </section>

            <!-- MODUL LENGKAP -->
            <section class="py-24 sm:py-32">
                <div class="container-landing">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-900 px-4 py-1.5 t-section-eyebrow text-white">
                            <AppIcon name="apps" class="text-base leading-none" />Modul Lengkap
                        </span>
                        <h2 class="mt-5 t-section-title text-on-surface">
                            Modul yang siap pakai sejak hari pertama
                        </h2>
                        <p class="mx-auto mt-5 max-w-2xl t-section-lead text-on-surface-variant">
                            Dari verifikasi anggota hingga laporan keuangan tahunan, semua kebutuhan operasional BUMDesma tersedia dalam satu sistem.
                        </p>
                    </div>

                    <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="(m, idx) in modules" :key="idx" class="flex gap-4 rounded-2xl border border-emerald-900/15 bg-white p-6 transition hover:border-emerald-700/40 hover:bg-emerald-50/40">
                            <div class="shrink-0">
                                <AppIcon :name="m.icon" class="text-3xl leading-none text-emerald-900" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="t-card-title text-on-surface">{{ m.title }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-on-surface-variant">{{ m.desc }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TENTANG & KONTAK -->
            <section class="border-t border-emerald-900/15 bg-emerald-50/30 py-24 sm:py-28">
                <div class="container-landing">
                    <div v-if="settings.about_short" class="mb-8 rounded-3xl border border-emerald-900/15 bg-white p-8 shadow-sm">
                        <div class="flex flex-col items-start gap-5 sm:flex-row sm:gap-6">
                            <span class="grid size-16 shrink-0 place-items-center rounded-full bg-emerald-900 text-white">
                                <AppIcon name="info" class="text-3xl leading-none" />
                            </span>
                            <div class="flex-1">
                                <h2 class="text-xl font-bold tracking-tight text-on-surface">Tentang {{ organization.name }}</h2>
                                <p class="mt-3 t-body text-on-surface-variant">{{ settings.about_short }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3">
                        <div class="rounded-3xl border border-emerald-900/15 bg-white p-7 shadow-sm">
                            <span class="grid size-14 shrink-0 place-items-center rounded-full bg-emerald-900 text-white">
                                <AppIcon name="place" class="text-2xl leading-none" />
                            </span>
                            <h2 class="mt-4 t-card-title text-on-surface">Alamat Sekretariat</h2>
                            <p v-if="organization.address" class="mt-3 text-sm leading-relaxed text-on-surface-variant">{{ organization.address }}</p>
                            <p v-else class="mt-3 text-sm italic text-on-surface-variant">Alamat belum dipublikasikan.</p>
                        </div>

                        <div class="rounded-3xl border border-emerald-900/15 bg-white p-7 shadow-sm">
                            <span class="grid size-14 shrink-0 place-items-center rounded-full bg-emerald-900 text-white">
                                <AppIcon name="contact_support" class="text-2xl leading-none" />
                            </span>
                            <h2 class="mt-4 t-card-title text-on-surface">Hubungi Kami</h2>
                            <ul class="mt-4 space-y-3 text-sm">
                                <li v-if="organization.phone" class="flex items-center gap-3 text-on-surface">
                                    <span class="grid size-9 place-items-center rounded-full bg-emerald-900 text-white"><AppIcon name="call" class="text-base leading-none" /></span>
                                    <span class="font-medium">{{ organization.phone }}</span>
                                </li>
                                <li v-if="organization.email" class="flex items-center gap-3 text-on-surface">
                                    <span class="grid size-9 place-items-center rounded-full bg-emerald-900 text-white"><AppIcon name="mail" class="text-base leading-none" /></span>
                                    <span class="font-medium">{{ organization.email }}</span>
                                </li>
                                <li v-if="organization.website" class="flex items-center gap-3 text-on-surface">
                                    <span class="grid size-9 place-items-center rounded-full bg-emerald-900 text-white"><AppIcon name="language" class="text-base leading-none" /></span>
                                    <span class="font-medium">{{ organization.website }}</span>
                                </li>
                                <li v-if="!organization.phone && !organization.email && !organization.website" class="italic text-on-surface-variant">
                                    Kanal kontak belum dipublikasikan.
                                </li>
                            </ul>
                        </div>

                        <div class="rounded-3xl border border-emerald-900/15 bg-white p-7 shadow-sm">
                            <span class="grid size-14 shrink-0 place-items-center rounded-full bg-emerald-900 text-white">
                                <AppIcon name="history" class="text-2xl leading-none" />
                            </span>
                            <h2 class="mt-4 t-card-title text-on-surface">Berdiri Sejak</h2>
                            <p v-if="organization.operational_start_year" class="mt-3 text-sm leading-relaxed text-on-surface-variant">
                                Sejak <span class="font-bold text-on-surface">{{ organization.operational_start_year }}</span> melayani tata kelola keuangan &amp; dana bergulir masyarakat.
                            </p>
                            <p v-else class="mt-3 text-sm italic text-on-surface-variant">Informasi tahun berdiri belum tersedia.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="py-24">
                <div class="container-landing">
                    <div class="landing-cta relative isolate overflow-hidden rounded-[2rem] bg-gradient-to-br from-primary via-primary-deep to-primary-container px-8 py-16 text-on-primary shadow-2xl sm:px-14 sm:py-20">
                        <div class="pointer-events-none absolute -top-32 right-0 size-96 rounded-full bg-on-primary/10 blur-3xl" />
                        <div class="pointer-events-none absolute -bottom-32 left-0 size-96 rounded-full bg-on-primary/10 blur-3xl" />

                        <div class="relative grid items-center gap-10 lg:grid-cols-2">
                            <div>
                                <span class="inline-flex items-center gap-2 rounded-full bg-on-primary/15 px-3 py-1.5 t-eyebrow backdrop-blur">
                                    <AppIcon name="rocket_launch" class="text-base leading-none" />Mulai Sekarang
                                </span>
                                <h2 class="mt-5 t-section-title text-on-primary">
                                    Siap mengelola BUMDesma secara modern?
                                </h2>
                                <p class="mt-5 max-w-xl t-body-lg text-on-primary/85">
                                    Masuk ke portal sistem untuk mengelola data anggota, kelompok, penyaluran, dan laporan — semuanya dalam satu tempat.
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center justify-start gap-3 lg:justify-end">
                                <Link href="/login" class="inline-flex min-h-12 items-center gap-2 rounded-full bg-white px-7 t-btn text-emerald-900 shadow-lg transition hover:bg-emerald-50 hover:-translate-y-0.5">
                                    <AppIcon name="login" class="text-lg leading-none" />Masuk Portal
                                </Link>
                                <Link href="/kontak" class="inline-flex min-h-12 items-center gap-2 rounded-full border border-white/40 px-6 t-nav font-bold text-white transition hover:bg-white/10">
                                    <AppIcon name="support_agent" class="text-lg leading-none" />Konsultasi Gratis
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- FOOTER -->
        <footer class="border-t border-emerald-900/20 bg-white">
            <div class="container-landing py-16">
                <div class="grid gap-12 sm:grid-cols-2 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-3">
                            <div class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-2xl bg-emerald-900 shadow-md ring-1 ring-emerald-700/30">
                                <img v-if="organization.logo_url" :src="organization.logo_url" :alt="`Logo ${organization.name}`" class="size-full object-contain">
                                <span v-else class="text-xl font-extrabold leading-none text-white">{{ organization.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-base font-bold leading-tight text-on-surface">{{ organization.name }}</p>
                                <p v-if="organization.regency_name" class="mt-0.5 truncate text-xs leading-snug text-on-surface-variant">{{ organization.regency_name }}</p>
                            </div>
                        </div>
                        <p v-if="settings.about_short" class="mt-5 max-w-md text-sm leading-relaxed text-on-surface-variant">{{ settings.about_short }}</p>
                    </div>

                    <div>
                        <p class="t-eyebrow text-emerald-900">Tautan</p>
                        <ul class="mt-5 space-y-3 text-sm">
                            <li><Link href="/" class="text-on-surface-variant transition hover:text-emerald-900">Beranda</Link></li>
                            <li><Link href="#fitur" class="text-on-surface-variant transition hover:text-emerald-900">Fitur</Link></li>
                            <li><Link href="#alur" class="text-on-surface-variant transition hover:text-emerald-900">Alur</Link></li>
                            <li><Link href="#regulasi" class="text-on-surface-variant transition hover:text-emerald-900">Regulasi</Link></li>
                            <li><Link href="/berita" class="text-on-surface-variant transition hover:text-emerald-900">Berita</Link></li>
                            <li><Link href="/kontak" class="text-on-surface-variant transition hover:text-emerald-900">Kontak</Link></li>
                        </ul>
                    </div>

                    <div>
                        <p class="t-eyebrow text-emerald-900">Kontak</p>
                        <ul class="mt-5 space-y-3 text-sm text-on-surface-variant">
                            <li v-if="organization.phone" class="flex items-start gap-2.5"><AppIcon name="call" class="text-base leading-none text-emerald-900" /> {{ organization.phone }}</li>
                            <li v-if="organization.email" class="flex items-start gap-2.5"><AppIcon name="mail" class="text-base leading-none text-emerald-900" /> {{ organization.email }}</li>
                            <li v-if="organization.address" class="flex items-start gap-2.5"><AppIcon name="place" class="text-base leading-none text-emerald-900" /> <span>{{ organization.address }}</span></li>
                        </ul>
                        <div v-if="settings.social?.facebook || settings.social?.instagram || settings.social?.youtube" class="mt-5 flex gap-2">
                            <a v-if="settings.social?.facebook" :href="settings.social.facebook" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-full bg-emerald-50 text-emerald-900 transition hover:bg-emerald-900 hover:text-white" aria-label="Facebook"><AppIcon name="facebook" class="text-base leading-none" /></a>
                            <a v-if="settings.social?.instagram" :href="settings.social.instagram" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-full bg-emerald-50 text-emerald-900 transition hover:bg-emerald-900 hover:text-white" aria-label="Instagram"><AppIcon name="photo_camera" class="text-base leading-none" /></a>
                            <a v-if="settings.social?.youtube" :href="settings.social.youtube" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-full bg-emerald-50 text-emerald-900 transition hover:bg-emerald-900 hover:text-white" aria-label="YouTube"><AppIcon name="play_arrow" class="text-base leading-none" /></a>
                        </div>
                    </div>
                </div>

                <div class="mt-12 flex flex-col items-center justify-between gap-2 pt-6 text-center sm:flex-row sm:text-left">
                    <p class="text-xs text-on-surface-variant">
                        © {{ new Date().getFullYear() }} <span class="font-semibold text-on-surface">siupk Next</span> · Sistem Tata Kelola Dana Bergulir Nasional v2.6
                    </p>
                    <p v-if="settings.footer_note" class="text-xs text-on-surface-variant">{{ settings.footer_note }}</p>
                </div>
            </div>
        </footer>
    </div>
</template>
