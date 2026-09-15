<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
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
const floatPill1Ref = ref(null);
const floatPill2Ref = ref(null);

const navLinks = [
    { id: 'fitur', label: 'Fitur Unggulan' },
    { id: 'alur', label: 'Alur Kerja' },
    { id: 'statistik', label: 'Capaian' },
    { id: 'faq', label: 'Tanya Jawab' },
];

const features = [
    {
        icon: 'account_balance',
        title: 'Pengelolaan Dana Bergulir',
        desc: 'Manajemen permohonan pinjaman kelompok SPP & UEP, verifikasi berjenjang, jadwal angsuran amortisasi, hingga mutasi kolektibilitas per pemanfaat.',
        badge: 'Lending Engine',
    },
    {
        icon: 'bar_chart',
        title: 'Konsolidasi Keuangan Kabupaten',
        desc: 'Portal pengawasan terpadu untuk Pemerintah Kabupaten (Dinas PMD & Inspektorat). Laporan Neraca, Laba Rugi, Buku Besar, dan CALK otomatis se-wilayah.',
        badge: 'Regency Portal',
    },
    {
        icon: 'smart_toy',
        title: 'AI Assistant & Pengetahuan Regulasi',
        desc: 'Asisten cerdas terintegrasi untuk analisis data pinjaman, proyeksi keuangan bulanan, serta konsultasi regulasi dan SOP BUMDesma.',
        badge: 'AI Intelligence',
    },
    {
        icon: 'qr_code_2',
        title: 'Otomatisasi Tagihan & QRIS',
        desc: 'Integrasi sistem pembayaran tagihan langganan otomatis via QRIS dan Virtual Account Bank nasional (BCA, BRI, Mandiri, BNI, dll).',
        badge: 'Auto Billing',
    },
    {
        icon: 'chat',
        title: 'Notifikasi WhatsApp Otomatis',
        desc: 'Pengiriman slip pencairan pinjaman, struk angsuran, dan notifikasi pengingat jatuh tempo langsung ke nomor WhatsApp pengurus & pemanfaat.',
        badge: 'WA Gateway',
    },
    {
        icon: 'shield',
        title: 'Keamanan Data & Isolasi Sharding',
        desc: 'Arsitektur basis data terisolasi untuk tiap BUMDesma, menjamin kerahasiaan data, integritas saldo pembukuan, dan performa tinggi.',
        badge: 'Enterprise Security',
    },
];

const stats = [
    { label: 'Kecamatan / BUMDesma Siap Terlayani', target: 500, suffix: '+', icon: 'location_city' },
    { label: 'Kelompok Pemanfaat Dikelola', target: 12500, suffix: '+', icon: 'groups', formatNumber: true },
    { label: 'Otomatisasi Jurnal & Buku Besar', target: 100, suffix: '%', icon: 'receipt_long' },
    { label: 'Tingkat Akurasi Laporan Keuangan', target: 99.9, suffix: '%', icon: 'verified', isDecimal: true },
];

const statCounters = ref(stats.map(() => 0));

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
    },
    {
        num: '02',
        title: 'Migrasi & Saldo Awal',
        desc: 'Fasilitas Import Wizard pintar untuk memindahkan data master kelompok, anggota, dan saldo awal dari sistem sebelumnya.',
        icon: 'database',
    },
    {
        num: '03',
        title: 'Operasional Harian',
        desc: 'Pencatatan pinjaman, pembayaran angsuran, kas/bank, dan jurnal akuntansi otomatis sesuai standar SAK Entitas Privat.',
        icon: 'monitoring',
    },
    {
        num: '04',
        title: 'Laporan & Pengawasan Pemda',
        desc: 'Penerbitan laporan resmi berkala untuk pertanggungjawaban musyawarah antar desa (MAD) dan monitoring dinas terkait.',
        icon: 'assessment',
    },
];

