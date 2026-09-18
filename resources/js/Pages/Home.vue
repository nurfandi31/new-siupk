<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import gsap from 'gsap';
import { Head, Link } from '@inertiajs/vue3';
import AppButton from '../Components/AppButton.vue';
import AppIcon from '../Components/AppIcon.vue';
import AppCard from '../Components/AppCard.vue';
import AppBadge from '../Components/AppBadge.vue';

defineProps({
    name: { type: String, default: 'siupk Next' },
    status: { type: String, default: 'ok' },
});

const mobileNavOpen = ref(false);
const heroVisualRef = ref(null);
const heroCardRef = ref(null);
const activeSection = ref('');
const showScrollTop = ref(false);

const navLinks = [
    { id: 'fitur', label: 'Fitur Unggulan' },
    { id: 'alur', label: 'Alur Kerja' },
    { id: 'statistik', label: 'Capaian' },
    { id: 'faq', label: 'Tanya Jawab' },
];

const features = [
    {
        code: '01',
        icon: 'account_balance',
        title: 'Pengelolaan Dana Bergulir',
        desc: 'Pinjaman kelompok, angsuran, dan kolektibilitas per pemanfaat.',
    },
    {
        code: '02',
        icon: 'bar_chart',
        title: 'Konsolidasi Keuangan Kabupaten',
        desc: 'Portal pengawas untuk Dinas PMD & Inspektorat se-wilayah.',
    },
    {
        code: '03',
        icon: 'smart_toy',
        title: 'AI Assistant & Regulasi',
        desc: 'Analisis data, proyeksi keuangan, dan konsultasi SOP.',
    },
    {
        code: '04',
        icon: 'qr_code_2',
        title: 'Tagihan Otomatis & QRIS',
        desc: 'Pembayaran via QRIS dan Virtual Account bank nasional.',
    },
    {
        code: '05',
        icon: 'chat',
        title: 'Notifikasi WhatsApp',
        desc: 'Slip pencairan, struk angsuran, dan pengingat jatuh tempo.',
    },
    {
        code: '06',
        icon: 'shield',
        title: 'Keamanan & Isolasi Data',
        desc: 'Basis data terisolasi per BUMDesma untuk kerahasiaan.',
    },
];

const stats = [
    { label: 'Kecamatan / BUMDesma Siap Terlayani', target: 500, suffix: '+', icon: 'location_city' },
    { label: 'Kelompok Pemanfaat Dikelola', target: 12500, suffix: '+', icon: 'groups', formatNumber: true },
    { label: 'Posting Jurnal Otomatis', target: 100, suffix: '%', icon: 'receipt_long' },
    { label: 'Akurasi Konsolidasi Laporan', target: 99.9, suffix: '%', icon: 'verified', isDecimal: true },
];

const statCounters = ref(stats.map(() => 0));

const accuracyProgress = computed(() => {
    const max = 100;
    const v = statCounters.value[3] ?? 0;
    return Math.min(100, (v / max) * 100);
});

function formatStat(idx) {
    const s = stats[idx];
    const v = statCounters.value[idx];
    if (s.isDecimal) return v.toFixed(1).replace('.', ',') + s.suffix;
    if (s.formatNumber) return Math.floor(v).toLocaleString('id-ID') + s.suffix;
    return Math.floor(v) + s.suffix;
}

const steps = [
    {
        num: '01',
        title: 'Registrasi & Alokasi Tenant',
        desc: 'Pendaftaran BUMDesma LKD untuk mendapatkan alokasi basis data terisolasi yang aman dan siap pakai.',
        icon: 'how_to_reg',
        role: 'Tim Teknis + Pengurus BUMDesma',
        duration: '1–3 hari kerja',
        output: [
            'Tenant database terisolasi & terenkripsi',
            'Akun admin & operator BUMDesma',
            'Konfigurasi COA & mata uang daerah',
            'Surat penugasan pendampingan',
        ],
    },
    {
        num: '02',
        title: 'Migrasi & Saldo Awal',
        desc: 'Fasilitas Import Wizard pintar untuk memindahkan data master kelompok, anggota, dan saldo awal dari sistem sebelumnya.',
        icon: 'database',
        role: 'Tim Teknis + Bendahara',
        duration: '3–7 hari kerja',
        output: [
            'Import data master desa, kelompok, anggota',
            'Riwayat pinjaman & saldo awal',
            'Rekonsiliasi saldo pembuka',
            'Laporan migrasi tervalidasi',
        ],
    },
    {
        num: '03',
        title: 'Operasional Harian',
        desc: 'Pencatatan pinjaman, pembayaran angsuran, kas/bank, dan jurnal akuntansi otomatis sesuai standar SAK Entitas Privat.',
        icon: 'monitoring',
        role: 'Operator BUMDesma (mandiri)',
        duration: 'Berjalan otomatis setiap hari',
        output: [
            'Pinjaman baru & angsuran real-time',
            'Jurnal akuntansi otomatis (SAK EP/ETAP)',
            'Integrasi QRIS & Virtual Account',
            'Asisten AI regulasi (Ollama LLM)',
        ],
    },
    {
        num: '04',
        title: 'Laporan & Pengawasan Pemda',
        desc: 'Penerbitan laporan resmi berkala untuk pertanggungjawaban musyawarah antar desa (MAD) dan monitoring dinas terkait.',
        icon: 'assessment',
        role: 'Pengurus + Dinas PMD/Inspektorat',
        duration: 'Bulanan & tahunan',
        output: [
            'Laporan Keuangan (Neraca, Laba Rugi, Arus Kas)',
            'Laporan pertanggungjawaban MAD',
            'Dashboard monitoring Dinas PMD',
            'Export PDF/Excel siap audit',
        ],
    },
];

const activeStep = ref(0);
function setActiveStep(idx) {
    activeStep.value = idx;
}

const trustLogos = [
    {
        label: 'PP No. 11/2021',
        sub: 'Regulasi BUMDesma',
        icon: 'gavel',
        desc: 'Pendirian & pengelolaan sesuai Peraturan Pemerintah.',
        tone: 'primary',
    },
    {
        label: 'SAK EP / ETAP',
        sub: 'Standar Akuntansi',
        icon: 'account_balance',
        desc: 'Bagan akun entitas mikro & privat yang teruji.',
        tone: 'secondary',
    },
    {
        label: 'Database Sharding',
        sub: 'Isolasi Tenant',
        icon: 'database',
        desc: 'Ruang data independen & terenkripsi per kecamatan.',
        tone: 'tertiary',
    },
    {
        label: 'QRIS & VA Bank',
        sub: 'Payment Gateway',
        icon: 'payments',
        desc: 'QRIS nasional + Virtual Account multi-bank.',
        tone: 'secondary',
    },
    {
        label: 'Ollama LLM',
        sub: 'AI Lokal',
        icon: 'smart_toy',
        desc: 'Asisten regulasi on-prem tanpa kirim data keluar.',
        tone: 'tertiary',
    },
];

const faqs = [
    {
        q: 'Apa itu siupk Next dan siapa saja yang dapat menggunakannya?',
        a: 'siupk Next adalah sistem informasi tata kelola keuangan terpadu yang dirancang khusus untuk BUMDesma LKD (Lembaga Keuangan Desa / Eks UPK PNPM-MPd), pengelola dana bergulir masyarakat, serta instansi pembina teknis di tingkat Kabupaten (Dinas PMD & Inspektorat).',
    },
    {
        q: 'Apakah sistem ini sesuai dengan regulasi pemerintah dan standar akuntansi terkini?',
        a: 'Ya, sistem telah diselaraskan dengan amanat PP No. 11 Tahun 2021 tentang BUMDesa, Permendesa PDTT, serta standar bagan akun (COA) akuntansi keuangan entitas mikro & privat (SAK EP/ETAP) untuk menghasilkan Neraca, Laba Rugi, Arus Kas, dan CALK yang akuntabel.',
    },
    {
        q: 'Bagaimana keamanan dan kerahasiaan data keuangan masing-masing BUMDesma?',
        a: 'Sistem menggunakan teknologi Database Sharding terisolasi, di mana setiap BUMDesma memiliki ruang data yang independen dan terenkripsi sehingga data antar-kecamatan tidak dapat saling bercampur atau diakses tanpa izin.',
    },
    {
        q: 'Apakah data lama dari format Excel atau database Access dapat dipindahkan?',
        a: 'Tersedia modul Import Wizard dan migrasi data pintar yang memudahkan pengurus memasukkan data master desa, kelompok pemanfaat, data anggota, serta riwayat saldo pinjaman lama secara cepat tanpa harus input manual satu per satu.',
    },
    {
        q: 'Bagaimana cara BUMDesma mendaftarkan unit atau berkonsultasi implementasi?',
        a: 'Pengurus BUMDesma maupun perwakilan Dinas PMD dapat menghubungi tim teknis kami melalui tombol "Konsultasi & Pendaftaran" di bawah ini untuk pendampingan registrasi, demonstrasi sistem, dan pelatihan operator.',
    },
];

const activeFaq = ref(null);
function toggleFaq(idx) {
    activeFaq.value = activeFaq.value === idx ? null : idx;
}

function smoothScrollTo(id) {
    mobileNavOpen.value = false;
    const el = document.getElementById(id);
    if (el) el.scrollIntoView({ behavior: 'smooth' });
}

// 3D Parallax Tilt + cursor "push" displacement + cursor glow tracking + backdrop counter-parallax
function onHeroMouseMove(e) {
    if (!heroVisualRef.value || !heroCardRef.value) return;
    const rect = heroVisualRef.value.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const rotateX = ((y - centerY) / centerY) * -5;
    const rotateY = ((x - centerX) / centerX) * 5;

    // Normalized cursor position relative to center (-1 .. 1)
    const normX = (x - centerX) / centerX;
    const normY = (y - centerY) / centerY;

    // Card pushed AWAY from cursor (opposite direction) — "kedorong" effect
    const pushX = normX * -12;
    const pushY = normY * -10;

    gsap.to(heroCardRef.value, {
        rotateX,
        rotateY,
        x: pushX,
        y: pushY,
        transformPerspective: 1400,
        duration: 0.45,
        ease: 'power2.out',
    });

    // Cursor glow tracking · pass mouse coords as CSS vars to inner overlays
    const shellEl = heroCardRef.value.querySelector('.hero-mockup-shell');
    if (shellEl) {
        const shellRect = shellEl.getBoundingClientRect();
        const localX = ((e.clientX - shellRect.left) / shellRect.width) * 100;
        const localY = ((e.clientY - shellRect.top) / shellRect.height) * 100;
        shellEl.style.setProperty('--mouse-x', `${localX}%`);
        shellEl.style.setProperty('--mouse-y', `${localY}%`);
        shellEl.classList.add('is-cursor-active');
    }
}

