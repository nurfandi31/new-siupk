<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

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

const principles = [
    { icon: 'handshake', title: 'Kehati-hatian', desc: 'Prudent lending berdasarkan musyawarah & verifikasi lapangan.' },
    { icon: 'balance', title: 'Keadilan', desc: 'Pelayanan merata untuk seluruh desa & kelompok anggota.' },
    { icon: 'visibility', title: 'Keterbukaan', desc: 'Setiap keputusan dapat ditelusuri oleh anggota & pemerintah desa.' },
    { icon: 'workspace_premium', title: 'Profesional', desc: 'Standar operasional prosedur yang konsisten & terdokumentasi.' },
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

/* Animations */
@keyframes fade-up {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.anim-fade-up { animation: fade-up 0.5s ease-out both; }
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
        <div class="bg-primary-deep text-on-primary">
            <div class="container-landing flex items-center justify-between gap-4 py-2.5">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="grid size-7 shrink-0 place-items-center rounded-md bg-on-primary/15 backdrop-blur">
                        <AppIcon name="verified" class="text-base leading-none" />
                    </span>
                    <p class="t-tagline truncate uppercase">
                        Sistem Tata Kelola Keuangan &amp; Dana Bergulir BUMDesma LKD Sesuai Regulasi PP No. 11/2021
                    </p>
                </div>
                <div class="hidden shrink-0 items-center gap-1 sm:flex">
                    <Link href="/berita" class="t-nav inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium text-on-primary/85 transition hover:bg-on-primary/10 hover:text-on-primary">
                        <AppIcon name="article" class="text-base leading-none" />Berita
                    </Link>
                    <Link href="/kontak" class="t-nav inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium text-on-primary/85 transition hover:bg-on-primary/10 hover:text-on-primary">
                        <AppIcon name="mail" class="text-base leading-none" />Kontak
                    </Link>
                    <Link href="/login" class="t-nav ml-1 inline-flex items-center gap-1.5 rounded-full bg-on-primary px-4 py-1.5 font-bold text-primary shadow-md transition hover:bg-primary-fixed hover:shadow-lg">
                        <AppIcon name="login" class="text-base leading-none" />Masuk Sistem
                    </Link>
                </div>
            </div>
        </div>

        <!-- MAIN HEADER -->
        <header class="sticky top-0 z-40 border-b border-outline-variant/40 bg-surface-container-lowest/90 backdrop-blur-xl">
            <div class="container-landing flex items-center justify-between gap-6 py-4">
                <Link href="/" class="flex min-w-0 items-center gap-3">
                    <div class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-2xl bg-primary-container shadow-md ring-1 ring-primary/20">
                        <img v-if="organization.logo_url" :src="organization.logo_url" :alt="`Logo ${organization.name}`" class="size-full object-contain">
                        <span v-else class="text-xl font-extrabold leading-none text-on-primary-container">{{ organization.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold leading-tight text-on-surface">{{ organization.name }}</p>
                        <p v-if="organization.regency_name || organization.district_name" class="mt-0.5 truncate text-xs leading-snug text-on-surface-variant">
                            {{ [organization.district_name, organization.regency_name].filter(Boolean).join(' · ') }}
                        </p>
                    </div>
                </Link>

                <nav class="hidden items-center gap-1 md:flex">
                    <Link href="/" class="rounded-full px-4 py-2 t-nav text-on-surface transition hover:bg-surface-container">Beranda</Link>
                    <Link href="#fitur" class="rounded-full px-4 py-2 t-nav text-on-surface-variant transition hover:bg-surface-container hover:text-on-surface">Fitur</Link>
                    <Link href="#alur" class="rounded-full px-4 py-2 t-nav text-on-surface-variant transition hover:bg-surface-container hover:text-on-surface">Alur</Link>
                    <Link href="#regulasi" class="rounded-full px-4 py-2 t-nav text-on-surface-variant transition hover:bg-surface-container hover:text-on-surface">Regulasi</Link>
                    <Link href="/berita" class="rounded-full px-4 py-2 t-nav text-on-surface-variant transition hover:bg-surface-container hover:text-on-surface">Berita</Link>
                    <Link href="/kontak" class="rounded-full px-4 py-2 t-nav text-on-surface-variant transition hover:bg-surface-container hover:text-on-surface">Kontak</Link>
                    <Link href="/login" class="ml-3 inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 t-nav font-bold text-on-primary shadow-md transition hover:bg-primary-deep hover:shadow-lg">
                        <AppIcon name="login" class="text-base leading-none" />Masuk Sistem
                    </Link>
                </nav>

                <button class="grid size-11 place-items-center rounded-xl bg-surface-container text-on-surface transition hover:bg-surface-container-high md:hidden" aria-label="Menu">
                    <AppIcon name="menu" class="text-2xl leading-none" />
                </button>
            </div>
        </header>

        <main class="flex-1">
            <!-- HERO -->
            <section class="relative isolate overflow-hidden">
                <div class="absolute inset-0 -z-10 bg-gradient-to-br from-surface via-primary-container/30 to-surface" />
                <div class="pointer-events-none absolute -top-40 right-[-15%] size-[32rem] rounded-full bg-primary/15 blur-3xl" />
                <div class="pointer-events-none absolute bottom-[-20%] left-[-15%] size-[28rem] rounded-full bg-secondary/15 blur-3xl" />
                <div class="pointer-events-none absolute inset-0 -z-10 opacity-[0.035]" style="background-image: radial-gradient(circle, currentColor 1px, transparent 1px); background-size: 28px 28px;" />

                <div class="container-landing grid items-center gap-16 py-8 lg:grid-cols-12 lg:gap-14 lg:py-10">
                    <!-- Left: copy -->
                    <div class="lg:col-span-6 anim-fade-up">
                        <div class="inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary-container/60 px-4 py-1.5 t-eyebrow text-on-primary-container backdrop-blur">
                            <AppIcon name="verified" class="text-base leading-none" />
                            {{ settings.hero_tagline ?? 'Situs Resmi BUMDesma LKD' }}
                        </div>

                        <h1 class="mt-6 t-h1 text-on-surface">
                            {{ organization.legal_name }}
                        </h1>

                        <p class="mt-6 max-w-xl t-body-lg text-on-surface-variant">
                            {{ settings.hero_description ?? `Sistem Tata Kelola Keuangan & Dana Bergulir BUMDesma LKD yang transparan, akuntabel, dan modern — dirancang untuk melayani masyarakat sesuai regulasi PP No. 11/2021.` }}
                        </p>

                        <div class="mt-9 flex flex-wrap items-center gap-3">
                            <Link href="/login" class="inline-flex min-h-12 items-center gap-2 rounded-full bg-primary px-7 t-btn text-on-primary shadow-lg shadow-primary/30 transition hover:bg-primary-deep hover:shadow-xl hover:-translate-y-0.5">
                                <AppIcon name="apartment" class="text-lg leading-none" />
                                Portal Pengelolaan Keuangan
                            </Link>
                            <a v-if="organization.phone" :href="`tel:${organization.phone}`" class="inline-flex min-h-12 items-center gap-2 rounded-full border border-outline-variant/60 bg-surface-container-lowest/80 px-6 t-nav font-semibold text-on-surface backdrop-blur transition hover:border-primary/40 hover:bg-surface-container">
                                <AppIcon name="call" class="text-lg leading-none" />
                                Hubungi Kami
                            </a>
                        </div>

                        <!-- Trust badges -->
                        <div class="mt-10 flex flex-wrap items-center gap-x-7 gap-y-3">
                            <div class="flex items-center gap-2 text-on-surface-variant">
                                <span class="grid size-7 place-items-center rounded-full bg-secondary-container text-on-secondary">
                                    <AppIcon name="check" class="text-base leading-none" />
                                </span>
                                <span class="text-sm font-semibold">Patuh PP No. 11/2021</span>
                            </div>
                            <div class="flex items-center gap-2 text-on-surface-variant">
                                <span class="grid size-7 place-items-center rounded-full bg-secondary-container text-on-secondary">
                                    <AppIcon name="check" class="text-base leading-none" />
                                </span>
                                <span class="text-sm font-semibold">Enkripsi &amp; Audit Log</span>
                            </div>
                            <div class="flex items-center gap-2 text-on-surface-variant">
                                <span class="grid size-7 place-items-center rounded-full bg-secondary-container text-on-secondary">
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
                            <div class="absolute -top-8 -right-8 size-28 rounded-3xl bg-tertiary/40 blur-2xl" />
                            <div class="absolute -bottom-10 -left-10 size-36 rounded-full bg-primary/25 blur-2xl" />

                            <!-- Main card -->
                            <div class="relative overflow-hidden rounded-3xl border border-outline-variant/40 bg-surface-container-lowest shadow-2xl">
                                <!-- Title bar -->
                                <div class="flex items-center justify-between border-b border-outline-variant/40 bg-surface-container-low px-5 py-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="size-2.5 rounded-full bg-error/70" />
                                        <span class="size-2.5 rounded-full bg-tertiary/70" />
                                        <span class="size-2.5 rounded-full bg-secondary/70" />
                                    </div>
                                    <span class="t-eyebrow text-on-surface-variant">siupk.next / dashboard</span>
                                    <span class="size-5" />
                                </div>

                                <!-- Body -->
                                <div class="space-y-5 p-6">
                                    <!-- KPI row -->
                                    <div class="grid grid-cols-3 gap-3">
                                        <div v-for="(k, i) in quickStats" :key="i" class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest/50 p-3.5">
                                            <AppIcon :name="k.icon" tone="primary" class="text-xl leading-none" />
                                            <p class="mt-2 text-lg font-extrabold leading-tight text-on-surface">{{ k.value }}</p>
                                            <p class="mt-0.5 text-[10px] font-medium leading-tight text-on-surface-variant">{{ k.label }}</p>
                                        </div>
                                    </div>

                                    <!-- Chart placeholder -->
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <p class="t-eyebrow text-on-surface-variant">Penyaluran Bulanan</p>
                                            <span class="text-[11px] font-bold text-secondary">+12,4% YoY</span>
                                        </div>
                                        <div class="mt-3 flex h-32 items-end gap-2">
                                            <div v-for="(h, i) in [55, 38, 70, 48, 82, 64, 92, 76]" :key="i" class="flex-1 rounded-t-lg bg-gradient-to-t from-primary to-secondary transition-all hover:opacity-80" :style="{ height: h + '%' }" />
                                        </div>
                                        <div class="mt-2 flex justify-between text-[10px] font-medium text-on-surface-variant">
                                            <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>Mei</span><span>Jun</span><span>Jul</span><span>Agu</span>
                                        </div>
                                    </div>

                                    <!-- Activity -->
                                    <div class="space-y-2.5 border-t border-outline-variant/40 pt-4">
                                        <div v-for="(item, i) in [
                                            { color: 'bg-secondary', label: 'Penyaluran Kelompok Maju Bersama', amount: '+Rp 25.000.000' },
                                            { color: 'bg-tertiary', label: 'Angsuran Kelompok Lestari', amount: '+Rp 12.500.000' },
                                            { color: 'bg-primary', label: 'Verifikasi SPK Selesai', amount: '7 dokumen' },
                                        ]" :key="i" class="flex items-center gap-3">
                                            <span class="size-2 shrink-0 rounded-full" :class="item.color" />
                                            <span class="flex-1 truncate text-xs font-medium text-on-surface">{{ item.label }}</span>
                                            <span class="text-xs font-bold text-on-surface">{{ item.amount }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Floating notification -->
                            <div class="absolute -bottom-5 -left-5 hidden w-60 rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-4 shadow-xl sm:block">
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
            <section class="relative border-y border-outline-variant/40 bg-surface-container-lowest">
                <div class="container-landing grid divide-outline-variant/40 sm:grid-cols-2 lg:grid-cols-4 lg:divide-x">
                    <div v-for="(stat, idx) in stats" :key="idx" class="flex items-center gap-4 px-2 py-8 lg:px-6">
                        <AppIcon :name="stat.icon" :tone="stat.tone" containerShape="pill" container-size="14" />
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
                        <span class="inline-flex items-center gap-2 rounded-full bg-primary-container px-4 py-1.5 t-section-eyebrow text-on-primary-container">
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
                        <article v-for="(feature, idx) in features" :key="idx" class="group relative flex flex-col overflow-hidden rounded-3xl border border-outline-variant/40 bg-surface-container-lowest p-8 shadow-sm transition hover:-translate-y-1 hover:border-primary/40 hover:shadow-xl">
                            <div class="absolute -right-16 -top-16 size-40 rounded-full bg-primary/5 transition group-hover:bg-primary/10" />
                            <div class="relative">
                                <AppIcon :name="feature.icon" tone="primary" container-size="14" />
                                <h3 class="mt-6 t-card-title text-on-surface">{{ feature.title }}</h3>
                                <p class="mt-3 text-sm leading-relaxed text-on-surface-variant">{{ feature.desc }}</p>
                            </div>
                            <div class="relative mt-8 inline-flex items-center gap-1.5 text-sm font-bold text-primary">
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
                        <span class="inline-flex items-center gap-2 rounded-full bg-secondary-container px-4 py-1.5 t-section-eyebrow text-on-secondary">
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
                        ]" :key="idx" class="relative rounded-3xl border border-outline-variant/40 bg-surface-container-lowest p-7 shadow-sm">
                            <div class="absolute -top-4 left-7 grid size-10 place-items-center rounded-2xl bg-primary t-step-num text-on-primary shadow-lg shadow-primary/30">
                                {{ idx + 1 }}
                            </div>
                            <div class="mt-3">
                                <AppIcon :name="step.icon" tone="primary" container-size="12" />
                                <h3 class="mt-5 t-card-title text-on-surface">{{ step.title }}</h3>
                                <p class="mt-3 text-sm leading-relaxed text-on-surface-variant">{{ step.desc }}</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- REGULASI & PRINSIP -->
            <section id="regulasi" class="relative isolate overflow-hidden bg-primary-deep py-24 text-on-primary sm:py-28">
                <div class="pointer-events-none absolute -top-32 left-1/3 size-96 rounded-full bg-on-primary/8 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-32 right-1/4 size-96 rounded-full bg-on-primary/8 blur-3xl" />

                <div class="container-landing">
                    <div class="grid items-center gap-16 lg:grid-cols-2">
                        <div>
                            <span class="inline-flex items-center gap-2 rounded-full bg-on-primary/15 px-4 py-1.5 t-eyebrow backdrop-blur">
                                <AppIcon name="policy" class="text-base leading-none" />Regulasi
                            </span>
                            <h2 class="mt-5 t-section-title text-on-primary">
                                Patuh pada PP No. 11 Tahun 2021
                            </h2>
                            <p class="mt-5 max-w-xl t-body-lg text-on-primary/85">
                                Sistem dirancang mengikuti pedoman Pendirian &amp; Pengelolaan BUMDesma bersama LKD. Setiap proses — mulai dari musyawarah desa, verifikasi anggota, penyaluran, hingga pelaporan — terdokumentasi otomatis dan dapat ditelusuri.
                            </p>

                            <div class="mt-8 flex flex-wrap items-center gap-4">
                                <div class="flex items-center gap-3 rounded-2xl bg-on-primary/8 px-4 py-3 backdrop-blur">
                                    <AppIcon name="verified_user" class="text-2xl leading-none" />
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wider opacity-80">Standar</p>
                                        <p class="text-sm font-bold">PP No. 11/2021</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 rounded-2xl bg-on-primary/8 px-4 py-3 backdrop-blur">
                                    <AppIcon name="gavel" class="text-2xl leading-none" />
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wider opacity-80">Pendamping</p>
                                        <p class="text-sm font-bold">Provinsi &amp; Kabupaten</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div v-for="(p, idx) in principles" :key="idx" class="rounded-3xl border border-on-primary/15 bg-on-primary/5 p-6 backdrop-blur">
                                <AppIcon :name="p.icon" class="text-3xl leading-none" />
                                <h3 class="mt-4 t-card-title text-on-primary">{{ p.title }}</h3>
                                <p class="mt-2.5 text-sm leading-relaxed text-on-primary/80">{{ p.desc }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- MODUL LENGKAP -->
            <section class="py-24 sm:py-32">
                <div class="container-landing">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="inline-flex items-center gap-2 rounded-full bg-tertiary-fixed px-4 py-1.5 t-section-eyebrow text-tertiary">
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
                        <div v-for="(m, idx) in modules" :key="idx" class="flex gap-4 rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-6 transition hover:border-primary/40 hover:bg-surface-container">
                            <div class="shrink-0">
                                <AppIcon :name="m.icon" tone="primary" container-size="12" />
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
            <section class="border-t border-outline-variant/40 bg-surface-container-low/40 py-24 sm:py-28">
                <div class="container-landing">
                    <div v-if="settings.about_short" class="mb-8 rounded-3xl border border-outline-variant/40 bg-surface-container-lowest p-8 shadow-sm">
                        <div class="flex flex-col items-start gap-5 sm:flex-row sm:gap-6">
                            <AppIcon name="info" tone="primary" container-size="16" containerShape="pill" />
                            <div class="flex-1">
                                <h2 class="text-xl font-bold tracking-tight text-on-surface">Tentang {{ organization.name }}</h2>
                                <p class="mt-3 t-body text-on-surface-variant">{{ settings.about_short }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-3">
                        <div class="rounded-3xl border border-outline-variant/40 bg-surface-container-lowest p-7 shadow-sm">
                            <AppIcon name="place" tone="primary" container-size="14" containerShape="pill" />
                            <h2 class="mt-4 t-card-title text-on-surface">Alamat Sekretariat</h2>
                            <p v-if="organization.address" class="mt-3 text-sm leading-relaxed text-on-surface-variant">{{ organization.address }}</p>
                            <p v-else class="mt-3 text-sm italic text-on-surface-variant">Alamat belum dipublikasikan.</p>
                        </div>

                        <div class="rounded-3xl border border-outline-variant/40 bg-surface-container-lowest p-7 shadow-sm">
                            <AppIcon name="contact_support" tone="primary" container-size="14" containerShape="pill" />
                            <h2 class="mt-4 t-card-title text-on-surface">Hubungi Kami</h2>
                            <ul class="mt-4 space-y-3 text-sm">
                                <li v-if="organization.phone" class="flex items-center gap-3 text-on-surface">
                                    <span class="grid size-9 place-items-center rounded-full bg-primary-container text-on-primary-container"><AppIcon name="call" class="text-base leading-none" /></span>
                                    <span class="font-medium">{{ organization.phone }}</span>
                                </li>
                                <li v-if="organization.email" class="flex items-center gap-3 text-on-surface">
                                    <span class="grid size-9 place-items-center rounded-full bg-primary-container text-on-primary-container"><AppIcon name="mail" class="text-base leading-none" /></span>
                                    <span class="font-medium">{{ organization.email }}</span>
                                </li>
                                <li v-if="organization.website" class="flex items-center gap-3 text-on-surface">
                                    <span class="grid size-9 place-items-center rounded-full bg-primary-container text-on-primary-container"><AppIcon name="language" class="text-base leading-none" /></span>
                                    <span class="font-medium">{{ organization.website }}</span>
                                </li>
                                <li v-if="!organization.phone && !organization.email && !organization.website" class="italic text-on-surface-variant">
                                    Kanal kontak belum dipublikasikan.
                                </li>
                            </ul>
                        </div>

                        <div class="rounded-3xl border border-outline-variant/40 bg-surface-container-lowest p-7 shadow-sm">
                            <AppIcon name="history" tone="primary" container-size="14" containerShape="pill" />
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
                    <div class="relative isolate overflow-hidden rounded-[2rem] bg-gradient-to-br from-primary via-primary-deep to-primary-container px-8 py-16 text-on-primary shadow-2xl sm:px-14 sm:py-20">
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
                                <Link href="/login" class="inline-flex min-h-12 items-center gap-2 rounded-full bg-on-primary px-7 t-btn text-primary shadow-lg transition hover:bg-primary-fixed hover:-translate-y-0.5">
                                    <AppIcon name="login" class="text-lg leading-none" />Masuk Portal
                                </Link>
                                <Link href="/kontak" class="inline-flex min-h-12 items-center gap-2 rounded-full border border-on-primary/40 px-6 t-nav font-bold text-on-primary transition hover:bg-on-primary/10">
                                    <AppIcon name="support_agent" class="text-lg leading-none" />Konsultasi Gratis
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- FOOTER -->
        <footer class="border-t border-outline-variant/60 bg-surface-container-lowest">
            <div class="container-landing py-16">
                <div class="grid gap-12 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-3">
                            <div class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-2xl bg-primary-container shadow-md ring-1 ring-primary/20">
                                <img v-if="organization.logo_url" :src="organization.logo_url" :alt="`Logo ${organization.name}`" class="size-full object-contain">
                                <span v-else class="text-xl font-extrabold leading-none text-on-primary-container">{{ organization.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-base font-bold leading-tight text-on-surface">{{ organization.name }}</p>
                                <p v-if="organization.regency_name" class="mt-0.5 truncate text-xs leading-snug text-on-surface-variant">{{ organization.regency_name }}</p>
                            </div>
                        </div>
                        <p v-if="settings.about_short" class="mt-5 max-w-md text-sm leading-relaxed text-on-surface-variant">{{ settings.about_short }}</p>
                    </div>

                    <div>
                        <p class="t-eyebrow text-on-surface">Tautan</p>
                        <ul class="mt-5 space-y-3 text-sm">
                            <li><Link href="/" class="text-on-surface-variant transition hover:text-primary">Beranda</Link></li>
                            <li><Link href="#fitur" class="text-on-surface-variant transition hover:text-primary">Fitur</Link></li>
                            <li><Link href="#alur" class="text-on-surface-variant transition hover:text-primary">Alur</Link></li>
                            <li><Link href="#regulasi" class="text-on-surface-variant transition hover:text-primary">Regulasi</Link></li>
                            <li><Link href="/berita" class="text-on-surface-variant transition hover:text-primary">Berita</Link></li>
                            <li><Link href="/kontak" class="text-on-surface-variant transition hover:text-primary">Kontak</Link></li>
                        </ul>
                    </div>

                    <div>
                        <p class="t-eyebrow text-on-surface">Kontak</p>
                        <ul class="mt-5 space-y-3 text-sm text-on-surface-variant">
                            <li v-if="organization.phone" class="flex items-start gap-2.5"><AppIcon name="call" class="text-base leading-none text-primary" /> {{ organization.phone }}</li>
                            <li v-if="organization.email" class="flex items-start gap-2.5"><AppIcon name="mail" class="text-base leading-none text-primary" /> {{ organization.email }}</li>
                            <li v-if="organization.address" class="flex items-start gap-2.5"><AppIcon name="place" class="text-base leading-none text-primary" /> <span>{{ organization.address }}</span></li>
                        </ul>
                        <div v-if="settings.social?.facebook || settings.social?.instagram || settings.social?.youtube" class="mt-5 flex gap-2">
                            <a v-if="settings.social?.facebook" :href="settings.social.facebook" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-full bg-surface-container text-on-surface-variant transition hover:bg-primary hover:text-on-primary" aria-label="Facebook"><AppIcon name="facebook" class="text-base leading-none" /></a>
                            <a v-if="settings.social?.instagram" :href="settings.social.instagram" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-full bg-surface-container text-on-surface-variant transition hover:bg-primary hover:text-on-primary" aria-label="Instagram"><AppIcon name="photo_camera" class="text-base leading-none" /></a>
                            <a v-if="settings.social?.youtube" :href="settings.social.youtube" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-full bg-surface-container text-on-surface-variant transition hover:bg-primary hover:text-on-primary" aria-label="YouTube"><AppIcon name="play_arrow" class="text-base leading-none" /></a>
                        </div>
                    </div>
                </div>

                <div class="mt-12 flex flex-col items-center justify-between gap-3 border-t border-outline-variant/40 pt-8 text-center sm:flex-row sm:text-left">
                    <p class="text-sm font-semibold text-on-surface">
                        © {{ new Date().getFullYear() }} {{ organization.legal_name }}
                    </p>
                    <p v-if="settings.footer_note" class="text-xs text-on-surface-variant">{{ settings.footer_note }}</p>
                    <p v-else class="text-xs text-on-surface-variant">
                        Dikelola dengan <a href="/" class="font-semibold text-primary hover:underline">siupk Next</a> — Sistem Tata Kelola Keuangan &amp; Dana Bergulir BUMDesma LKD
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