const trustLogos = [
    { label: 'PP No. 11/2021', sub: 'Regulasi' },
    { label: 'SAK EP / ETAP', sub: 'Standar Akuntansi' },
    { label: 'Database Sharding', sub: 'Isolasi Tenant' },
    { label: 'QRIS & VA Bank', sub: 'Payment Gateway' },
    { label: 'Ollama LLM', sub: 'AI Lokal' },
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

// 3D Parallax Tilt on Hero Mockup
function onHeroMouseMove(e) {
    if (!heroVisualRef.value || !heroCardRef.value) return;
    const rect = heroVisualRef.value.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const rotateX = ((y - centerY) / centerY) * -6;
    const rotateY = ((x - centerX) / centerX) * 6;
    const transX = ((x - centerX) / centerX) * 8;
    const transY = ((y - centerY) / centerY) * 8;

    gsap.to(heroCardRef.value, {
        rotateX, rotateY,
        x: transX * 0.5,
        y: transY * 0.5,
        transformPerspective: 1200,
        duration: 0.5,
        ease: 'power2.out',
    });

    if (floatPill1Ref.value) {
        gsap.to(floatPill1Ref.value, {
            x: transX * 1.4, y: transY * 1.4,
            duration: 0.55, ease: 'power2.out',
        });
    }

    if (floatPill2Ref.value) {
        gsap.to(floatPill2Ref.value, {
            x: -transX * 1.1, y: -transY * 1.1,
            duration: 0.55, ease: 'power2.out',
        });
    }
}

function onHeroMouseLeave() {
    if (!heroCardRef.value) return;
    gsap.to(heroCardRef.value, {
        rotateX: 0, rotateY: 0, x: 0, y: 0,
        duration: 0.9, ease: 'elastic.out(1, 0.6)',
    });
    if (floatPill1Ref.value) gsap.to(floatPill1Ref.value, { x: 0, y: 0, duration: 0.9, ease: 'power3.out' });
    if (floatPill2Ref.value) gsap.to(floatPill2Ref.value, { x: 0, y: 0, duration: 0.9, ease: 'power3.out' });
}

function onFeatureCardHover(e, enter) {
    const icon = e.currentTarget.querySelector('.feature-icon-box');
    if (!icon) return;
    gsap.to(icon, {
        scale: enter ? 1.15 : 1,
        rotate: enter ? 4 : 0,
        duration: 0.35,
        ease: 'back.out(2)',
    });
}

let observerInstance = null;

onMounted(() => {
    nextTick(() => {
        const heroTl = gsap.timeline({ defaults: { ease: 'power3.out' } });

        heroTl
            .fromTo('.top-banner-bar', { y: -20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.5 })
            .fromTo('.nav-container', { y: -25, opacity: 0 }, { y: 0, opacity: 1, duration: 0.6 }, '-=0.2')
            .fromTo('.hero-badge-item', { opacity: 0, scale: 0.85, y: 15 }, { opacity: 1, scale: 1, y: 0, duration: 0.6, ease: 'back.out(1.7)' }, '-=0.3')
            .fromTo('.hero-anim-title', { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: 0.75, ease: 'power4.out' }, '-=0.4')
            .fromTo('.hero-anim-desc', { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.65 }, '-=0.4')
            .fromTo('.hero-anim-actions', { opacity: 0, y: 20, scale: 0.96 }, { opacity: 1, y: 0, scale: 1, duration: 0.6, ease: 'back.out(1.4)' }, '-=0.4')
            .fromTo('.hero-anim-trust > *', { opacity: 0, y: 15 }, { opacity: 1, y: 0, duration: 0.5, stagger: 0.08 }, '-=0.3')
            .fromTo('.hero-preview-card', { opacity: 0, scale: 0.92, y: 40 }, { opacity: 1, scale: 1, y: 0, duration: 0.95, ease: 'back.out(1.3)' }, '-=0.7');

        // Ambient blobs
        gsap.to('.ambient-blob-1', {
            scale: 1.2, rotate: 25, x: 20, y: -15,
            duration: 8, repeat: -1, yoyo: true, ease: 'sine.inOut',
        });
        gsap.to('.ambient-blob-2', {
            scale: 1.15, rotate: -20, x: -25, y: 20,
            duration: 10, repeat: -1, yoyo: true, ease: 'sine.inOut', delay: 1,
        });

        // Floating pills idle animation
        gsap.to('.float-pill-1', { y: -10, duration: 3.6, repeat: -1, yoyo: true, ease: 'sine.inOut' });
        gsap.to('.float-pill-2', { y: 12, duration: 4.2, repeat: -1, yoyo: true, ease: 'sine.inOut', delay: 0.5 });

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
    });
});

onUnmounted(() => {
    if (observerInstance) observerInstance.disconnect();
});
</script>

<template>
    <Head title="siupk Next - Sistem Informasi Dana Bergulir Masyarakat" />

    <div class="min-h-screen bg-surface font-sans text-on-surface antialiased scroll-smooth selection:bg-primary selection:text-on-primary">
        <!-- Top Banner -->
        <div class="top-banner-bar relative overflow-hidden bg-gradient-to-r from-primary via-primary-container to-primary px-4 py-2.5 text-center text-xs font-semibold tracking-wide text-on-primary shadow-sm">
            <div class="ambient-circle absolute -right-16 -top-8 size-32 rounded-full bg-white/10 blur-2xl pointer-events-none" />
            <div class="relative mx-auto flex max-w-7xl items-center justify-center gap-2">
                <AppBadge tone="primary" class="border border-white/20 font-extrabold">RESMI</AppBadge>
                <span>Sistem Tata Kelola Keuangan & Dana Bergulir BUMDesma LKD Sesuai Regulasi PP No. 11/2021</span>
            </div>
        </div>

        <!-- Sticky Header / Navbar -->
        <header class="nav-container sticky top-0 z-40 border-b border-outline-variant/40 bg-surface-container-lowest/85 backdrop-blur-xl transition-all">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
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

                <!-- Desktop Navigation Links -->
                <nav class="hidden items-center gap-1 md:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.id"
                        :href="`#${link.id}`"
                        @click.prevent="smoothScrollTo(link.id)"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary"
                    >{{ link.label }}</a>
                </nav>

                <!-- Header Actions -->
                <div class="flex items-center gap-3">
                    <Link href="/login" class="hidden sm:inline-flex">
                        <AppButton variant="secondary" size="compact" icon="login" class="!rounded-full font-bold shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
                            Masuk Portal
                        </AppButton>
                    </Link>

                    <button
                        type="button"
                        class="grid size-10 place-items-center rounded-lg border border-outline-variant text-on-surface md:hidden transition hover:bg-surface-container-high"
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
                                <AppButton variant="primary" size="medium" icon="login" class="w-full !rounded-full font-bold">
                                    Masuk ke Portal
                                </AppButton>
                            </Link>
                        </div>
                    </nav>
                </div>
            </transition>
        </header>

        <!-- Hero Section -->
        <main>
            <section class="relative overflow-hidden bg-gradient-to-b from-surface-container-lowest via-surface-container-low/30 to-surface py-8 sm:py-10 lg:py-12">
                <!-- Ambient glow blobs -->
                <div class="ambient-blob-1 absolute -top-24 -left-24 size-[28rem] rounded-full bg-primary/10 blur-3xl pointer-events-none -z-10" />
                <div class="ambient-blob-2 absolute top-1/2 -right-24 size-[32rem] rounded-full bg-secondary/10 blur-3xl pointer-events-none -z-10" />
                <!-- Subtle grid pattern overlay -->
                <div class="absolute inset-0 -z-10 opacity-[0.035] pointer-events-none" style="background-image: linear-gradient(to right, currentColor 1px, transparent 1px), linear-gradient(to bottom, currentColor 1px, transparent 1px); background-size: 56px 56px;" />

                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-14 lg:grid-cols-12 lg:gap-10">
                        <!-- Left Hero Column -->
                        <div class="lg:col-span-7 space-y-7 text-center lg:text-left">
                            <div class="hero-badge-item inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-4 py-1.5 text-xs font-semibold text-primary shadow-sm backdrop-blur">
                                <span class="grid size-5 place-items-center rounded-full bg-secondary text-on-secondary">
                                    <AppIcon name="verified" class="text-[11px]" />
                                </span>
                                <span>Platform Dana Bergulir & Akuntansi SAK EP Generasi Baru</span>
                            </div>

                            <h1 class="hero-anim-title text-4xl sm:text-5xl lg:text-6xl xl:text-[3.75rem] font-black tracking-tight text-primary leading-[1.1]">
                                Transformasi Digital Keuangan
                                <span class="block mt-2 bg-gradient-to-r from-primary via-secondary to-primary bg-clip-text text-transparent bg-[length:200%_auto]">
                                    BUMDesma & LKD Indonesia
                                </span>
                            </h1>

                            <p class="hero-anim-desc text-base sm:text-lg text-on-surface-variant max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                                Solusi terintegrasi untuk pengelolaan pinjaman bergulir, pembukuan akuntansi standar SAK Entitas Privat, penerbitan kuitansi WhatsApp, dan pelaporan konsolidasi Pemerintah Kabupaten secara real-time.
                            </p>

                            <!-- CTA Buttons -->
                            <div class="hero-anim-actions flex flex-wrap items-center justify-center lg:justify-start gap-3 pt-2">
                                <Link href="/login">
                                    <AppButton variant="primary" size="large" icon="login" class="!rounded-full font-bold shadow-lg shadow-primary/25 hover:shadow-xl hover:shadow-primary/30 transition-all duration-300 hover:-translate-y-0.5">
                                        Masuk ke Dashboard
                                    </AppButton>
                                </Link>
                                <a href="#fitur" @click.prevent="smoothScrollTo('fitur')">
                                    <AppButton variant="secondary" size="large" icon="explore" class="!rounded-full font-semibold hover:-translate-y-0.5 transition-all duration-300">
                                        Pelajari Fitur
                                    </AppButton>
                                </a>
                            </div>

                            <!-- Trust Pills -->
                            <div class="hero-anim-trust pt-6 flex flex-wrap items-center justify-center lg:justify-start gap-3">
                                <div class="inline-flex items-center gap-2 rounded-full border border-outline-variant/60 bg-surface-container-lowest/80 px-3.5 py-1.5 text-xs font-semibold text-on-surface-variant backdrop-blur">
                                    <AppIcon name="check_circle" class="text-base text-secondary" />
                                    <span>PP No. 11/2021</span>
                                </div>
                                <div class="inline-flex items-center gap-2 rounded-full border border-outline-variant/60 bg-surface-container-lowest/80 px-3.5 py-1.5 text-xs font-semibold text-on-surface-variant backdrop-blur">
                                    <AppIcon name="check_circle" class="text-base text-secondary" />
                                    <span>SAK EP / ETAP</span>
                                </div>
                                <div class="inline-flex items-center gap-2 rounded-full border border-outline-variant/60 bg-surface-container-lowest/80 px-3.5 py-1.5 text-xs font-semibold text-on-surface-variant backdrop-blur">
                                    <AppIcon name="check_circle" class="text-base text-secondary" />
                                    <span>Isolasi Sharding</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Hero Visual with Interactive 3D Tilt Parallax -->
                        <div
                            ref="heroVisualRef"
                            class="lg:col-span-5 relative flex justify-center perspective-[1200px]"
                            @mousemove="onHeroMouseMove"
                            @mouseleave="onHeroMouseLeave"
                        >
                            <!-- Floating Pill 1: Collectibility -->
                            <div
                                ref="floatPill1Ref"
                                class="float-pill-1 absolute -top-5 -left-2 sm:-left-6 z-30 flex items-center gap-3 rounded-2xl border border-secondary/20 bg-surface-container-lowest/95 backdrop-blur-md px-4 py-3 shadow-xl shadow-secondary/10 text-xs font-bold text-on-surface transition-transform will-change-transform"
                            >
                                <div class="grid size-9 place-items-center rounded-xl bg-secondary/15 text-secondary">
                                    <AppIcon name="trending_up" class="text-xl" />
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase font-extrabold tracking-wider text-outline">Kolektibilitas</p>
                                    <p class="text-secondary font-black text-sm">98,6% Lancar</p>
                                </div>
                            </div>

                            <!-- Floating Pill 2: Portfolio Volume -->
                            <div
                                ref="floatPill2Ref"
                                class="float-pill-2 absolute -bottom-5 -right-2 sm:-right-4 z-30 flex items-center gap-3 rounded-2xl border border-primary/20 bg-surface-container-lowest/95 backdrop-blur-md px-4 py-3 shadow-xl shadow-primary/10 text-xs font-bold text-on-surface transition-transform will-change-transform"
                            >
                                <div class="grid size-9 place-items-center rounded-xl bg-primary/15 text-primary">
                                    <AppIcon name="account_balance_wallet" class="text-xl" />
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase font-extrabold tracking-wider text-outline">Portofolio</p>
                                    <p class="text-primary font-black text-sm">Rp 1,48 Milyar</p>
                                </div>
                            </div>

                            <!-- Main Mockup Dashboard Card -->
                            <div ref="heroCardRef" class="hero-preview-card w-full max-w-md will-change-transform transform-gpu">
                                <div class="relative overflow-hidden rounded-3xl border border-outline-variant/40 bg-surface-container-lowest/95 backdrop-blur-xl p-6 shadow-2xl shadow-primary/10 space-y-4">
                                    <!-- Top gradient accent bar -->
                                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-primary via-secondary to-primary-container" />

                                    <!-- Mock App Header -->
                                    <div class="flex items-center justify-between pb-3 border-b border-outline-variant/50">
                                        <div class="flex items-center gap-2.5">
                                            <div class="flex gap-1.5">
                                                <div class="size-2.5 rounded-full bg-error/70" />
                                                <div class="size-2.5 rounded-full bg-amber-400/70" />
                                                <div class="size-2.5 rounded-full bg-secondary/70" />
                                            </div>
                                            <span class="ml-2 text-xs font-black text-primary truncate">BUMDesma Mandiri Sejahtera</span>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary/15 px-2.5 py-0.5 text-[10px] font-bold text-secondary">
                                            <span class="size-1.5 rounded-full bg-secondary animate-pulse" /> Live
                                        </span>
                                    </div>

                                    <!-- Mock Stats Cards -->
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="rounded-2xl bg-gradient-to-br from-primary-fixed/60 to-surface-container-low p-3.5 space-y-1.5 border border-primary/10 hover:border-primary/30 transition-colors">
                                            <span class="text-[10px] font-bold text-outline uppercase tracking-wider">Outstanding Piutang</span>
                                            <p class="text-base sm:text-lg font-black text-primary">Rp 1,48 M</p>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-secondary">
                                                <AppIcon name="trending_up" class="text-sm" /> +12,4% bln ini
                                            </span>
                                        </div>
                                        <div class="rounded-2xl bg-gradient-to-br from-secondary-container/30 to-surface-container-low p-3.5 space-y-1.5 border border-secondary/10 hover:border-secondary/30 transition-colors">
                                            <span class="text-[10px] font-bold text-outline uppercase tracking-wider">Kesehatan Usaha</span>
                                            <p class="text-base sm:text-lg font-black text-secondary">Predikat Sehat</p>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-primary">
                                                <AppIcon name="verified" class="text-sm" /> Skor 94,8 / 100
                                            </span>
                                        </div>
                                    </div>

                                    <!-- AI Assistant Mock -->
                                    <div class="rounded-2xl border border-primary/20 bg-gradient-to-br from-primary-fixed/40 to-primary-container/5 p-3.5 flex items-start gap-3 shadow-sm">
                                        <div class="grid size-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-primary to-primary-container text-on-primary shadow-sm">
                                            <AppIcon name="smart_toy" class="text-base" />
                                        </div>
                                        <div class="space-y-0.5 text-xs flex-1">
                                            <p class="font-bold text-primary flex items-center gap-1.5 flex-wrap">
                                                <span>Ariel AI Assistant</span>
                                                <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-primary text-on-primary font-extrabold tracking-wider">RAG</span>
                                            </p>
                                            <p class="text-on-surface-variant text-[11px] leading-relaxed">
                                                "Proyeksi likuiditas bulan depan optimal. Neraca & Laba Rugi konsolidasi siap dicetak."
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Status bar preview -->
                                    <div class="flex items-center justify-between pt-1 text-[11px] font-bold text-outline">
                                        <span class="flex items-center gap-1.5">
                                            <AppIcon name="sync" class="text-sm text-secondary animate-spin" style="animation-duration: 6s" />
                                            Sinkronisasi Shard Database
                                        </span>
                                        <span class="inline-flex items-center gap-1 text-secondary font-black">
                                            <span class="size-1.5 rounded-full bg-secondary" /> Terisolasi
                                        </span>
                                    </div>
                                </div>

                                <!-- Decorative backdrop layers -->
                                <div class="absolute -bottom-3 -right-3 size-full rounded-3xl bg-primary/5 border border-primary/10 pointer-events-none -z-10 transform rotate-3" />
                                <div class="absolute -bottom-6 -right-6 size-full rounded-3xl bg-secondary/5 border border-secondary/10 pointer-events-none -z-20 transform rotate-6" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Stats Bar Section -->
            <section id="statistik" class="stats-section relative overflow-hidden bg-gradient-to-br from-primary via-primary-container to-primary-deep py-16 text-on-primary scroll-mt-20">
                <div class="ambient-circle absolute -right-20 -top-10 size-80 rounded-full bg-white/5 blur-3xl pointer-events-none" />
                <div class="ambient-circle absolute -left-20 bottom-0 size-72 rounded-full bg-secondary/20 blur-3xl pointer-events-none" />
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/30 to-transparent" />
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
                    <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                        <div
                            v-for="(s, idx) in stats"
                            :key="idx"
                            class="group text-center space-y-3 rounded-2xl border border-white/10 bg-white/5 backdrop-blur-sm p-5 transition-all duration-300 hover:bg-white/10 hover:-translate-y-1"
                        >
                            <div class="mx-auto grid size-11 place-items-center rounded-xl bg-on-primary/10 text-on-primary shadow-inner transition-transform group-hover:scale-110 duration-300">
                                <AppIcon :name="s.icon" class="text-2xl" />
                            </div>
                            <p class="text-3xl sm:text-4xl font-black tracking-tight tabular-nums">{{ formatStat(idx) }}</p>
                            <p class="text-xs sm:text-sm font-medium text-primary-fixed-dim leading-snug">{{ s.label }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Trust Strip / Logo Cloud -->
            <section class="border-b border-outline-variant/40 bg-surface-container-lowest py-5">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <p class="text-center text-xs font-bold uppercase tracking-[0.18em] text-outline mb-3">Dipercaya untuk Standar & Regulasi Terkini</p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                        <div
                            v-for="logo in trustLogos"
                            :key="logo.label"
                            class="group flex flex-col items-center justify-center px-4 py-1.5 text-center transition-all"
                        >
                            <span class="text-sm font-black text-primary group-hover:text-secondary transition-colors">{{ logo.label }}</span>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-outline mt-0.5">{{ logo.sub }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Grid Section -->
            <section id="fitur" class="reveal-group py-20 sm:py-28 bg-surface scroll-mt-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-14">
                    <div class="text-center space-y-4 max-w-3xl mx-auto">
                        <AppBadge tone="primary" class="font-bold">Fitur Unggulan</AppBadge>
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black text-primary tracking-tight leading-tight">
                            Solusi Komprehensif untuk
                            <span class="block bg-gradient-to-r from-secondary to-primary-container bg-clip-text text-transparent">BUMDesma & Instansi Pembina</span>
                        </h2>
                        <p class="text-on-surface-variant text-base sm:text-lg leading-relaxed">
                            Dirancang dari pengalaman lapangan pengelolaan dana bergulir, memenuhi standar tata kelola modern dan regulasi perundang-undangan.
                        </p>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="(f, idx) in features"
                            :key="idx"
                            class="reveal-item group relative overflow-hidden rounded-3xl border border-outline-variant/60 bg-surface-container-lowest p-7 shadow-sm transition-all duration-300 hover:shadow-xl hover:shadow-primary/5 hover:border-primary/30 hover:-translate-y-1.5"
                            @mouseenter="onFeatureCardHover($event, true)"
                            @mouseleave="onFeatureCardHover($event, false)"
                        >
                            <!-- Top accent line on hover -->
                            <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-primary via-secondary to-primary-container origin-left scale-x-0 transition-transform duration-500 group-hover:scale-x-100" />

                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="feature-icon-box grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-primary-fixed to-primary-container/20 text-primary shadow-sm transition-transform">
                                        <AppIcon :name="f.icon" class="text-2xl" />
                                    </div>
                                    <AppBadge tone="neutral" class="text-[10px] font-bold">{{ f.badge }}</AppBadge>
                                </div>
                                <h3 class="text-lg font-bold text-primary group-hover:text-secondary transition-colors">{{ f.title }}</h3>
                                <p class="text-sm text-on-surface-variant leading-relaxed">{{ f.desc }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Workflow Steps Section -->
            <section id="alur" class="reveal-group py-20 sm:py-24 bg-gradient-to-b from-surface-container-low/40 via-surface-container-low/70 to-surface-container-low/40 scroll-mt-20 border-y border-outline-variant/50">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-14">
                    <div class="text-center space-y-4 max-w-2xl mx-auto">
                        <AppBadge tone="secondary" class="font-bold">Alur Kerja Sistem</AppBadge>
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black text-primary tracking-tight leading-tight">
                            4 Langkah Mudah Implementasi
                        </h2>
                        <p class="text-on-surface-variant text-base sm:text-lg leading-relaxed">
                            Proses implementasi yang terstruktur dan didampingi tim teknis berpengalaman.
                        </p>
                    </div>

                    <div class="relative grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Decorative connector line -->
                        <div class="absolute top-12 left-[12%] right-[12%] hidden h-px bg-gradient-to-r from-transparent via-outline-variant to-transparent lg:block" aria-hidden="true" />

                        <div
                            v-for="step in steps"
                            :key="step.num"
                            class="reveal-item relative rounded-3xl border border-outline-variant/60 bg-surface-container-lowest p-6 space-y-4 shadow-sm hover:shadow-lg hover:border-primary/30 transition-all duration-300 hover:-translate-y-1"
                        >
                            <div class="flex items-center gap-3">
                                <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-primary to-primary-container text-on-primary shadow-md shadow-primary/20">
                                    <AppIcon :name="step.icon" class="text-xl" />
                                </div>
                                <span class="text-3xl font-black text-outline/40 leading-none">{{ step.num }}</span>
                            </div>
                            <h3 class="text-base font-bold text-primary leading-snug">{{ step.title }}</h3>
                            <p class="text-xs text-on-surface-variant leading-relaxed">{{ step.desc }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- FAQ Section -->
            <section id="faq" class="reveal-group py-20 sm:py-24 bg-surface scroll-mt-20">
                <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-10">
                    <div class="text-center space-y-4">
                        <AppBadge tone="neutral" class="font-bold">Tanya Jawab</AppBadge>
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black text-primary tracking-tight">Pertanyaan yang Sering Diajukan</h2>
                        <p class="text-on-surface-variant text-base sm:text-lg max-w-2xl mx-auto">
                            Jawaban singkat untuk pertanyaan paling umum tentang platform siupk Next.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(faq, idx) in faqs"
                            :key="idx"
                            class="reveal-item overflow-hidden rounded-2xl border border-outline-variant/60 bg-surface-container-lowest shadow-sm transition-all hover:border-primary/30 hover:shadow-md"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center justify-between p-5 text-left text-sm font-bold text-primary hover:bg-surface-container-low/40 transition-all duration-150 active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-inset"
                                :aria-expanded="activeFaq === idx"
                                @click="toggleFaq(idx)"
                            >
                                <span class="pr-4">{{ faq.q }}</span>
                                <span
                                    class="grid size-8 shrink-0 place-items-center rounded-full bg-surface-container-low text-primary transition-all duration-300"
                                    :class="{ 'bg-primary text-on-primary rotate-180': activeFaq === idx }"
                                >
                                    <AppIcon name="expand_more" class="text-lg" />
                                </span>
                            </button>
                            <div
                                class="grid transition-[grid-template-rows] duration-300 ease-out"
                                :class="activeFaq === idx ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                            >
                                <div class="overflow-hidden">
                                    <div class="px-5 pb-5 text-xs sm:text-sm text-on-surface-variant leading-relaxed border-t border-outline-variant/40 pt-4 bg-surface-container-low/30">
                                        {{ faq.a }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Bottom CTA Section -->
            <section class="relative overflow-hidden py-20 bg-gradient-to-br from-primary via-primary-container to-primary-deep text-on-primary">
                <div class="ambient-orb absolute -left-24 top-0 size-96 rounded-full bg-white/10 blur-3xl pointer-events-none" />
                <div class="ambient-orb absolute -right-24 bottom-0 size-80 rounded-full bg-secondary/20 blur-3xl pointer-events-none" />
                <!-- Subtle grid -->
                <div class="absolute inset-0 opacity-[0.05] pointer-events-none" style="background-image: linear-gradient(to right, currentColor 1px, transparent 1px), linear-gradient(to bottom, currentColor 1px, transparent 1px); background-size: 48px 48px;" />

                <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center space-y-7 relative z-10">
                    <AppBadge tone="primary" class="border border-white/30 font-bold">Mulai Sekarang</AppBadge>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                        Siap Mengoptimalkan Tata Kelola BUMDesma Anda?
                    </h2>
                    <p class="text-primary-fixed-dim text-base sm:text-lg max-w-2xl mx-auto leading-relaxed">
                        Masuk ke portal operasional atau hubungi admin teknis untuk pendaftaran unit BUMDesma baru dan demo langsung.
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                        <Link href="/login">
                            <AppButton variant="secondary" size="large" icon="login" class="!rounded-full font-bold shadow-xl shadow-black/10 hover:shadow-2xl transition-all duration-300 hover:-translate-y-0.5">
                                Masuk ke Portal Sekarang
                            </AppButton>
                        </Link>
                        <a href="#fitur" @click.prevent="smoothScrollTo('fitur')">
                            <AppButton variant="ghost" size="large" icon="explore" class="!rounded-full font-semibold text-on-primary hover:bg-white/10 transition-all duration-300 hover:-translate-y-0.5">
                                Lihat Fitur Lengkap
                            </AppButton>
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-outline-variant/60 bg-surface-container-lowest py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
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

                <div class="pt-6 border-t border-outline-variant/60 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-outline">
                    <p>&copy; 2026 siupk Next &mdash; BUMDesma & LKD Financial Information System.</p>
                    <p class="inline-flex items-center gap-1.5">
                        <AppIcon name="verified" class="text-sm text-secondary" />
                        <span>Sesuai PP No. 11/2021 & SAK EP / ETAP</span>
                    </p>
                </div>
            </div>
        </footer>
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
</style>
