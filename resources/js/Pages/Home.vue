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
    { label: 'Kecamatan / BUMDesma Siap Terlayani', target: 500, suffix: '+', current: ref(0), icon: 'location_city' },
    { label: 'Kelompok Pemanfaat Dikelola', target: 12500, suffix: '+', current: ref(0), icon: 'groups', formatNumber: true },
    { label: 'Otomatisasi Jurnal & Buku Besar', target: 100, suffix: '%', current: ref(0), icon: 'receipt_long' },
    { label: 'Tingkat Akurasi Laporan Keuangan', target: 99.9, suffix: '%', current: ref(0), icon: 'verified', isDecimal: true },
];

function formatDisplayValue(stat) {
    if (stat.isDecimal) {
        return stat.current.value.toFixed(1).replace('.', ',') + stat.suffix;
    }
    if (stat.formatNumber) {
        return Math.floor(stat.current.value).toLocaleString('id-ID') + stat.suffix;
    }
    return Math.floor(stat.current.value) + stat.suffix;
}

const steps = [
    {
        num: '01',
        title: 'Registrasi & Alokasi Tenant',
        desc: 'Pendaftaran BUMDesma LKD untuk mendapatkan alokasi basis data terisolasi yang aman dan siap pakai.',
    },
    {
        num: '02',
        title: 'Migrasi & Saldo Awal',
        desc: 'Fasilitas Import Wizard pintar untuk memindahkan data master kelompok, anggota, dan saldo awal dari sistem sebelumnya.',
    },
    {
        num: '03',
        title: 'Operasional Harian',
        desc: 'Pencatatan pinjaman, pembayaran angsuran, kas/bank, dan jurnal akuntansi otomatis sesuai standar SAK Entitas Privat.',
    },
    {
        num: '04',
        title: 'Laporan & Pengawasan Pemda',
        desc: 'Penerbitan laporan resmi berkala untuk pertanggungjawaban musyawarah antar desa (MAD) dan monitoring dinas terkait.',
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
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
}

// 3D Parallax Tilt Handler on Hero Mockup
function onHeroMouseMove(e) {
    if (!heroVisualRef.value || !heroCardRef.value) return;
    const rect = heroVisualRef.value.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const rotateX = ((y - centerY) / centerY) * -7;
    const rotateY = ((x - centerX) / centerX) * 7;
    const transX = ((x - centerX) / centerX) * 8;
    const transY = ((y - centerY) / centerY) * 8;

    gsap.to(heroCardRef.value, {
        rotateX,
        rotateY,
        x: transX * 0.5,
        y: transY * 0.5,
        transformPerspective: 1000,
        duration: 0.45,
        ease: 'power2.out',
    });

    if (floatPill1Ref.value) {
        gsap.to(floatPill1Ref.value, {
            x: transX * 1.5,
            y: transY * 1.5,
            duration: 0.5,
            ease: 'power2.out',
        });
    }

    if (floatPill2Ref.value) {
        gsap.to(floatPill2Ref.value, {
            x: -transX * 1.2,
            y: -transY * 1.2,
            duration: 0.5,
            ease: 'power2.out',
        });
    }
}

function onHeroMouseLeave() {
    if (!heroCardRef.value) return;
    gsap.to(heroCardRef.value, {
        rotateX: 0,
        rotateY: 0,
        x: 0,
        y: 0,
        duration: 0.8,
        ease: 'elastic.out(1, 0.6)',
    });
    if (floatPill1Ref.value) {
        gsap.to(floatPill1Ref.value, { x: 0, y: 0, duration: 0.8, ease: 'power3.out' });
    }
    if (floatPill2Ref.value) {
        gsap.to(floatPill2Ref.value, { x: 0, y: 0, duration: 0.8, ease: 'power3.out' });
    }
}

// Feature Card Micro-Interaction on Hover
function onFeatureCardHover(e, enter) {
    const icon = e.currentTarget.querySelector('.feature-icon-box');
    if (icon) {
        if (enter) {
            gsap.to(icon, { scale: 1.12, rotate: 4, duration: 0.35, ease: 'back.out(2)' });
        } else {
            gsap.to(icon, { scale: 1, rotate: 0, duration: 0.3, ease: 'power2.out' });
        }
    }
}

let observerInstance = null;

onMounted(() => {
    nextTick(() => {
        const heroTl = gsap.timeline({ defaults: { ease: 'power3.out' } });

        // Smooth Entrance Timeline
        heroTl
            .fromTo(
                '.top-banner-bar',
                { y: -20, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.5 }
            )
            .fromTo(
                '.nav-container',
                { y: -25, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.6 },
                '-=0.2'
            )
            .fromTo(
                '.hero-badge-item',
                { opacity: 0, scale: 0.85, y: 15 },
                { opacity: 1, scale: 1, y: 0, duration: 0.6, ease: 'back.out(1.7)' },
                '-=0.3'
            )
            .fromTo(
                '.hero-anim-title',
                { opacity: 0, y: 30 },
                { opacity: 1, y: 0, duration: 0.75, ease: 'power4.out' },
                '-=0.4'
            )
            .fromTo(
                '.hero-anim-desc',
                { opacity: 0, y: 20 },
                { opacity: 1, y: 0, duration: 0.65 },
                '-=0.4'
            )
            .fromTo(
                '.hero-anim-actions',
                { opacity: 0, y: 20, scale: 0.96 },
                { opacity: 1, y: 0, scale: 1, duration: 0.6, ease: 'back.out(1.4)' },
                '-=0.4'
            )
            .fromTo(
                '.hero-anim-trust',
                { opacity: 0, y: 15 },
                { opacity: 1, y: 0, duration: 0.5, stagger: 0.08 },
                '-=0.3'
            )
            .fromTo(
                '.hero-preview-card',
                { opacity: 0, scale: 0.92, y: 40 },
                { opacity: 1, scale: 1, y: 0, duration: 0.95, ease: 'back.out(1.3)' },
                '-=0.7'
            );

        // Continuous Ambient Loop Animations
        gsap.to('.ambient-blob-1', {
            scale: 1.2,
            rotate: 25,
            x: 20,
            y: -15,
            duration: 8,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
        });
        gsap.to('.ambient-blob-2', {
            scale: 1.15,
            rotate: -20,
            x: -25,
            y: 20,
            duration: 10,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
            delay: 1,
        });

        // Floating Pill Continuous Idle Floating
        gsap.to('.float-pill-1', {
            y: -10,
            duration: 3.6,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
        });
        gsap.to('.float-pill-2', {
            y: 12,
            duration: 4.2,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
            delay: 0.5,
        });

        // Intersection Observer for Scroll Reveals
        let statsCounted = false;
        observerInstance = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const target = entry.target;

                        if (target.classList.contains('stats-section') && !statsCounted) {
                            statsCounted = true;
                            stats.forEach((stat) => {
                                const counterObj = { val: 0 };
                                gsap.to(counterObj, {
                                    val: stat.target,
                                    duration: 2.2,
                                    ease: 'power3.out',
                                    onUpdate: () => {
                                        stat.current.value = counterObj.val;
                                    },
                                });
                            });
                        }

                        if (target.classList.contains('reveal-group')) {
                            const items = target.querySelectorAll('.reveal-item');
                            gsap.fromTo(
                                items,
                                { opacity: 0, y: 35, scale: 0.97 },
                                {
                                    opacity: 1,
                                    y: 0,
                                    scale: 1,
                                    duration: 0.65,
                                    stagger: 0.1,
                                    ease: 'power2.out',
                                }
                            );
                            observerInstance.unobserve(target);
                        }
                    }
                });
            },
            { threshold: 0.12 }
        );

        document.querySelectorAll('.stats-section, .reveal-group').forEach((el) => {
            observerInstance.observe(el);
        });
    });
});