function onHeroMouseLeave() {
    if (!heroVisualRef.value || !heroCardRef.value) return;
    gsap.to(heroCardRef.value, {
        rotateX: 0,
        rotateY: 0,
        x: 0,
        y: 0,
        duration: 0.9,
        ease: 'elastic.out(1, 0.3)',
    });

    const shellEl = heroCardRef.value.querySelector('.hero-mockup-shell');
    if (shellEl) {
        shellEl.classList.remove('is-cursor-active');
    }
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function onWindowScroll() {
    showScrollTop.value = window.scrollY > 600;
}

let observerInstance = null;
let navObserverInstance = null;

onMounted(() => {
    nextTick(() => {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (!prefersReducedMotion) {
            const heroTl = gsap.timeline({ defaults: { ease: 'power3.out' } });

            heroTl
                .fromTo('.top-banner-bar', { y: -20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.5 })
                .fromTo('.nav-container', { y: -25, opacity: 0 }, { y: 0, opacity: 1, duration: 0.6 }, '-=0.2')
                .fromTo('.hero-anim-badge', { opacity: 0, scale: 0.85, y: 15 }, { opacity: 1, scale: 1, y: 0, duration: 0.6, ease: 'back.out(1.7)' }, '-=0.3')
                .fromTo('.hero-anim-title', { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: 0.75, ease: 'power4.out' }, '-=0.4')
                .fromTo('.hero-anim-desc', { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.65 }, '-=0.4')
                .fromTo('.hero-anim-actions > *', { opacity: 0, y: 20, scale: 0.96 }, { opacity: 1, y: 0, scale: 1, duration: 0.6, ease: 'back.out(1.4)' }, '-=0.4')
                .fromTo('.hero-preview-card', { opacity: 0, scale: 0.92, y: 40 }, { opacity: 1, scale: 1, y: 0, duration: 0.95, ease: 'back.out(1.3)' }, '-=0.7');
        } else {
            gsap.set('.top-banner-bar, .nav-container, .hero-anim-badge, .hero-anim-title, .hero-anim-desc, .hero-anim-actions > *, .hero-preview-card', { opacity: 1, y: 0, clearProps: 'all' });
        }

        // Scroll reveal + stats counter
        let statsCounted = false;
        observerInstance = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    const target = entry.target;

                    if (target.classList.contains('stats-section') && !statsCounted) {
                        statsCounted = true;
                        stats.forEach((s, idx) => {
                            const counterObj = { val: 0 };
                            gsap.to(counterObj, {
                                val: s.target,
                                duration: 2.2,
                                ease: 'power3.out',
                                onUpdate: () => { statCounters.value[idx] = counterObj.val; },
                            });
                        });
                    }

                    if (target.classList.contains('reveal-group')) {
                        const items = target.querySelectorAll('.reveal-item');
                        gsap.fromTo(
                            items,
                            { opacity: 0, y: 35, scale: 0.97 },
                            { opacity: 1, y: 0, scale: 1, duration: 0.65, stagger: 0.1, ease: 'power2.out' }
                        );
                        observerInstance.unobserve(target);
                    }
                });
            },
            { threshold: 0.12 }
        );

        document.querySelectorAll('.stats-section, .reveal-group').forEach((el) => observerInstance.observe(el));

        // Nav active section observer — track which section is in viewport
        navObserverInstance = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        activeSection.value = entry.target.id;
                    }
                });
            },
            { rootMargin: '-30% 0px -60% 0px', threshold: 0 }
        );

        navLinks.forEach((link) => {
            const el = document.getElementById(link.id);
            if (el) navObserverInstance.observe(el);
        });

        // Scroll-to-top visibility
        window.addEventListener('scroll', onWindowScroll, { passive: true });
    });
});

onUnmounted(() => {
    if (observerInstance) observerInstance.disconnect();
    if (navObserverInstance) navObserverInstance.disconnect();
    window.removeEventListener('scroll', onWindowScroll);
});
</script>