onUnmounted(() => {
    if (observerInstance) {
        observerInstance.disconnect();
    }
});
</script>

<template>
    <Head title="siupk Next - Sistem Informasi Dana Bergulir Masyarakat" />

    <div class="min-h-screen bg-surface font-sans text-on-surface antialiased scroll-smooth selection:bg-primary selection:text-on-primary">
        <!-- Top Banner -->
        <div class="top-banner-bar bg-gradient-to-r from-primary via-primary-container to-primary px-4 py-2 text-center text-xs font-semibold tracking-wide text-on-primary shadow-sm">
            <div class="mx-auto flex max-w-7xl items-center justify-center gap-2">
                <span class="inline-flex items-center rounded-full bg-secondary px-2.5 py-0.5 text-[11px] font-extrabold tracking-wider text-on-secondary shadow-sm">RESMI</span>
                <span>Sistem Tata Kelola Keuangan & Dana Bergulir BUMDesma LKD Sesuai Regulasi PP No. 11/2021</span>
            </div>
        </div>

        <!-- Sticky Header / Navbar -->
        <header class="nav-container sticky top-0 z-40 border-b border-outline-variant/60 bg-surface-container-lowest/95 backdrop-blur-md transition-all">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
                <!-- Brand Logo -->
                <a href="#" class="flex items-center gap-3 transition hover:opacity-90 group">
                    <div class="grid size-10 place-items-center rounded-xl bg-primary text-on-primary shadow-md transition-transform group-hover:scale-105 duration-300">
                        <AppIcon name="account_balance" class="text-2xl" />
                    </div>
                    <div>
                        <span class="block text-lg font-black tracking-tight text-primary">siupk <span class="text-secondary">Next</span></span>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-outline">BUMDesma LKD Platform</span>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden items-center gap-8 md:flex">
                    <a href="#fitur" @click.prevent="smoothScrollTo('fitur')" class="text-sm font-semibold text-on-surface-variant transition hover:text-primary">Fitur Unggulan</a>
                    <a href="#solusi" @click.prevent="smoothScrollTo('solusi')" class="text-sm font-semibold text-on-surface-variant transition hover:text-primary">Alur Kerja</a>
                    <a href="#statistik" @click.prevent="smoothScrollTo('statistik')" class="text-sm font-semibold text-on-surface-variant transition hover:text-primary">Capaian</a>
                    <a href="#faq" @click.prevent="smoothScrollTo('faq')" class="text-sm font-semibold text-on-surface-variant transition hover:text-primary">Tanya Jawab</a>
                </nav>

                <!-- Header Actions -->
                <div class="flex items-center gap-3">
                    <Link href="/login">
                        <AppButton variant="secondary" size="compact" icon="login" class="font-bold shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
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
                <div v-if="mobileNavOpen" class="border-b border-outline-variant bg-surface-container-low px-4 py-4 md:hidden">
                    <nav class="flex flex-col gap-3">
                        <a href="#fitur" @click="smoothScrollTo('fitur')" class="rounded-lg px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-high">Fitur Unggulan</a>
                        <a href="#solusi" @click="smoothScrollTo('solusi')" class="rounded-lg px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-high">Alur Kerja</a>
                        <a href="#statistik" @click="smoothScrollTo('statistik')" class="rounded-lg px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-high">Capaian</a>
                        <a href="#faq" @click="smoothScrollTo('faq')" class="rounded-lg px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-high">Tanya Jawab</a>
                        <div class="pt-2 border-t border-outline-variant">
                            <Link href="/login" class="w-full">
                                <AppButton variant="secondary" size="medium" icon="login" class="w-full font-bold">
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
            <section class="relative overflow-hidden bg-gradient-to-b from-surface-container-lowest via-surface-container-low/40 to-surface py-16 sm:py-24 lg:py-28">
                <!-- Ambient Glowing Background Elements -->
                <div class="ambient-blob-1 absolute -top-24 -left-24 size-96 rounded-full bg-primary/10 blur-3xl pointer-events-none -z-10" />
                <div class="ambient-blob-2 absolute top-1/2 -right-24 size-[28rem] rounded-full bg-secondary/10 blur-3xl pointer-events-none -z-10" />

                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-8">
                        <!-- Left Hero Column -->
                        <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                            <div class="hero-badge-item inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-3.5 py-1.5 text-xs font-semibold text-primary shadow-xs">
                                <AppIcon name="verified" class="text-base text-primary" />
                                <span>Platform Dana Bergulir & Akuntansi SAK EP Generasi Baru</span>
                            </div>

                            <h1 class="hero-anim-title text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-primary leading-[1.15]">
                                Transformasi Digital Keuangan
                                <span class="block bg-gradient-to-r from-primary via-secondary to-primary-container bg-clip-text text-transparent">
                                    BUMDesma & LKD Indonesia
                                </span>
                            </h1>

                            <p class="hero-anim-desc text-base sm:text-lg text-on-surface-variant max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                                Solusi terintegrasi untuk pengelolaan pinjaman bergulir, pembukuan akuntansi standar SAK Entitas Privat, penerbitan kuitansi WhatsApp, dan pelaporan konsolidasi Pemerintah Kabupaten secara real-time.
                            </p>

                            <!-- CTA Buttons -->
                            <div class="hero-anim-actions flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-2">
                                <Link href="/login">
                                    <AppButton variant="primary" size="large" icon="login" class="font-bold shadow-lg shadow-primary/25 hover:shadow-xl hover:shadow-primary/30 transition-all duration-300 hover:-translate-y-0.5">
                                        Masuk ke Dashboard
                                    </AppButton>
                                </Link>
                                <a href="#fitur" @click.prevent="smoothScrollTo('fitur')">
                                    <AppButton variant="secondary" size="large" icon="explore" class="font-semibold hover:-translate-y-0.5 transition-all duration-300">
                                        Pelajari Fitur
                                    </AppButton>
                                </a>
                            </div>

                            <!-- Trust Badges -->
                            <div class="hero-anim-trust pt-6 border-t border-outline-variant/60 flex flex-wrap items-center justify-center lg:justify-start gap-6 text-xs font-semibold text-outline">
                                <div class="flex items-center gap-2 hover:text-primary transition-colors">
                                    <AppIcon name="check_circle" class="text-secondary text-base" />
                                    <span>PP No. 11/2021 Compliant</span>
                                </div>
                                <div class="flex items-center gap-2 hover:text-primary transition-colors">
                                    <AppIcon name="check_circle" class="text-secondary text-base" />
                                    <span>Standar Akuntansi SAK EP</span>
                                </div>
                                <div class="flex items-center gap-2 hover:text-primary transition-colors">
                                    <AppIcon name="check_circle" class="text-secondary text-base" />
                                    <span>Isolasi Sharding Database</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Hero Visual with Interactive 3D Tilt Parallax -->
                        <div
                            ref="heroVisualRef"
                            class="lg:col-span-5 relative flex justify-center perspective-[1000px]"
                            @mousemove="onHeroMouseMove"
                            @mouseleave="onHeroMouseLeave"
                        >
                            <!-- Floating Pill 1: Repayment Rate -->
                            <div
                                ref="floatPill1Ref"
                                class="float-pill-1 absolute -top-4 -left-4 sm:-left-8 z-30 flex items-center gap-2.5 rounded-2xl border border-secondary/20 bg-surface-container-lowest/95 backdrop-blur-md px-4 py-2.5 shadow-xl text-xs font-bold text-on-surface transition-transform will-change-transform"
                            >
                                <div class="grid size-7 place-items-center rounded-xl bg-secondary/15 text-secondary">
                                    <AppIcon name="trending_up" class="text-lg" />
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase font-extrabold tracking-wider text-outline">Kolektibilitas</p>
                                    <p class="text-secondary font-black">98,6% Lancar</p>
                                </div>
                            </div>

                            <!-- Floating Pill 2: Portfolio Volume -->
                            <div
                                ref="floatPill2Ref"
                                class="float-pill-2 absolute -bottom-4 -right-4 sm:-right-6 z-30 flex items-center gap-2.5 rounded-2xl border border-primary/20 bg-surface-container-lowest/95 backdrop-blur-md px-4 py-2.5 shadow-xl text-xs font-bold text-on-surface transition-transform will-change-transform"
                            >
                                <div class="grid size-7 place-items-center rounded-xl bg-primary/15 text-primary">
                                    <AppIcon name="account_balance_wallet" class="text-lg" />
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase font-extrabold tracking-wider text-outline">Portofolio</p>
                                    <p class="text-primary font-black">Rp 1,48 Milyar</p>
                                </div>
                            </div>

                            <!-- Main Mockup Dashboard Card -->
                            <div ref="heroCardRef" class="hero-preview-card w-full max-w-md will-change-transform transform-gpu">
                                <AppCard class="space-y-4 border-2 border-primary/15 bg-surface-container-lowest/95 backdrop-blur-xl p-6 shadow-2xl rounded-3xl relative overflow-hidden">
                                    <!-- Top Card Gradient Aura -->
                                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-primary via-secondary to-primary-container" />

                                    <!-- Mock App Header -->
                                    <div class="flex items-center justify-between pb-3 border-b border-outline-variant/60">
                                        <div class="flex items-center gap-2.5">
                                            <div class="size-3 rounded-full bg-error/70" />
                                            <div class="size-3 rounded-full bg-amber-400/70" />
                                            <div class="size-3 rounded-full bg-secondary/70" />
                                            <span class="ml-2 text-xs font-black text-primary">BUMDesma Mandiri Sejahtera</span>
                                        </div>
                                        <span class="rounded-full bg-secondary/15 px-2 py-0.5 text-[10px] font-bold text-secondary flex items-center gap-1">
                                            <span class="size-1.5 rounded-full bg-secondary animate-pulse" /> Live
                                        </span>
                                    </div>

                                    <!-- Mock Stats Cards -->
                                    <div class="grid grid-cols-2 gap-3.5">
                                        <div class="rounded-2xl bg-surface-container-low p-3.5 space-y-1 border border-outline-variant/40 hover:border-primary/30 transition-colors">
                                            <span class="text-[11px] font-bold text-outline uppercase tracking-wider">Outstanding Piutang</span>
                                            <p class="text-lg font-black text-primary">Rp 1.480.500.000</p>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-secondary">
                                                <AppIcon name="trending_up" class="text-sm" /> +12.4% bln ini
                                            </span>
                                        </div>
                                        <div class="rounded-2xl bg-surface-container-low p-3.5 space-y-1 border border-outline-variant/40 hover:border-secondary/30 transition-colors">
                                            <span class="text-[11px] font-bold text-outline uppercase tracking-wider">Kesehatan Usaha</span>
                                            <p class="text-lg font-black text-secondary">Predikat Sehat</p>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-primary">
                                                <AppIcon name="verified" class="text-sm" /> Skor: 94,8 / 100
                                            </span>
                                        </div>
                                    </div>

                                    <!-- AI Assistant Mock -->
                                    <div class="rounded-2xl border border-primary/20 bg-primary-fixed/30 p-3.5 flex items-start gap-3 shadow-xs">
                                        <div class="grid size-8 shrink-0 place-items-center rounded-xl bg-primary text-on-primary shadow-xs">
                                            <AppIcon name="smart_toy" class="text-base" />
                                        </div>
                                        <div class="space-y-0.5 text-xs">
                                            <p class="font-bold text-primary flex items-center gap-1.5">
                                                <span>Ariel AI Assistant</span>
                                                <span class="text-[9px] px-1.5 py-0.2 rounded-full bg-primary/10 text-primary font-extrabold">RAG Ready</span>
                                            </p>
                                            <p class="text-on-surface-variant text-[11px] leading-relaxed">
                                                "Proyeksi likuiditas bulan depan optimal. Neraca & Laba Rugi konsolidasi siap dicetak."
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Status bar preview -->
                                    <div class="flex items-center justify-between pt-1 text-[11px] font-bold text-outline">
                                        <span class="flex items-center gap-1.5"><AppIcon name="sync" class="text-sm text-secondary animate-spin" style="animation-duration: 6s" /> Sinkronisasi Shard Database</span>
                                        <span class="text-secondary font-black">Terisolasi</span>
                                    </div>
                                </AppCard>

                                <!-- Decorative backdrop -->
                                <div class="absolute -bottom-4 -right-4 size-full rounded-3xl bg-primary/10 border border-primary/20 pointer-events-none -z-10 transform rotate-2" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Stats Bar Section -->
            <section id="statistik" class="stats-section bg-gradient-to-r from-primary via-primary-container to-primary text-on-primary py-14 scroll-mt-20 shadow-inner relative overflow-hidden">
                <div class="ambient-circle absolute -right-16 top-0 size-80 rounded-full bg-white/5 blur-2xl pointer-events-none" />
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
                    <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
                        <div v-for="(s, idx) in stats" :key="idx" class="text-center space-y-2 group">
                            <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-on-primary/10 text-on-primary shadow-xs transition-transform group-hover:scale-110 duration-300">
                                <AppIcon :name="s.icon" class="text-2xl" />
                            </div>
                            <p class="text-3xl sm:text-4xl font-black tracking-tight">{{ formatDisplayValue(s) }}</p>
                            <p class="text-xs sm:text-sm font-medium text-primary-fixed-dim max-w-[200px] mx-auto">{{ s.label }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Grid Section -->
            <section id="fitur" class="reveal-group py-20 sm:py-28 bg-surface scroll-mt-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-14">
                    <div class="text-center space-y-4 max-w-3xl mx-auto">
                        <AppBadge tone="primary" class="font-bold">Fitur Unggulan</AppBadge>
                        <h2 class="text-3xl sm:text-4xl font-black text-primary tracking-tight">
                            Solusi Komprehensif untuk BUMDesma & Instansi Pembina
                        </h2>
                        <p class="text-on-surface-variant text-base sm:text-lg">
                            Dirancang dari pengalaman lapangan pengelolaan dana bergulir, memenuhi standar tata kelola modern dan regulasi perundang-undangan.
                        </p>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="(f, idx) in features"
                            :key="idx"
                            class="reveal-item group relative rounded-3xl border border-outline-variant/70 bg-surface-container-lowest p-7 shadow-sm transition-all duration-300 hover:shadow-xl hover:border-primary/30 hover:-translate-y-1.5"
                            @mouseenter="onFeatureCardHover($event, true)"
                            @mouseleave="onFeatureCardHover($event, false)"
                        >
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="feature-icon-box grid size-12 place-items-center rounded-2xl bg-primary-fixed text-primary shadow-xs transition-transform">
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
            <section id="solusi" class="reveal-group py-20 bg-surface-container-low/50 scroll-mt-20 border-y border-outline-variant/60">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-14">
                    <div class="text-center space-y-4 max-w-2xl mx-auto">
                        <AppBadge tone="secondary" class="font-bold">Alur Kerja Sistem</AppBadge>
                        <h2 class="text-3xl sm:text-4xl font-black text-primary tracking-tight">
                            4 Langkah Mudah Implementasi di BUMDesma
                        </h2>
                        <p class="text-on-surface-variant text-base">
                            Proses implementasi yang terstruktur dan didampingi tim teknis berpengalaman.
                        </p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="step in steps"
                            :key="step.num"
                            class="reveal-item relative rounded-3xl border border-outline-variant/60 bg-surface-container-lowest p-6 space-y-4 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1"
                        >
                            <span class="inline-block text-3xl font-black text-secondary-container/80">{{ step.num }}</span>
                            <h3 class="text-base font-bold text-primary">{{ step.title }}</h3>
                            <p class="text-xs text-on-surface-variant leading-relaxed">{{ step.desc }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- FAQ Section -->
            <section id="faq" class="reveal-group py-20 bg-surface scroll-mt-20">
                <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-10">
                    <div class="text-center space-y-4">
                        <AppBadge tone="neutral" class="font-bold">Tanya Jawab</AppBadge>
                        <h2 class="text-3xl font-black text-primary tracking-tight">Pertanyaan yang Sering Diajukan</h2>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(faq, idx) in faqs"
                            :key="idx"
                            class="reveal-item overflow-hidden rounded-2xl border border-outline-variant/70 bg-surface-container-lowest shadow-xs transition-all"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center justify-between p-5 text-left text-sm font-bold text-primary hover:bg-surface-container-low/50 transition-all duration-150 active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-inset"
                                :aria-expanded="activeFaq === idx"
                                @click="toggleFaq(idx)"
                            >
                                <span class="pr-4">{{ faq.q }}</span>
                                <AppIcon
                                    name="expand_more"
                                    class="text-xl text-outline transition-transform duration-200 shrink-0"
                                    :class="{ 'rotate-180 text-primary': activeFaq === idx }"
                                />
                            </button>
                            <div
                                class="grid transition-[grid-template-rows] duration-200 ease-out"
                                :class="activeFaq === idx ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                            >
                                <div class="overflow-hidden">
                                    <div class="px-5 pb-5 text-xs sm:text-sm text-on-surface-variant leading-relaxed border-t border-outline-variant/40 pt-3 bg-surface-container-low/30">
                                        {{ faq.a }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Bottom CTA Section -->
            <section class="reveal-group py-16 bg-gradient-to-br from-primary via-primary-container to-primary text-on-primary text-center relative overflow-hidden">
                <div class="ambient-orb absolute -left-20 top-0 size-96 rounded-full bg-white/10 blur-3xl pointer-events-none" />
                <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6 relative z-10">
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight">Siap Mengoptimalkan Tata Kelola BUMDesma Anda?</h2>
                    <p class="text-primary-fixed-dim text-base max-w-xl mx-auto leading-relaxed">
                        Masuk ke portal operasional atau hubungi admin teknis untuk pendaftaran unit BUMDesma baru.
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-4 pt-2">
                        <Link href="/login">
                            <AppButton variant="secondary" size="large" icon="login" class="font-bold shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-0.5">
                                Masuk ke Portal Sekarang
                            </AppButton>
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-outline-variant/60 bg-surface-container-lowest py-8 text-center text-xs text-outline">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p>&copy; 2026 siupk Next &mdash; BUMDesma & LKD Financial Information System.</p>
                <div class="flex items-center gap-4">
                    <a href="#" class="hover:text-primary transition-colors">Kebijakan Privasi</a>
                    <span>&bull;</span>
                    <a href="#" class="hover:text-primary transition-colors">Syarat Layanan</a>
                    <span>&bull;</span>
                    <Link href="/login" class="hover:text-primary transition-colors font-bold">Portal Login</Link>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 200ms ease, transform 200ms ease;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