<template>
    <Head title="siupk Next - Sistem Informasi Dana Bergulir Masyarakat" />

    <div class="min-h-screen bg-surface font-sans text-on-surface antialiased scroll-smooth selection:bg-primary selection:text-on-primary">
        <!-- Top Banner -->
        <div class="top-banner-bar relative overflow-hidden border-b border-primary/20 bg-gradient-to-r from-primary via-primary-container to-primary-deep text-on-primary shadow-md shadow-primary/10">
            <!-- Decorative ambient glows -->
            <div class="pointer-events-none absolute -left-24 top-0 size-48 rounded-full bg-white/10 blur-3xl" />
            <div class="pointer-events-none absolute right-1/4 -top-12 size-40 rounded-full bg-primary-fixed/20 blur-3xl" />
            <div class="pointer-events-none absolute -right-16 bottom-0 size-44 rounded-full bg-secondary/15 blur-3xl" />

            <div class="relative mx-auto flex h-9 max-w-7xl items-center px-4 sm:px-6 lg:px-8">
                <div class="grid w-full grid-cols-3 items-center gap-2 text-[11px] font-medium tracking-[0.08em] sm:gap-4">

                    <!-- Segment 1: Regulasi PP (kiri) -->
                    <div class="flex items-center gap-2 justify-self-start">
                        <span class="top-banner-badge top-banner-icon relative grid size-5 place-items-center rounded-md bg-on-primary/15 ring-1 ring-on-primary/30 transition-all duration-300 hover:bg-on-primary/30 hover:scale-110 hover:shadow-md hover:shadow-on-primary/20">
                            <svg class="relative size-3 banner-shield-group" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path class="banner-shield-path" d="M12 2 4 5v7c0 5 3.5 8.5 8 10 4.5-1.5 8-5 8-10V5l-8-3Z" />
                                <path class="banner-shield-check" d="m9 12 2 2 4-4" />
                            </svg>
                        </span>
                        <span class="hidden font-mono text-[9px] font-bold uppercase tracking-[0.22em] text-primary-fixed-dim sm:inline">Regulasi</span>
                        <span class="font-bold tracking-[0.05em]">PP No.&nbsp;11/2021</span>
                    </div>

                    <!-- Placeholder tengah (kosong) -->
                    <div class="hidden md:block"></div>

                    <!-- Segment 2: Sistem Nasional (kanan) + Version chip -->
                    <div class="hidden items-center gap-3 justify-self-end md:flex">
                        <span class="font-semibold">Sistem Informasi Unit Pengelola Kegiatan</span>
                        <span class="banner-version-chip inline-flex items-center gap-1.5 rounded-full border border-on-primary/30 bg-on-primary/10 px-2.5 py-0.5 text-[10px] font-black tracking-wider backdrop-blur-sm">
                            <span class="banner-version-dot relative grid size-1.5 place-items-center rounded-full bg-on-primary">
                                <span class="size-1.5 rounded-full bg-on-primary" />
                            </span>
                            v2.6
                        </span>
                    </div>

                    <!-- Mobile: hanya versi di pojok kanan -->
                    <span class="banner-version-chip inline-flex items-center gap-1.5 justify-self-end rounded-full border border-on-primary/30 bg-on-primary/10 px-2 py-0.5 text-[9px] font-black tracking-wider backdrop-blur-sm md:hidden">
                        <span class="size-1.5 rounded-full bg-on-primary" />
                        v2.6
                    </span>
                </div>
            </div>

            <!-- Bottom accent line -->
            <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-primary-fixed/40 to-transparent" />
        </div>

        <!-- Sticky Header / Navbar -->
        <header class="nav-container sticky top-0 z-40 border-b border-outline-variant/40 bg-surface-container-lowest/85 backdrop-blur-xl transition-all">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3.5 sm:px-6 lg:px-8">
                <!-- Brand Logo -->
                <a href="#" class="flex items-center gap-3 transition hover:opacity-90 group">
                    <div class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-primary to-primary-container text-on-primary shadow-md shadow-primary/25 transition-transform group-hover:scale-105 duration-300">
                        <AppIcon name="account_balance" class="text-2xl" />
                    </div>
                    <div>
                        <span class="block text-lg font-black tracking-tight text-primary">siupk <span class="bg-gradient-to-r from-secondary to-secondary-container bg-clip-text text-transparent">Next</span></span>
                        <span class="block text-[10px] font-bold uppercase tracking-[0.18em] text-outline">BUMDesma LKD Platform</span>
                    </div>
                </a>

                <!-- Right Group: Nav Links + Login Button (login pinned to far right) -->
                <div class="flex items-center gap-2 md:gap-3">
                    <!-- Desktop Navigation Links — left of login button -->
                    <nav class="hidden items-center gap-1 md:flex">
                        <a
                            v-for="link in navLinks"
                            :key="link.id"
                            :href="`#${link.id}`"
                            @click.prevent="smoothScrollTo(link.id)"
                            class="group relative rounded-full px-4 py-2 text-sm font-medium transition-all duration-300"
                            :class="activeSection === link.id
                                ? 'text-primary'
                                : 'text-on-surface-variant hover:text-primary'"
                        >
                            {{ link.label }}
                            <span
                                class="absolute -bottom-0.5 left-1/2 h-0.5 -translate-x-1/2 rounded-full bg-primary transition-all duration-300"
                                :class="activeSection === link.id ? 'w-8 opacity-100' : 'w-0 opacity-0'"
                            />
                        </a>
                    </nav>

                    <!-- Login Button (Desktop) — pinned to far right -->
                    <Link href="/login" class="hidden md:inline-flex">
                        <AppButton variant="outline" size="compact" icon="login" icon-class="transition-transform duration-300 ease-out group-hover/btn:translate-x-1" class="font-bold group/btn">
                            <span class="transition-transform duration-300 group-hover/btn:tracking-wide">Masuk Portal</span>
                        </AppButton>
                    </Link>

                    <!-- Mobile Menu Toggle (rightmost) -->
                    <button
                        type="button"
                        class="grid size-10 place-items-center rounded-lg text-on-surface shadow-sm ring-1 ring-inset ring-outline-variant/40 md:hidden transition hover:bg-surface-container-high hover:shadow-md"
                        :aria-label="mobileNavOpen ? 'Tutup navigasi' : 'Buka navigasi'"
                        @click="mobileNavOpen = !mobileNavOpen"
                    >
                        <AppIcon :name="mobileNavOpen ? 'close' : 'menu'" class="text-2xl" />
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation Drawer -->
            <transition name="drawer">
                <div v-if="mobileNavOpen" class="border-b border-outline-variant bg-surface-container-low/95 px-4 py-4 md:hidden">
                    <nav class="flex flex-col gap-1">
                        <a
                            v-for="link in navLinks"
                            :key="link.id"
                            :href="`#${link.id}`"
                            @click="smoothScrollTo(link.id)"
                            class="rounded-lg px-3 py-2.5 text-sm font-semibold text-on-surface hover:bg-surface-container-high"
                        >{{ link.label }}</a>
                        <div class="pt-2 mt-2 border-t border-outline-variant">
                            <Link href="/login" class="w-full">
                                <AppButton variant="outline" size="medium" icon="login" icon-class="transition-transform duration-300 ease-out group-hover/btn:translate-x-1" class="w-full font-bold group/btn">
                                    <span class="transition-transform duration-300 group-hover/btn:tracking-wide">Masuk Portal</span>
                                </AppButton>
                            </Link>
                        </div>
                    </nav>
                </div>
            </transition>
        </header>

        <!-- Hero Section -->
        <main>
            <section class="hero-section relative overflow-hidden bg-surface-container-lowest py-16 sm:py-20 lg:py-24">
                <div class="sep-h-strong absolute bottom-0 left-0 right-0" />

                <div class="relative mx-auto max-w-[88rem] px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-12 sm:gap-10 md:gap-8 lg:grid-cols-2 lg:gap-12 xl:gap-16">
                        <!-- LEFT COLUMN · editorial text block -->
                        <div class="space-y-5 text-center lg:text-left">
                            <!-- Eyebrow pill · outlined style -->
                            <div class="hero-anim-badge inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/5 px-3.5 py-1 font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-primary sm:text-[11px]">
                                <span class="grid size-4 place-items-center rounded-full border-2 border-primary/60">
                                    <svg class="size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="m5 12 5 5 9-11" />
                                    </svg>
                                </span>
                                <span>Platform Resmi · PP No. 11/2021</span>
                            </div>

                            <!-- Headline · Tata Kelola + Dana Bergulir. -->
                            <h1 class="hero-anim-title font-sans text-[2.25rem] font-black leading-[1.02] tracking-[-0.035em] sm:text-[2.5rem] lg:text-[3.25rem] xl:text-[3.5rem]">
                                <span class="block text-on-surface">Tata Kelola</span>
                                <span class="mt-1 block text-primary">
                                    Dana Bergulir<span class="text-primary-fixed">.</span>
                                </span>
                            </h1>

                            <!-- Mono kicker + body copy -->
                            <div class="hero-anim-desc space-y-4">
                                <p class="font-mono text-[10px] font-bold uppercase tracking-[0.32em] text-outline sm:text-xs">
                                    Sistem Informasi · Unit Pengelola Kegiatan
                                </p>
                                <p class="mx-auto max-w-xl text-base leading-relaxed text-on-surface-variant sm:text-lg lg:mx-0">
                                    Solusi terintegrasi untuk pengelolaan pinjaman bergulir, pembukuan akuntansi standar <span class="font-bold text-on-surface">SAK Entitas Privat</span>, penerbitan kuitansi WhatsApp, dan pelaporan konsolidasi Pemerintah Kabupaten secara real-time.
                                </p>
                            </div>

                            <!-- CTA buttons -->
                            <div class="hero-anim-actions flex flex-wrap items-center justify-center gap-3 pt-1 lg:justify-start">
                                <Link href="/login">
                                    <AppButton variant="primary" size="large" icon="login" class="!rounded-full font-bold shadow-lg shadow-primary/25 hover:shadow-xl hover:shadow-primary/30 transition-all duration-300">
                                        Masuk Portal
                                    </AppButton>
                                </Link>
                                <a href="#fitur" @click.prevent="smoothScrollTo('fitur')">
                                    <AppButton variant="outline" size="large" icon="arrow_circle_right" icon-class="transition-transform duration-300 ease-out group-hover/btn:translate-x-1" class="!rounded-full font-bold ring-1 ring-inset ring-primary/30 transition-all duration-300 hover:ring-primary/60 group/btn">
                                        Pelajari Fitur
                                    </AppButton>
                                </a>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN · Compliance Console with 3D parallax tilt -->
                        <div
                            ref="heroVisualRef"
                            class="hero-bento relative perspective-[1400px] flex justify-center"
                            @mousemove="onHeroMouseMove"
                            @mouseleave="onHeroMouseLeave"
                        >
                            <!-- SINGLE MOCKUP CARD · compliance console -->
                            <div ref="heroCardRef" class="hero-preview-card relative w-full max-w-md will-change-transform transform-gpu">
                                <div class="hero-mockup-shell relative overflow-hidden rounded-2xl bg-white/85 ring-1 ring-primary/15 shadow-[0_2px_4px_rgba(0,0,0,0.04),0_12px_28px_-10px_rgba(3,92,63,0.22),0_28px_56px_-16px_rgba(3,92,63,0.14)] backdrop-blur-xl">
                                    <!-- Cursor glow overlay -->
                                    <div class="hero-cursor-glow pointer-events-none absolute inset-0 rounded-2xl" aria-hidden="true" />
                                    <!-- Inner border glow on hover -->
                                    <div class="hero-cursor-border pointer-events-none absolute inset-0 rounded-2xl" aria-hidden="true" />

                                    <!-- Window-style header -->
                                    <div class="relative flex items-center justify-between px-4 py-2.5 shadow-[0_1px_0_0_rgba(3,92,63,0.10)]">
                                        <div class="flex items-center gap-2">
                                            <span class="size-2.5 rounded-full bg-error/80" />
                                            <span class="size-2.5 rounded-full bg-amber-400/80" />
                                            <span class="size-2.5 rounded-full bg-secondary/80" />
                                            <span class="ml-2 text-[11px] font-black tracking-tight text-primary">UPK · Compliance Console</span>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary/15 px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-[0.14em] text-secondary">
                                            <span class="size-1.5 rounded-full bg-secondary animate-pulse" />
                                            Live
                                        </span>
                                    </div>

                                    <div class="space-y-3 p-4">
                                        <!-- Compliance list · 3 regulasi items · underline divider style -->
                                        <div class="space-y-0">
                                            <div class="flex items-center justify-between border-b border-primary/[0.08] py-2.5">
                                                <div class="flex items-center gap-3">
                                                    <AppIcon name="gavel" class="text-[18px] leading-none text-primary" />
                                                    <div class="leading-tight">
                                                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-outline">PP 11/2021</p>
                                                        <p class="text-[13px] font-bold text-on-surface">BUMDesma compliant</p>
                                                    </div>
                                                </div>
                                                <AppIcon name="check_circle" class="text-[18px] leading-none text-primary" />
                                            </div>
                                            <div class="flex items-center justify-between border-b border-primary/[0.08] py-2.5">
                                                <div class="flex items-center gap-3">
                                                    <AppIcon name="calculate" class="text-[18px] leading-none text-primary" />
                                                    <div class="leading-tight">
                                                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-outline">SAK EP / ETAP</p>
                                                        <p class="text-[13px] font-bold text-on-surface">Standar Akuntansi</p>
                                                    </div>
                                                </div>
                                                <AppIcon name="check_circle" class="text-[18px] leading-none text-primary" />
                                            </div>
                                            <div class="flex items-center justify-between border-b border-primary/[0.08] py-2.5">
                                                <div class="flex items-center gap-3">
                                                    <AppIcon name="database" class="text-[18px] leading-none text-primary" />
                                                    <div class="leading-tight">
                                                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-outline">Shard Database</p>
                                                        <p class="text-[13px] font-bold text-on-surface">Isolasi per BUMDesma</p>
                                                    </div>
                                                </div>
                                                <AppIcon name="check_circle" class="text-[18px] leading-none text-primary" />
                                            </div>
                                        </div>

                                        <!-- Key metrics · 2 stats side-by-side -->
                                        <div class="grid grid-cols-2 gap-3 pt-1">
                                            <div class="space-y-1">
                                                <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-outline">Kolektibilitas</span>
                                                <p class="text-[1.5rem] font-black leading-none tracking-tight text-on-surface">98,6%</p>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-primary">
                                                    <AppIcon name="check_circle" class="text-[12px] leading-none" /> Lancar
                                                </span>
                                            </div>
                                            <div class="space-y-1">
                                                <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-outline">Wilayah</span>
                                                <p class="text-[1.5rem] font-black leading-none tracking-tight text-on-surface">38 <span class="text-sm font-bold">Kabupaten</span></p>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-primary">
                                                    <AppIcon name="location_on" class="text-[12px] leading-none" /> se-Indonesia
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Footer sync status -->
                                        <div class="flex items-center justify-between pt-2 text-[10px] font-bold text-outline shadow-[0_-1px_0_0_rgba(3,92,63,0.08)]">
                                            <span class="inline-flex items-center gap-1.5 text-on-surface-variant">
                                                <svg class="size-[14px] text-primary hero-sync-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M3 12a9 9 0 0 1 15-6.7L21 8" />
                                                    <polyline points="21 3 21 8 16 8" />
                                                    <path d="M21 12a9 9 0 0 1-15 6.7L3 16" />
                                                    <polyline points="3 21 3 16 8 16" />
                                                </svg>
                                                Sinkronisasi Shard DB
                                            </span>
                                            <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-[10px] font-black tracking-wide text-primary">Terisolasi</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Stats Bar Section · Bento Grid Asimetris -->
            <section id="statistik" class="stats-section relative overflow-hidden border-t border-outline-variant/30 bg-surface-container-lowest py-10 sm:py-12 text-on-surface scroll-mt-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
                    <div class="mb-6 flex flex-col items-start justify-between gap-3 sm:mb-7 sm:flex-row sm:items-end">
                        <div class="space-y-1.5">
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-primary">Skala & Kualitas Operasional</p>
                            <h2 class="text-xl sm:text-2xl font-black leading-tight tracking-tight text-on-surface sm:max-w-md">Bukti Nyata, Bukan Sekedar Klaim</h2>
                        </div>
                        <p class="text-xs font-medium text-on-surface-variant sm:max-w-xs sm:text-right">Angka-angka ini dihasilkan langsung dari konsolidasi data lintas BUMDesma yang aktif menggunakan sistem.</p>
                    </div>

                    <!-- Bento grid: hero card (left, col-span-7) + duo cards (right, col-span-5 stacked) + akurasi banner full-width di bawah -->
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                        <!-- Hero Stat · left, spans 7 cols / 2 rows -->
                        <div
                            class="bento-card bento-hero group relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary-container to-primary-deep p-4 sm:p-5 text-on-primary shadow-xl shadow-primary/20 ring-1 ring-inset ring-primary/30 md:col-span-7 md:row-span-2 transition-all duration-500 hover:-translate-y-1 hover:shadow-2xl hover:shadow-primary/30"
                        >
                            <!-- Glow accent -->
                            <div class="absolute -right-12 -bottom-12 size-56 rounded-full bg-secondary/30 blur-3xl transition-all duration-500 group-hover:bg-secondary/50 group-hover:scale-110" />
                            <div class="absolute right-3 top-3 flex items-center gap-1.5 rounded-full border border-white/30 bg-white/15 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-on-primary backdrop-blur-sm">
                                <span class="relative flex size-1.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-300 opacity-75" />
                                    <span class="relative inline-flex size-1.5 rounded-full bg-emerald-300" />
                                </span>
                                Live Data
                            </div>

                            <div class="relative z-10 flex h-full flex-col justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-10 place-items-center rounded-xl bg-on-primary/20 text-on-primary shadow-inner transition-all duration-500 group-hover:scale-110 group-hover:rotate-3 group-hover:bg-on-primary/30">
                                        <AppIcon :name="stats[1].icon" class="text-xl" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-primary-fixed-dim">{{ stats[1].label }}</p>
                                        <p class="text-[9px] font-medium uppercase tracking-wider text-on-primary/70">Kelompok aktif terdaftar</p>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex items-baseline gap-2">
                                        <p class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tighter tabular-nums leading-none text-on-primary">
                                            {{ formatStat(1) }}
                                        </p>
                                    </div>
                                    <p class="mt-2 text-xs font-medium text-primary-fixed-dim max-w-md">Pemanfaat resmi yang tersebar di ratusan desa, tercatat dalam sistem pembukuan digital.</p>
                                </div>

                                <!-- Mini bar chart decoration -->
                                <div class="flex items-end gap-1 h-8 mt-1">
                                    <div v-for="(h, i) in [40, 65, 45, 80, 55, 90, 70, 95, 60, 85, 100, 75]" :key="i"
                                        class="w-1.5 rounded-t bg-gradient-to-t from-secondary/60 to-primary-fixed transition-all duration-500 group-hover:from-secondary group-hover:to-white"
                                        :style="`height: ${h}%; animation-delay: ${i * 50}ms`" />
                                </div>
                            </div>
                        </div>

                        <!-- Small stat 1 · top right · LIGHT surface + amber accent -->
                        <div
                            class="bento-card group relative overflow-hidden rounded-2xl bg-surface-container-lowest p-4 text-on-surface shadow-lg shadow-primary/10 ring-1 ring-inset ring-outline-variant/40 md:col-span-5 transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:shadow-primary/20"
                        >
                            <div class="absolute -right-8 -top-8 size-24 rounded-full bg-amber-400/20 blur-2xl transition-all duration-500 group-hover:bg-amber-400/30 group-hover:scale-125" />
                            <div class="relative z-10 flex h-full flex-col justify-between gap-2">
                                <div class="flex items-start justify-between">
                                    <div class="grid size-9 place-items-center rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-md shadow-amber-600/25 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-3">
                                        <AppIcon :name="stats[0].icon" class="text-lg" />
                                    </div>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-amber-700 ring-1 ring-amber-200">
                                        <AppIcon name="trending_up" class="text-[11px]" />
                                        Nasional
                                    </span>
                                </div>
                                <div>
                                    <p class="text-2xl sm:text-3xl font-black tracking-tight tabular-nums leading-none text-on-surface">{{ formatStat(0) }}</p>
                                    <p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant">{{ stats[0].label }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Small stat 2 · bottom right · WHITE surface + emerald ring (clean tech look) -->
                        <div
                            class="bento-card group relative overflow-hidden rounded-2xl bg-surface-container-lowest p-4 text-on-surface shadow-lg shadow-primary/10 ring-1 ring-inset ring-primary/30 md:col-span-5 transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:shadow-primary/20"
                        >
                            <div class="absolute -left-8 -bottom-8 size-24 rounded-full bg-primary/15 blur-2xl transition-all duration-500 group-hover:bg-primary/25 group-hover:scale-125" />
                            <div class="relative z-10 flex h-full flex-col justify-between gap-2">
                                <div class="flex items-start justify-between">
                                    <div class="grid size-9 place-items-center rounded-lg bg-gradient-to-br from-primary to-primary-container text-on-primary shadow-md shadow-primary/25 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3">
                                        <AppIcon :name="stats[2].icon" class="text-lg" />
                                    </div>
                                    <div class="grid size-6 place-items-center rounded-full bg-primary text-on-primary shadow-sm">
                                        <AppIcon name="check" class="text-sm font-bold" />
                                    </div>
                                </div>
                                <div>
                                    <p class="text-2xl sm:text-3xl font-black tracking-tight tabular-nums leading-none text-on-surface">{{ formatStat(2) }}</p>
                                    <p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant">{{ stats[2].label }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom row: stat akurasi full-width (di luar bento, sebagai banner mini) -->
                    <div
                        class="bento-card mt-3 group relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 p-4 text-white shadow-xl shadow-slate-900/25 ring-1 ring-inset ring-slate-700/50 transition-all duration-500 hover:-translate-y-1 hover:shadow-2xl hover:shadow-slate-900/40"
                    >
                        <div class="absolute -right-16 -top-16 size-40 rounded-full bg-primary/20 blur-3xl transition-all duration-500 group-hover:bg-primary/30 group-hover:scale-110" />
                        <div class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="grid size-10 place-items-center rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-md shadow-amber-600/30 transition-all duration-500 group-hover:scale-110">
                                    <AppIcon :name="stats[3].icon" class="text-lg" />
                                </div>
                                <div>
                                    <p class="text-[9px] font-bold uppercase tracking-wider text-amber-300">Standar Akurasi</p>
                                    <p class="text-xs font-bold text-white">{{ stats[3].label }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex-1 sm:flex-none sm:w-44">
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-white/15">
                                        <div class="bento-progress h-full rounded-full bg-gradient-to-r from-amber-400 via-emerald-300 to-teal-300" :style="`width: ${accuracyProgress}%`" />
                                    </div>
                                </div>
                                <p class="text-xl sm:text-2xl font-black tracking-tight tabular-nums leading-none">{{ formatStat(3) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Trust Strip / Standar & Regulasi · Featured + Grid -->
            <section class="trust-section relative overflow-hidden border-t border-outline-variant/30 bg-surface py-12 sm:py-14">
                <!-- Ambient orbs -->
                <div aria-hidden="true" class="absolute inset-x-0 -top-24 mx-auto h-48 max-w-3xl rounded-full bg-primary/5 blur-3xl" />
                <div aria-hidden="true" class="absolute inset-x-0 -bottom-24 mx-auto h-48 max-w-3xl rounded-full bg-secondary/5 blur-3xl" />

                <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <!-- Header -->
                    <div class="mx-auto mb-8 max-w-2xl text-center sm:mb-10">
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary-container/40 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-primary">
                            <AppIcon name="verified" class="text-xs" />
                            <span>Standar &amp; Regulasi Terkini</span>
                        </div>
                        <h3 class="text-xl font-black tracking-tight text-primary sm:text-2xl">
                            Standar Regulasi &amp; Tata Kelola Data
                        </h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-on-surface-variant">
                            Diselaraskan dengan kerangka hukum nasional dan arsitektur basis data berisolasi per entitas.
                        </p>
                    </div>

                    <!-- Featured + Grid layout -->
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-5">
                        <!-- FEATURED · left (col-span-4) -->
                        <article
                            class="trust-featured group relative flex flex-col overflow-hidden rounded-2xl bg-gradient-to-br from-primary-container via-surface to-surface-container-low p-5 sm:p-6 shadow-lg shadow-primary/15 ring-1 ring-inset ring-primary/20 transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:shadow-primary/25 sm:col-span-12 lg:col-span-4"
                        >
                            <!-- Glow accent -->
                            <div class="absolute -right-12 -bottom-12 size-40 rounded-full bg-primary/15 blur-3xl transition-all duration-500 group-hover:bg-primary/25 group-hover:scale-110" />

                            <!-- Top row: badge + verified check -->
                            <div class="relative z-10 flex items-center justify-between mb-4">
                                <div class="inline-flex items-center gap-1 rounded-full bg-primary px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-on-primary shadow-sm">
                                    <AppIcon name="workspace_premium" class="text-[11px]" />
                                    Featured
                                </div>
                                <div class="grid size-7 place-items-center rounded-full bg-primary/15 text-primary transition-all duration-500 group-hover:bg-primary group-hover:text-on-primary">
                                    <AppIcon name="verified" class="text-sm" />
                                </div>
                            </div>

                            <!-- Icon box -->
                            <div class="relative z-10 mb-4 grid size-12 place-items-center rounded-xl bg-gradient-to-br from-primary to-primary-container text-on-primary shadow-lg shadow-primary/30 transition-all duration-500 group-hover:scale-110 group-hover:-rotate-3">
                                <div class="absolute inset-1 rounded-lg border border-white/25" />
                                <AppIcon :name="trustLogos[0].icon" class="text-2xl relative z-10" />
                            </div>

                            <!-- Content -->
                            <div class="relative z-10 space-y-2 flex-1">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-secondary mb-0.5">
                                        {{ trustLogos[0].sub }}
                                    </p>
                                    <h4 class="text-lg sm:text-xl font-black tracking-tight leading-tight text-primary">
                                        {{ trustLogos[0].label }}
                                    </h4>
                                </div>
                                <p class="text-xs text-on-surface-variant leading-relaxed">
                                    {{ trustLogos[0].desc }}
                                </p>
                            </div>

                            <!-- Trust bullets -->
                            <div class="relative z-10 mt-4 space-y-1.5 pt-4 shadow-[0_-1px_0_0_rgba(3,92,63,0.10)]">
                                <div class="flex items-center gap-1.5 text-[11px] font-semibold text-on-surface">
                                    <AppIcon name="check_circle" class="text-sm text-secondary" />
                                    <span>Diacu lintas Kementerian/Lembaga</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-[11px] font-semibold text-on-surface">
                                    <AppIcon name="check_circle" class="text-sm text-secondary" />
                                    <span>Update mengikuti perubahan regulasi</span>
                                </div>
                            </div>
                        </article>

                        <!-- SUPPORTING GRID · right (col-span-8) 2×2 -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:col-span-8">
                            <article
                                v-for="(logo, idx) in trustLogos.slice(1)"
                                :key="logo.label"
                                class="trust-card group relative flex flex-col overflow-hidden rounded-2xl bg-surface-container-lowest p-4 sm:p-5 shadow-sm shadow-outline-variant/10 ring-1 ring-inset ring-outline-variant/40 transition-all duration-500 hover:-translate-y-1.5 hover:shadow-lg"
                                :class="{
                                    'hover:ring-primary/40 hover:shadow-primary/15': logo.tone === 'primary',
                                    'hover:ring-secondary/40 hover:shadow-secondary/15': logo.tone === 'secondary',
                                    'hover:ring-tertiary/40 hover:shadow-tertiary/15': logo.tone === 'tertiary',
                                }"
                            >
                                <!-- Top accent bar (animated) -->
                                <div
                                    aria-hidden="true"
                                    class="absolute inset-x-0 top-0 h-0.5 origin-left scale-x-0 rounded-t-2xl transition-transform duration-500 group-hover:scale-x-100"
                                    :class="{
                                        'bg-gradient-to-r from-primary to-primary-container': logo.tone === 'primary',
                                        'bg-gradient-to-r from-secondary to-secondary-container': logo.tone === 'secondary',
                                        'bg-gradient-to-r from-tertiary to-tertiary-container': logo.tone === 'tertiary',
                                    }"
                                />

                                <!-- Corner decorative -->
                                <div
                                    aria-hidden="true"
                                    class="absolute -right-6 -bottom-6 size-20 rounded-full blur-2xl opacity-0 transition-all duration-500 group-hover:opacity-60"
                                    :class="{
                                        'bg-primary/30': logo.tone === 'primary',
                                        'bg-secondary/30': logo.tone === 'secondary',
                                        'bg-tertiary/30': logo.tone === 'tertiary',
                                    }"
                                />

                                <!-- Number watermark -->
                                <span class="absolute -right-1 -top-2 text-4xl font-black leading-none tabular-nums select-none transition-all duration-500 group-hover:scale-110"
                                    :class="{
                                        'text-primary/10 group-hover:text-primary/20': logo.tone === 'primary',
                                        'text-secondary/10 group-hover:text-secondary/20': logo.tone === 'secondary',
                                        'text-tertiary/10 group-hover:text-tertiary/20': logo.tone === 'tertiary',
                                    }">0{{ idx + 2 }}</span>

                                <div class="relative z-10 flex items-start justify-between mb-3">
                                    <div
                                        class="grid size-10 place-items-center rounded-lg transition-all duration-500 group-hover:scale-110 group-hover:-rotate-3 group-hover:shadow-md"
                                        :class="{
                                            'bg-primary-container text-primary group-hover:bg-primary group-hover:text-on-primary group-hover:shadow-primary/30': logo.tone === 'primary',
                                            'bg-secondary-container text-secondary group-hover:bg-secondary group-hover:text-on-secondary group-hover:shadow-secondary/30': logo.tone === 'secondary',
                                            'bg-tertiary-container text-tertiary group-hover:bg-tertiary group-hover:text-on-tertiary group-hover:shadow-tertiary/30': logo.tone === 'tertiary',
                                        }"
                                    >
                                        <AppIcon :name="logo.icon" class="text-xl" />
                                    </div>
                                    <AppBadge
                                        tone="neutral"
                                        class="hidden text-[8px] font-bold uppercase tracking-wider sm:inline-flex"
                                    >
                                        {{ logo.tone }}
                                    </AppBadge>
                                </div>

                                <div class="relative z-10 space-y-0.5 mb-2">
                                    <h4 class="text-sm font-black leading-tight text-primary sm:text-base">
                                        {{ logo.label }}
                                    </h4>
                                    <p class="text-[9px] font-bold uppercase tracking-[0.16em]"
                                        :class="{
                                            'text-primary': logo.tone === 'primary',
                                            'text-secondary': logo.tone === 'secondary',
                                            'text-tertiary': logo.tone === 'tertiary',
                                        }"
                                    >
                                        {{ logo.sub }}
                                    </p>
                                </div>

                                <p class="relative z-10 text-[11px] leading-relaxed text-on-surface-variant">
                                    {{ logo.desc }}
                                </p>

                                <!-- Verified mini badge -->
                                <div class="relative z-10 mt-3 flex items-center gap-1 text-[9px] font-bold text-secondary">
                                    <AppIcon name="verified" class="text-xs" />
                                    <span>Tersertifikasi</span>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Grid Section -->
            <section id="fitur" class="reveal-group relative border-t border-outline-variant/30 bg-surface-container-lowest scroll-mt-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 sm:py-14 space-y-10">
                    <!-- Section header -->
                    <div class="text-center space-y-3 max-w-2xl mx-auto">
                        <h2 class="text-2xl sm:text-3xl font-black text-primary tracking-tight leading-[1.1]">
                            Fitur Unggulan
                        </h2>
                        <p class="text-on-surface-variant text-sm sm:text-base leading-relaxed">
                            Modul utama yang menopang tata kelola dana bergulir BUMDesma.
                        </p>
                    </div>

                    <!-- Unified 6-card grid -->
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="f in features"
                            :key="f.code"
                            class="reveal-item group flex flex-col rounded-2xl bg-surface-container-lowest p-5 shadow-sm ring-1 ring-inset ring-outline-variant/30 transition-all duration-300 hover:shadow-md hover:ring-primary/40"
                        >
                            <!-- Icon -->
                            <div class="mb-4 grid size-11 place-items-center rounded-xl bg-primary-container/60 text-primary">
                                <AppIcon :name="f.icon" class="text-2xl" />
                            </div>

                            <!-- Title -->
                            <h3 class="text-base font-bold text-on-surface leading-snug group-hover:text-primary transition-colors">
                                {{ f.title }}
                            </h3>

                            <!-- Description -->
                            <p class="mt-1.5 text-sm text-on-surface-variant leading-relaxed">
                                {{ f.desc }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Workflow Steps Section · Vertical Stepper + Side Panel -->
            <section id="alur" class="alur-section reveal-group relative overflow-hidden border-t border-outline-variant/30 bg-surface scroll-mt-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10 space-y-10 py-12 sm:py-14">
                <!-- Decorative ambient orbs -->
                <div class="ambient-circle absolute -left-32 top-0 size-80 rounded-full bg-primary/5 blur-3xl pointer-events-none" />
                <div class="ambient-circle absolute -right-32 bottom-0 size-72 rounded-full bg-secondary/5 blur-3xl pointer-events-none" />

                    <!-- Header -->
                    <div class="text-center space-y-4 max-w-3xl mx-auto">
                        <div class="inline-flex items-center gap-2 rounded-full border border-secondary/30 bg-secondary-container/40 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-secondary">
                            <AppIcon name="route" class="text-xs" />
                            Alur Kerja Sistem
                        </div>
                        <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight leading-tight">
                            <span class="bg-gradient-to-br from-primary via-primary-container to-secondary bg-clip-text text-transparent">Tahapan Implementasi Sistematis</span>
                        </h2>
                        <p class="text-on-surface-variant text-sm sm:text-base leading-relaxed">
                            Tahapan kerja yang terdokumentasi, didampingi tim teknis berpengalaman, dari onboarding hingga pelaporan resmi ke Pemerintah Kabupaten.
                        </p>
                        <!-- Quick info chips -->
                        <div class="flex flex-wrap items-center justify-center gap-2 pt-1">
                            <div class="inline-flex items-center gap-2 rounded-full border border-outline-variant/50 bg-surface-container-lowest px-3 py-1.5 text-xs font-semibold text-on-surface-variant">
                                <AppIcon name="schedule" class="text-base text-primary" />
                                Rata-rata 14–21 hari
                            </div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-outline-variant/50 bg-surface-container-lowest px-3 py-1.5 text-xs font-semibold text-on-surface-variant">
                                <AppIcon name="groups" class="text-base text-secondary" />
                                Pendamping Tim Teknis
                            </div>
                        </div>
                    </div>

                    <!-- Stepper + Side Panel (desktop) / Accordion (mobile) -->
                    <div class="grid gap-5 lg:grid-cols-12 lg:gap-6">
                        <!-- LEFT · Vertical Stepper -->
                        <ol class="alur-stepper relative lg:col-span-5 space-y-3">
                            <li
                                v-for="(step, idx) in steps"
                                :key="step.num"
                                class="alur-stepper-item group relative"
                            >
                                <button
                                    type="button"
                                    @click="setActiveStep(idx)"
                                    :aria-pressed="activeStep === idx"
                                    class="alur-step-btn relative flex w-full items-center gap-4 rounded-2xl p-4 text-left transition-all duration-400 ring-1 ring-inset ring-transparent"
                                    :class="activeStep === idx
                                        ? 'bg-surface-container-lowest shadow-lg shadow-primary/10 ring-primary/30'
                                        : 'bg-surface-container-lowest/60 hover:bg-surface-container-lowest hover:ring-outline-variant/40'"
                                >
                                    <!-- Numbered circle node -->
                                    <div class="relative shrink-0">
                                        <div
                                            class="grid size-14 place-items-center rounded-full text-base font-black transition-all duration-500 ring-1 ring-inset ring-surface"
                                            :class="activeStep === idx
                                                ? (idx === steps.length - 1
                                                    ? 'bg-gradient-to-br from-secondary to-primary-container text-secondary-fixed shadow-lg shadow-secondary/30 scale-110'
                                                    : 'bg-gradient-to-br from-primary to-primary-container text-on-primary shadow-lg shadow-primary/30 scale-110')
                                                : 'bg-surface-container text-on-surface-variant'"
                                        >
                                            <AppIcon :name="step.icon" class="text-xl" />
                                        </div>
                                        <!-- Step number badge -->
                                        <span
                                            class="absolute -top-1 -right-1 grid size-5 place-items-center rounded-full text-[9px] font-black ring-2 ring-surface transition-all duration-300"
                                            :class="activeStep === idx
                                                ? 'bg-secondary text-on-secondary'
                                                : 'bg-outline-variant/30 text-on-surface-variant'"
                                        >
                                            {{ step.num }}
                                        </span>
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-1 min-w-0 py-1">
                                        <h3
                                            class="text-sm sm:text-base font-black leading-tight transition-colors mb-1"
                                            :class="activeStep === idx ? 'text-primary' : 'text-on-surface'"
                                        >
                                            {{ step.title }}
                                        </h3>
                                        <p
                                            class="text-[11px] sm:text-xs text-on-surface-variant leading-snug line-clamp-2 transition-colors"
                                        >
                                            {{ step.desc }}
                                        </p>
                                    </div>
                                </button>
                            </li>
                        </ol>

                        <!-- RIGHT · Side Panel Detail -->
                        <div class="lg:col-span-7">
                            <div
                                v-for="(step, idx) in steps"
                                :key="`panel-${step.num}`"
                                class="alur-panel"
                                :class="activeStep === idx ? 'alur-panel-active' : 'alur-panel-hidden'"
                                :aria-hidden="activeStep !== idx"
                            >
                                <!-- Panel card -->
                                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-surface-container-lowest via-surface-container-low to-surface-container-lowest p-5 sm:p-6 shadow-xl shadow-outline-variant/15 ring-1 ring-inset ring-outline-variant/40">

                                    <!-- Background ambient glow -->
                                    <div class="absolute -right-20 -top-20 size-60 rounded-full bg-primary/8 blur-3xl pointer-events-none" />
                                    <div class="absolute -left-16 -bottom-16 size-48 rounded-full bg-secondary/8 blur-3xl pointer-events-none" />

                                    <!-- Top row: icon hero + step chip -->
                                    <div class="relative z-10 flex items-start justify-between gap-4 mb-5">
                                        <div class="relative">
                                            <div class="absolute inset-0 -m-2 rounded-3xl bg-primary/20 blur-xl opacity-50" />
                                            <div
                                                class="relative grid size-14 sm:size-16 place-items-center rounded-2xl shadow-2xl transition-all duration-500"
                                                :class="idx === steps.length - 1
                                                    ? 'bg-gradient-to-br from-secondary via-primary-container to-primary text-secondary-fixed shadow-secondary/30'
                                                    : 'bg-gradient-to-br from-primary via-primary-container to-secondary text-on-primary shadow-primary/30'"
                                            >
                                                <div class="absolute inset-1.5 rounded-xl border border-white/25" />
                                                <AppIcon :name="step.icon" class="text-2xl sm:text-3xl relative z-10" />
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="inline-flex items-center gap-1.5 rounded-full bg-primary-container/50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-primary">
                                                Step {{ step.num }}
                                            </div>
                                            <p class="mt-2 text-[10px] font-bold uppercase tracking-[0.2em] text-secondary">
                                                {{ step.role }}
                                            </p>
                                            <p class="mt-1 text-xs font-semibold text-on-surface-variant">
                                                <AppIcon name="schedule" class="text-sm align-middle mr-0.5" />
                                                {{ step.duration }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Title + Desc -->
                                    <div class="relative z-10 space-y-2 mb-5">
                                        <h3 class="text-xl sm:text-2xl font-black tracking-tight text-on-surface leading-tight">
                                            {{ step.title }}
                                        </h3>
                                        <p class="text-sm sm:text-base text-on-surface-variant leading-relaxed">
                                            {{ step.desc }}
                                        </p>
                                    </div>

                                    <!-- Deliverables -->
                                    <div class="relative z-10 rounded-2xl border border-primary/15 bg-primary-container/25 p-4">
                                        <div class="flex items-center gap-2 mb-2.5">
                                            <div class="grid size-6 place-items-center rounded-full bg-primary text-on-primary">
                                                <AppIcon name="check" class="text-sm" />
                                            </div>
                                            <p class="text-xs font-black uppercase tracking-wider text-primary">Deliverables</p>
                                        </div>
                                        <ul class="grid gap-2 sm:grid-cols-2">
                                            <li
                                                v-for="(item, i) in step.output"
                                                :key="i"
                                                class="flex items-start gap-2 text-xs sm:text-sm text-on-surface"
                                            >
                                                <AppIcon name="check_circle" class="text-base text-secondary shrink-0 mt-0.5" />
                                                <span class="leading-snug">{{ item }}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <!-- Bottom nav -->
                                    <div class="relative z-10 mt-5 flex items-center justify-between gap-3 pt-4 border-t border-outline-variant/30">
                                        <button
                                            v-if="idx > 0"
                                            type="button"
                                            @click="setActiveStep(idx - 1)"
                                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-xs font-bold text-on-surface-variant shadow-sm ring-1 ring-inset ring-outline-variant/30 hover:text-primary hover:bg-primary-container/30 hover:-translate-y-0.5 hover:shadow-md hover:ring-primary/40 active:scale-[0.97] active:shadow-sm transition-all duration-200"
                                        >
                                            <AppIcon name="arrow_forward" class="text-sm rotate-180" />
                                            <span>Step {{ steps[idx - 1].num }}</span>
                                        </button>
                                        <span v-else class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant/60">
                                            Tahap Awal
                                        </span>
                                        <button
                                            v-if="idx < steps.length - 1"
                                            type="button"
                                            @click="setActiveStep(idx + 1)"
                                            class="inline-flex items-center gap-1.5 rounded-full bg-primary px-4 py-2 text-xs font-bold text-on-primary shadow-md shadow-primary/20 hover:shadow-lg hover:bg-secondary transition-all"
                                        >
                                            <span>Lanjut Step {{ steps[idx + 1].num }}</span>
                                            <AppIcon name="arrow_forward" class="text-sm" />
                                        </button>
                                        <a
                                            v-else
                                            href="#kontak"
                                            @click.prevent="smoothScrollTo('kontak')"
                                            class="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-primary to-secondary px-4 py-2 text-xs font-bold text-on-primary shadow-md shadow-primary/30 hover:shadow-lg hover:shadow-primary/40 hover:-translate-y-0.5 active:scale-[0.97] active:shadow-sm transition-all duration-200"
                                        >
                                            <AppIcon name="rocket_launch" class="text-sm" />
                                            <span>Mulai Sekarang</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom helper strip -->
                    <div class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-dashed border-primary/30 bg-primary-container/20 p-4 sm:flex-row sm:p-5">
                        <div class="flex items-center gap-3">
                            <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary text-on-primary shadow-md shadow-primary/30">
                                <AppIcon name="auto_awesome" class="text-lg" />
                            </div>
                            <div>
                                <p class="text-sm font-bold text-on-surface">Setiap tahap disertai pendampingan langsung</p>
                                <p class="text-xs text-on-surface-variant mt-0.5">Tim teknis akan hadir secara daring atau luring sesuai kebutuhan BUMDesma Anda.</p>
                            </div>
                        </div>
                        <a href="#kontak" @click.prevent="smoothScrollTo('kontak')" class="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-xs font-bold text-on-primary shadow-md shadow-primary/20 transition-all duration-300 hover:scale-[1.02] hover:shadow-lg hover:bg-secondary shrink-0">
                            <AppIcon name="rocket_launch" class="text-base" />
                            Mulai Sekarang
                            <AppIcon name="arrow_forward" class="text-base" />
                        </a>
                    </div>
                </div>
            </section>

            <!-- FAQ Section · Split 2-kolom -->
            <section id="faq" class="faq-section reveal-group relative overflow-hidden border-t border-outline-variant/30 bg-surface-container-lowest scroll-mt-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10 py-12 sm:py-14">
                <!-- Soft decorative orbs -->
                <div class="ambient-circle absolute -right-32 top-20 size-80 rounded-full bg-primary/5 blur-3xl pointer-events-none" />
                <div class="ambient-circle absolute -left-32 bottom-20 size-72 rounded-full bg-secondary/5 blur-3xl pointer-events-none" />

                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12 lg:gap-6">
                        <!-- LEFT · FAQ accordion list -->
                        <div class="order-1 lg:col-span-7 space-y-2">
                            <div
                                v-for="(faq, idx) in faqs"
                                :key="idx"
                                class="reveal-item faq-item group overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm ring-1 ring-inset ring-outline-variant/30 transition-all duration-300 hover:shadow-lg hover:shadow-primary/15 hover:ring-primary/40"
                                :class="{ 'faq-item-active ring-primary shadow-md shadow-primary/15': activeFaq === idx }"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-4 p-4 text-left transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-inset"
                                    :aria-expanded="activeFaq === idx"
                                    @click="toggleFaq(idx)"
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="grid size-8 shrink-0 place-items-center rounded-lg text-xs font-black tabular-nums transition-all duration-300"
                                            :class="activeFaq === idx ? 'bg-primary text-on-primary' : 'bg-primary-container text-primary group-hover:bg-primary group-hover:text-on-primary'">
                                            {{ String(idx + 1).padStart(2, '0') }}
                                        </div>
                                        <span class="text-sm sm:text-base font-bold pt-1"
                                            :class="activeFaq === idx ? 'text-primary' : 'text-on-surface group-hover:text-primary transition-colors'">
                                            {{ faq.q }}
                                        </span>
                                    </div>
                                    <span
                                        class="grid size-8 shrink-0 place-items-center rounded-full transition-all duration-300"
                                        :class="activeFaq === idx ? 'bg-primary text-on-primary rotate-180' : 'bg-surface-container text-primary group-hover:bg-primary-container'"
                                    >
                                        <AppIcon name="expand_more" class="text-lg" />
                                    </span>
                                </button>
                                <div
                                    class="faq-collapse grid transition-all duration-400 ease-out"
                                    :class="activeFaq === idx ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                                >
                                    <div class="overflow-hidden">
                                        <div class="faq-answer relative ml-12 px-4 pb-4 sm:ml-11">
                                            <!-- Left accent bar -->
                                            <div class="absolute left-4 top-0 bottom-4 w-0.5 bg-gradient-to-b from-primary to-secondary rounded-full sm:left-4" />
                                            <p class="pl-4 text-sm text-on-surface-variant leading-relaxed">
                                                {{ faq.a }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT · Info panel -->
                        <div class="order-2 lg:col-span-5 space-y-4">
                            <div class="space-y-3">
                                <div class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary-container/40 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-primary">
                                    <span class="grid size-5 place-items-center rounded-full bg-primary text-on-primary">
                                        <AppIcon name="forum" class="text-xs" />
                                    </span>
                                    Tanya Jawab
                                </div>
                                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-primary tracking-tight leading-tight">
                                    Pertanyaan yang <span class="bg-gradient-to-br from-primary to-secondary bg-clip-text text-transparent">Sering Diajukan</span>
                                </h2>
                                <p class="text-on-surface-variant text-sm sm:text-base leading-relaxed">
                                    Jawaban ringkas atas pertanyaan yang paling sering diajukan terkait platform siupk Next, kepatuhan regulasi, dan implementasi sistem.
                                </p>
                            </div>

                            <!-- Quick stat chips -->
                            <div class="flex flex-wrap gap-2">
                                <div class="inline-flex items-center gap-2 rounded-full bg-surface-container-low px-3 py-1.5 text-xs font-semibold text-on-surface-variant border border-outline-variant/50">
                                    <AppIcon name="schedule" class="text-base text-secondary" />
                                    Respons Satu Hari Kerja
                                </div>
                                <div class="inline-flex items-center gap-2 rounded-full bg-surface-container-low px-3 py-1.5 text-xs font-semibold text-on-surface-variant border border-outline-variant/50">
                                    <AppIcon name="verified_user" class="text-base text-primary" />
                                    Tim Teknis Tersertifikasi
                                </div>
                            </div>

                            <!-- Trust mini-card -->
                            <div class="rounded-2xl bg-surface-container-lowest p-3 flex items-center gap-3 shadow-md shadow-outline-variant/10 ring-1 ring-inset ring-outline-variant/30">
                                <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-secondary-container text-on-secondary-container">
                                    <AppIcon name="shield_lock" class="text-xl" />
                                </div>
                                <div class="text-xs">
                                    <p class="font-bold text-on-surface">Data Anda Aman & Terisolasi</p>
                                    <p class="text-on-surface-variant leading-snug mt-0.5">Setiap BUMDesma punya database shard terpisah & terenkripsi.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Bottom CTA Section · Rocket-launch gradient -->
            <section class="bottom-cta relative overflow-hidden border-t border-primary-deep/30 bg-gradient-to-b from-primary-container via-primary to-primary-deep text-on-primary py-12 sm:py-16">
                <!-- Top divider line -->
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary-fixed/40 to-transparent" />
                <!-- Rocket-flame burst (top half) -->
                <div class="pointer-events-none absolute inset-x-0 top-0 h-1/2 bg-gradient-to-b from-secondary/40 via-secondary/10 to-transparent" />
                <!-- Center rocket flame orb -->
                <div class="pointer-events-none absolute left-1/2 top-0 -translate-x-1/2 size-40 rounded-full bg-secondary-fixed/40 blur-3xl animate-cta-rocket-flicker" />
                <!-- Left orb -->
                <div class="pointer-events-none absolute -left-20 top-1/3 size-72 rounded-full bg-secondary/40 blur-3xl animate-cta-orb-drift" />
                <!-- Right orb -->
                <div class="pointer-events-none absolute -right-20 bottom-1/4 size-72 rounded-full bg-primary-fixed/25 blur-3xl animate-cta-orb-drift" style="animation-duration: 22s; animation-delay: -6s" />
                <!-- Bottom shadow gradient -->
                <div class="pointer-events-none absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-primary-deep/80 to-transparent" />
                <!-- Bottom divider line -->
                <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-primary-fixed/30 to-transparent" />

                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
                    <div class="text-center space-y-5">
                        <!-- Eyebrow pill · gradient -->
                        <div class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-primary-fixed to-secondary-fixed px-3 py-1 text-[10px] font-bold uppercase tracking-[0.2em] text-primary-deep ring-1 ring-inset ring-white/30 shadow-lg shadow-primary-deep/40">
                            <AppIcon name="rocket_launch" class="text-xs" />
                            <span>Mulai Sekarang</span>
                        </div>

                        <!-- Heading -->
                        <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black leading-[1.15] tracking-tight max-w-2xl mx-auto">
                            Tata Kelola Dana Bergulir dalam
                            <span class="block bg-gradient-to-r from-secondary-fixed via-primary-fixed to-secondary-fixed bg-clip-text text-transparent">Satu Platform Terpadu</span>
                        </h2>

                        <!-- Body -->
                        <p class="mx-auto max-w-xl text-sm sm:text-base leading-relaxed text-primary-fixed-dim">
                            Portal operasional untuk pengurus BUMDesma, atau jadwalkan demonstrasi bersama tim teknis untuk instansi pembina di tingkat Kabupaten.
                        </p>

                        <!-- Buttons -->
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                            <Link href="/login">
                                <AppButton variant="secondary" size="large" icon="login" class="!rounded-full !px-6 !py-3 font-bold shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-0.5">
                                    Masuk Portal Operasional
                                </AppButton>
                            </Link>
                            <a href="#kontak" @click.prevent="smoothScrollTo('kontak')">
                                <AppButton variant="ghost" size="large" icon="event_available" class="!rounded-full !px-6 !py-3 font-semibold !bg-white/10 !text-on-primary hover:!bg-white/20 transition-all duration-300 hover:-translate-y-0.5">
                                    Jadwalkan Demonstrasi
                                </AppButton>
                            </a>
                        </div>

                        <!-- Mini stats inline -->
                        <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 pt-3 text-[11px] font-semibold text-primary-fixed-dim">
                            <span class="inline-flex items-center gap-1.5">
                                <AppIcon name="bolt" class="text-xs text-secondary-fixed" />
                                Onboarding 7 Hari
                            </span>
                            <span class="hidden sm:inline opacity-40">•</span>
                            <span class="inline-flex items-center gap-1.5">
                                <AppIcon name="support_agent" class="text-xs text-secondary-fixed" />
                                Pendamping Sen–Jum
                            </span>
                            <span class="hidden sm:inline opacity-40">•</span>
                            <span class="inline-flex items-center gap-1.5">
                                <AppIcon name="shield_lock" class="text-xs text-secondary-fixed" />
                                Basis Data Berisolasi
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-outline-variant/60 bg-surface-container-lowest py-4 sm:py-5">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-3">
                <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-primary to-primary-container text-on-primary shadow-md">
                            <AppIcon name="account_balance" class="text-2xl" />
                        </div>
                        <div>
                            <p class="text-base font-black tracking-tight text-primary">siupk <span class="text-secondary">Next</span></p>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-outline">BUMDesma & LKD Financial Information System</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-semibold text-outline">
                        <a href="#fitur" @click.prevent="smoothScrollTo('fitur')" class="hover:text-primary transition-colors">Fitur</a>
                        <a href="#alur" @click.prevent="smoothScrollTo('alur')" class="hover:text-primary transition-colors">Alur Kerja</a>
                        <a href="#faq" @click.prevent="smoothScrollTo('faq')" class="hover:text-primary transition-colors">Tanya Jawab</a>
                        <Link href="/login" class="hover:text-primary transition-colors">Portal Login</Link>
                    </div>
                </div>

                <div class="pt-2 border-t border-outline-variant/60 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-outline">
                    <p>&copy; 2026 siupk Next &mdash; BUMDesma & LKD Financial Information System.</p>
                    <p class="inline-flex items-center gap-1.5">
                        <AppIcon name="verified" class="text-sm text-secondary" />
                        <span>Sesuai PP No. 11/2021 & SAK EP / ETAP</span>
                    </p>
                </div>
            </div>
        </footer>

        <!-- Floating Scroll-to-Top Button -->
        <transition name="scroll-top">
            <button
                v-show="showScrollTop"
                type="button"
                aria-label="Kembali ke atas"
                class="group fixed bottom-6 right-6 z-50 grid size-12 place-items-center rounded-full bg-gradient-to-br from-primary via-primary to-primary-container text-on-primary shadow-lg shadow-primary/30 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-primary/50 active:scale-95 sm:bottom-8 sm:right-8 sm:size-14"
                @click="scrollToTop"
            >
                <AppIcon name="arrow_upward" class="text-xl transition-transform duration-300 group-hover:-translate-y-0.5 sm:text-2xl" />
                <span class="absolute inset-0 -z-10 animate-ping rounded-full bg-primary/30 opacity-0 group-hover:opacity-75" style="animation-duration: 1.5s" />
            </button>
        </transition>
    </div>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 220ms ease, transform 220ms ease;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@keyframes gradient-pan {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.hero-anim-title .bg-clip-text {
    animation: gradient-pan 8s ease-in-out infinite;
}

/* Trust strip · featured + grid reveal */
.trust-featured {
    animation: trust-rise 0.8s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.trust-card {
    animation: trust-rise 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.trust-card:nth-child(1) { animation-delay: 0.08s; }
.trust-card:nth-child(2) { animation-delay: 0.16s; }
.trust-card:nth-child(3) { animation-delay: 0.24s; }
.trust-card:nth-child(4) { animation-delay: 0.32s; }
@keyframes trust-rise {
    from { opacity: 0; transform: translateY(20px); filter: blur(4px); }
    to   { opacity: 1; transform: translateY(0); filter: blur(0); }
}

/* Alur · Vertical Stepper + Side Panel */
.alur-stepper-item {
    animation: alur-rise 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.alur-stepper-item:nth-child(1) { animation-delay: 0.05s; }
.alur-stepper-item:nth-child(2) { animation-delay: 0.12s; }
.alur-stepper-item:nth-child(3) { animation-delay: 0.19s; }
.alur-stepper-item:nth-child(4) { animation-delay: 0.26s; }
@keyframes alur-rise {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
.alur-step-btn {
    cursor: pointer;
    transition: background-color 0.4s ease, box-shadow 0.4s ease, transform 0.3s ease, ring-color 0.3s ease;
}
.alur-step-btn:hover {
    transform: translateX(2px);
}

/* Side panel · show/hide dengan fade + slight slide */
.alur-panel {
    display: none;
    opacity: 0;
    transform: translateY(8px);
    transition: opacity 0.45s ease, transform 0.45s ease;
}
.alur-panel-active {
    display: block;
    opacity: 1;
    transform: translateY(0);
}
.alur-panel-hidden {
    display: none;
    opacity: 0;
    transform: translateY(8px);
}

/* FAQ · custom collapse duration (400ms lebih lembut dari default 300ms) */
.faq-collapse {
    transition-duration: 400ms;
}
.faq-item {
    animation: faq-rise 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.faq-item:nth-child(1) { animation-delay: 0.05s; }
.faq-item:nth-child(2) { animation-delay: 0.12s; }
.faq-item:nth-child(3) { animation-delay: 0.19s; }
.faq-item:nth-child(4) { animation-delay: 0.26s; }
.faq-item:nth-child(5) { animation-delay: 0.33s; }
.faq-item:nth-child(6) { animation-delay: 0.40s; }
@keyframes faq-rise {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
.faq-item-active .faq-answer {
    animation: faq-answer-fade 0.5s ease-out 0.05s both;
}
@keyframes faq-answer-fade {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Bento Stats · entrance + reveal */
.bento-card {
    animation: bento-rise 0.8s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.bento-card:nth-child(1) { animation-delay: 0.05s; }
.bento-card:nth-child(2) { animation-delay: 0.15s; }
.bento-card:nth-child(3) { animation-delay: 0.25s; }
.bento-card:nth-child(4) { animation-delay: 0.35s; }

.bento-hero .bg-clip-text {
    background-size: 200% 200%;
    animation: gradient-pan 6s ease-in-out infinite;
}

.bento-progress {
    transition: width 1.8s cubic-bezier(0.22, 1, 0.36, 1);
    box-shadow: 0 0 12px rgba(52, 211, 153, 0.6);
}

@keyframes bento-rise {
    from {
        opacity: 0;
        transform: translateY(28px) scale(0.97);
        filter: blur(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
        filter: blur(0);
    }
}

/* Top banner · verified strip dengan animasi draw-in + loop */
.top-banner-bar {
    animation: banner-fade-in 0.55s ease-out 0.15s backwards;
}
.top-banner-badge {
    animation: banner-badge-pop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.5s backwards,
               banner-badge-breathe 4.5s ease-in-out 2s infinite;
    transform-origin: center;
}
.top-banner-icon {
    animation: banner-icon-glow 3s ease-in-out 2s infinite;
}
.banner-shield-path {
    stroke-dasharray: 80;
    stroke-dashoffset: 80;
    animation: banner-shield-draw 0.7s cubic-bezier(0.65, 0, 0.35, 1) 0.85s forwards;
    transform-origin: center;
    transform-box: fill-box;
}
.banner-shield-check {
    stroke-dasharray: 14;
    stroke-dashoffset: 14;
    animation: banner-shield-draw 0.4s cubic-bezier(0.65, 0, 0.35, 1) 1.4s forwards,
               banner-shield-check-pulse 2.6s ease-in-out 2.2s infinite;
    transform-origin: center;
    transform-box: fill-box;
}
.banner-shield-group {
    transform-origin: center;
    transform-box: fill-box;
    animation: banner-shield-tilt 6s ease-in-out 2.4s infinite;
}
.top-banner-badge::after {
    content: '';
    position: absolute;
    inset: -2px;
    border-radius: 6px;
    border: 1px solid currentColor;
    opacity: 0;
    animation: banner-ring-pulse 2.8s ease-out 1.9s infinite;
    pointer-events: none;
}
.banner-version-chip {
    animation: banner-chip-in 0.55s cubic-bezier(0.34, 1.56, 0.64, 1) 0.8s backwards,
               banner-chip-pulse 3.5s ease-in-out 2.5s infinite;
}
.banner-version-dot {
    animation: banner-badge-pop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) 1.3s backwards;
}
.banner-version-dot::before {
    content: '';
    position: absolute;
    inset: -3px;
    border-radius: 9999px;
    background: currentColor;
    opacity: 0;
    animation: banner-dot-ping 2s cubic-bezier(0, 0, 0.2, 1) 2s infinite;
    pointer-events: none;
}

@keyframes banner-fade-in {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0);    }
}
@keyframes banner-badge-pop {
    0%   { transform: scale(0);   opacity: 0; }
    60%  { transform: scale(1.2); opacity: 1; }
    100% { transform: scale(1);   opacity: 1; }
}
@keyframes banner-badge-breathe {
    0%, 100% { transform: scale(1);    }
    50%      { transform: scale(1.06); }
}
@keyframes banner-icon-glow {
    0%, 100% { background-color: rgba(255, 255, 255, 0.15); }
    50%      { background-color: rgba(255, 255, 255, 0.28); }
}
@keyframes banner-shield-draw {
    to { stroke-dashoffset: 0; }
}
@keyframes banner-shield-tilt {
    0%, 100% { transform: rotate(0deg); }
    50%      { transform: rotate(-6deg); }
}
@keyframes banner-shield-check-pulse {
    0%, 100% { transform: scale(1);   opacity: 1;   }
    50%      { transform: scale(1.18); opacity: 0.9; }
}
@keyframes banner-ring-pulse {
    0%   { opacity: 0.55; transform: scale(1); }
    100% { opacity: 0;    transform: scale(1.8); }
}
@keyframes banner-chip-in {
    0%   { opacity: 0; transform: translateX(8px) scale(0.9); }
    100% { opacity: 1; transform: translateX(0)   scale(1);   }
}
@keyframes banner-chip-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0); }
    50%      { box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.12); }
}
@keyframes banner-dot-ping {
    0%   { transform: scale(1);   opacity: 0.6; }
    80%  { transform: scale(2.4); opacity: 0;   }
    100% { transform: scale(2.4); opacity: 0;   }
}

/* Hero mockup · footer sync icon (custom arc SVG, smooth spin) */
.hero-sync-icon {
    animation: hero-sync-spin 6s linear infinite;
    transform-origin: 50% 50%;
}
@keyframes hero-sync-spin {
    to { transform: rotate(360deg); }
}

/* Hero mockup · cursor glow tracking inside card */
.hero-cursor-glow {
    opacity: 0;
    background: radial-gradient(
        420px circle at var(--mouse-x, 50%) var(--mouse-y, 50%),
        color-mix(in oklab, var(--color-primary) 18%, transparent),
        transparent 55%
    );
    transition: opacity 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.hero-cursor-border {
    opacity: 0;
    background: radial-gradient(
        320px circle at var(--mouse-x, 50%) var(--mouse-y, 50%),
        color-mix(in oklab, var(--color-primary) 35%, transparent),
        transparent 60%
    );
    -webkit-mask-image: linear-gradient(black, black), linear-gradient(black, black);
    mask-image: linear-gradient(black, black), linear-gradient(black, black);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    transition: opacity 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.is-cursor-active .hero-cursor-glow,
.is-cursor-active .hero-cursor-border {
    opacity: 1;
}
.hero-mockup-shell {
    --mouse-x: 50%;
    --mouse-y: 50%;
}

/* Scroll-to-top button transition */
.scroll-top-enter-active,
.scroll-top-leave-active {
    transition: opacity 250ms ease, transform 250ms ease;
}
.scroll-top-enter-from,
.scroll-top-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.9);
}

/* Mobile nav drawer transition */
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 200ms ease, transform 200ms ease;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@media (prefers-reduced-motion: reduce) {
    .top-banner-bar,
    .top-banner-badge,
    .top-banner-icon,
    .top-banner-badge::after,
    .banner-version-dot,
    .banner-version-dot::before,
    .banner-version-chip,
    .banner-shield-path,
    .banner-shield-check,
    .banner-shield-group {
        animation: none !important;
    }
    .banner-shield-path,
    .banner-shield-check {
        stroke-dashoffset: 0 !important;
    }
    .hero-cursor-glow,
    .hero-cursor-border {
        animation: none !important;
        transform: none !important;
        opacity: 0 !important;
    }
    .hero-sync-icon {
        animation: none !important;
    }
    .alur-stepper-item,
    .alur-panel {
        animation: none !important;
    }
    .alur-step-btn:hover {
        transform: none !important;
    }
    .animate-cta-orb-drift,
    .animate-cta-rocket-flicker {
        animation: none !important;
    }
}

/* Bottom CTA · rocket-launch animation keyframes */
@keyframes cta-orb-drift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50%      { transform: translate(15px, -20px) scale(1.1); }
}
@keyframes cta-rocket-flicker {
    0%, 100% { opacity: 0.6; transform: translate(-50%, 0) scale(1); }
    50%      { opacity: 1;   transform: translate(-50%, 8px) scale(1.08); }
}
.animate-cta-orb-drift {
    animation: cta-orb-drift 18s ease-in-out infinite;
}
.animate-cta-rocket-flicker {
    animation: cta-rocket-flicker 3s ease-in-out infinite;
}
</style>
