<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import gsap from 'gsap';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    tenant: { type: Object, required: true },
    settings: { type: Object, default: () => ({}) },
    recent_posts: { type: Array, default: () => [] },
    recent_pages: { type: Array, default: () => [] },
    contact: { type: Object, default: () => ({}) },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const messageForm = useForm({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
    website: '',
});
const messageSending = ref(false);

function submitMessage() {
    messageSending.value = true;
    messageForm.post(route('public.contact.store'), {
        preserveScroll: true,
        onSuccess: () => messageForm.reset('name', 'email', 'phone', 'subject', 'message', 'website'),
        onFinish: () => { messageSending.value = false; },
    });
}

function formatPostDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

const mobileNavOpen = ref(false);
const activeSection = ref('');
const scrolled = ref(false);
const showScrollTop = ref(false);
const cursorX = ref(-100);
const cursorY = ref(-100);
const hoverTarget = ref(false);

function smoothScrollTo(id) {
    mobileNavOpen.value = false;
    const el = document.getElementById(id);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function onGlobalMouseMove(e) {
    cursorX.value = e.clientX;
    cursorY.value = e.clientY;
}

function onScroll() {
    scrolled.value = window.scrollY > 12;
    showScrollTop.value = window.scrollY > 480;
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

const orgName = computed(() => props.organization?.name || 'BUMDesma LKD');
const orgLegalName = computed(() => props.organization?.legal_name || orgName.value);
const orgRegion = computed(() => {
    const d = props.organization?.district_name;
    const r = props.organization?.regency_name;
    return [d, r].filter(Boolean).join(' · ');
});
const orgAddress = computed(() => props.organization?.address || '');
const orgPhone = computed(() => props.organization?.phone || '');
const orgEmail = computed(() => props.organization?.email || '');
const orgWebsite = computed(() => props.organization?.website || '');
const orgFounded = computed(() => props.organization?.operational_start_year || '');
const orgLogo = computed(() => props.organization?.logo_url || '');
const orgInitial = computed(() =>
    (props.organization?.legal_name || orgName.value || 'B').charAt(0).toUpperCase()
);

const heroTagline = computed(
    () =>
        props.settings?.hero_tagline ||
        `${orgLegalName.value} — Sistem Tata Kelola Keuangan & Dana Bergulir Masyarakat.`
);
const heroDescription = computed(
    () =>
        props.settings?.hero_description ||
        `Portal informasi resmi ${orgLegalName.value} — mengelola pinjaman, pembukuan, dan pelaporan keuangan BUMDesma LKD sesuai regulasi yang berlaku.`
);

const navAnchors = [
    { id: 'layanan', label: 'Layanan' },
    { id: 'produk', label: 'Produk' },
    { id: 'alur', label: 'Alur' },
    { id: 'berita', label: 'Berita' },
    { id: 'halaman', label: 'Halaman' },
    { id: 'kontak', label: 'Kontak' },
];

const stats = [
    { label: 'Kecamatan / Wilayah Layanan Aktif', value: '500+', top: 'Wilayah', icon: 'map' },
    { label: 'Kelompok Pemanfaat Aktif', value: '12.500+', top: 'Pemanfaat', icon: 'groups' },
    { label: 'Akurasi Jurnal Otomatis', value: '100%', top: 'Akurasi', icon: 'verified' },
    { label: 'Standar Akuntansi SAK EP', value: '99,9%', top: 'Kepatuhan', icon: 'shield' },
];

const layananModul = [
    { num: '01', icon: 'how_to_reg', title: 'Verifikasi Anggota', desc: 'Form digital, validasi NIK, dan lampiran dokumen otomatis untuk pengurus kelompok.', tone: 'indigo', tag: 'Onboarding', period: 'Real-time', stats: [
        { value: '2.4K', label: 'Tervalidasi', highlight: true },
        { value: '99,7%', label: 'Akurat', highlight: false },
    ] },
    { num: '02', icon: 'request_quote', title: 'Pengajuan Kredit', desc: 'Alur berjenjang dari kelompok hingga pengurus dengan berita acara otomatis.', tone: 'sky', tag: 'Workflow', period: '7 hari kerja', stats: [
        { value: '850+', label: 'Pengajuan/bln', highlight: true },
        { value: '< 3 hari', label: 'Rata-rata', highlight: false },
    ] },
    { num: '03', icon: 'account_balance_wallet', title: 'Pencairan & Angsuran', desc: 'Jadwal angsuran, kolektabilitas, dan tanda terima digital real-time.', tone: 'amber', tag: 'Transaksi', period: 'H+1 pencairan', stats: [
        { value: 'Rp 8,5M', label: 'Outstanding', highlight: true },
        { value: '98,4%', label: 'Kolektabilitas', highlight: false },
    ] },
    { num: '04', icon: 'fact_check', title: 'Berita Acara', desc: 'Template BA tersinkronisasi dengan format kecamatan dan kabupaten.', tone: 'rose', tag: 'Dokumen', period: 'Otomatis', stats: [
        { value: '12+', label: 'Template', highlight: true },
        { value: '100%', label: 'Tersinkron', highlight: false },
    ] },
    { num: '05', icon: 'analytics', title: 'Laporan Keuangan', desc: 'Neraca, laba-rugi, arus kas, dan CALK otomatis sesuai standar.', tone: 'teal', tag: 'Reporting', period: 'Bulanan', stats: [
        { value: '24+', label: 'Laporan', highlight: true },
        { value: 'SAK EP', label: 'Standar', highlight: false },
    ] },
    { num: '06', icon: 'inventory_2', title: 'Aset & Inventaris', desc: 'Manajemen aset tetap, inventaris, dan penyusutan otomatis.', tone: 'violet', tag: 'Aset', period: 'Update mingguan', stats: [
        { value: '320+', label: 'Item aset', highlight: true },
        { value: 'Auto', label: 'Penyusutan', highlight: false },
    ] },
];

const produkCards = [
    {
        key: 'kelompok',
        icon: 'groups',
        tone: 'indigo',
        headline: 'Solidaritas & gotong-royong',
        ringkasan: 'Pinjaman untuk satu kelompok usaha/masyarakat beranggotakan 5–20 orang.',
        desc: 'Skema penyaluran dimana pengurus kelompok bersama anggotanya bertanggung jawab secara kolektif atas kelancaran angsuran. Cocok untuk anggota yang baru memulai usaha.',
        stats: [
            { label: 'Porsi penyaluran', value: '68%', icon: 'pie_chart' },
            { label: 'Kolektabilitas', value: '98,4%', icon: 'trending_up' },
            { label: 'Tepat waktu', value: '96,2%', icon: 'schedule' },
            { label: 'Rata-rata nominal', value: 'Rp 8,5 jt', icon: 'payments' },
        ],
        manfaat: [
            'Risiko gagal bayar lebih rendah',
            'Verifikasi kolektif oleh pengurus',
            'Berita acara cukup satu dokumen',
            'Pendampingan intensif',
            'Cocok untuk usaha pemula',
            'Monitoring real-time',
        ],
        syarat: [
            'Kelompok minimal 5 anggota',
            'Memiliki pengurus aktif',
            'Berita acara musyawarah kelompok',
            'Rekomendasi pendamping kecamatan',
        ],
    },
    {
        key: 'individu',
        icon: 'person',
        tone: 'amber',
        headline: 'Fleksibel & personal',
        ringkasan: 'Pinjaman untuk peminjam perorangan dengan plafon lebih besar.',
        desc: 'Skema penyaluran kepada peminjam perorangan dengan analisis profil risiko yang lebih ketat. Cocok untuk anggota yang butuh plafon lebih besar dan sudah memiliki catatan usaha.',
        stats: [
            { label: 'Porsi penyaluran', value: '32%', icon: 'pie_chart' },
            { label: 'Kolektabilitas', value: '92,1%', icon: 'trending_up' },
            { label: 'Tepat waktu', value: '88,5%', icon: 'schedule' },
            { label: 'Rata-rata nominal', value: 'Rp 15,8 jt', icon: 'payments' },
        ],
        manfaat: [
            'Plafon lebih tinggi per peminjam',
            'Skoring risiko otomatis',
            'Reminder WhatsApp otomatis',
            'Jadwal angsuran custom',
            'Cocok untuk usaha berkembang',
            'Pencairan lebih cepat',
        ],
        syarat: [
            'Anggota aktif minimal 1 tahun',
            'Riwayat pinjaman sebelumnya',
            'Profil usaha terdaftar',
            'Rekomendasi pengurus',
        ],
    },
];

const toneStyles = {
    indigo: { ring: 'ring-emerald-600/30', bg: 'bg-emerald-50', text: 'text-emerald-800', grad: 'from-emerald-700 to-green-600', soft: 'from-emerald-700/10 to-green-600/10', icon: 'bg-emerald-700', accent: 'shadow-emerald-600/25', defaultTone: 'shadow-emerald-600/[0.08]', shadowRgb: 'rgba(16,185,129,0.18)' },
    sky: { ring: 'ring-emerald-500/30', bg: 'bg-emerald-50', text: 'text-emerald-700', grad: 'from-emerald-600 to-teal-500', soft: 'from-emerald-600/10 to-teal-500/10', icon: 'bg-emerald-600', accent: 'shadow-emerald-500/25', defaultTone: 'shadow-emerald-500/[0.08]', shadowRgb: 'rgba(16,185,129,0.16)' },
    violet: { ring: 'ring-green-600/30', bg: 'bg-green-50', text: 'text-green-800', grad: 'from-green-600 to-teal-500', soft: 'from-green-600/10 to-teal-500/10', icon: 'bg-green-600', accent: 'shadow-green-600/25', defaultTone: 'shadow-green-600/[0.08]', shadowRgb: 'rgba(22,163,74,0.18)' },
    amber: { ring: 'ring-amber-500/30', bg: 'bg-amber-50', text: 'text-amber-700', grad: 'from-amber-500 to-orange-500', soft: 'from-amber-500/10 to-orange-500/10', icon: 'bg-amber-500', accent: 'shadow-amber-500/25', defaultTone: 'shadow-amber-500/[0.08]', shadowRgb: 'rgba(245,158,11,0.18)' },
    rose: { ring: 'ring-rose-500/30', bg: 'bg-rose-50', text: 'text-rose-700', grad: 'from-rose-500 to-pink-500', soft: 'from-rose-500/10 to-pink-500/10', icon: 'bg-rose-500', accent: 'shadow-rose-500/25', defaultTone: 'shadow-rose-500/[0.08]', shadowRgb: 'rgba(244,63,94,0.18)' },
    teal: { ring: 'ring-teal-500/30', bg: 'bg-teal-50', text: 'text-teal-700', grad: 'from-teal-500 to-emerald-500', soft: 'from-teal-500/10 to-emerald-500/10', icon: 'bg-teal-500', accent: 'shadow-teal-500/25', defaultTone: 'shadow-teal-500/[0.08]', shadowRgb: 'rgba(20,184,166,0.18)' },
};

const alur = [
    { num: '01', title: 'Musyawarah Desa', desc: 'Verifikasi calon anggota dan rencana kebutuhan oleh pengurus & LKD desa.', role: 'Pengurus + LKD', duration: '1–3 hari', icon: 'groups_2' },
    { num: '02', title: 'Verifikasi & SPK', desc: 'Pemeriksaan data, berita acara, dan penandatanganan perjanjian kredit.', role: 'Tim Teknis', duration: '2–5 hari', icon: 'task_alt' },
    { num: '03', title: 'Penyaluran & Angsuran', desc: 'Pencairan, jadwal angsuran, dan monitoring kolektabilitas real-time.', role: 'Operator', duration: 'Otomatis', icon: 'sync_alt' },
    { num: '04', title: 'Pelaporan', desc: 'Laporan keuangan, berita acara, dan dokumentasi tersimpan otomatis.', role: 'Pengurus + Dinas', duration: 'Bulanan', icon: 'analytics' },
];

const regulasi = [
    { label: 'PP No. 11/2021', sub: 'BUMDesma', desc: 'Pendirian & pengelolaan badan usaha milik desa sesuai regulasi.', tone: 'indigo', icon: 'policy' },
    { label: 'SAK EP / ETAP', sub: 'Standar Akuntansi', desc: 'Bagan akun entitas mikro & privat yang berlaku nasional.', tone: 'sky', icon: 'menu_book' },
    { label: 'Sharding DB', sub: 'Isolasi Tenant', desc: 'Ruang data independen per BUMDesma untuk keamanan.', tone: 'teal', icon: 'database' },
    { label: 'QRIS & VA', sub: 'Payment Gateway', desc: 'Pembayaran nasional multi-bank yang aman & cepat.', tone: 'amber', icon: 'qr_code_2' },
];

let observerInstance = null;
let statsObserver = null;
let navObserver = null;
let dividerObserver = null;

onMounted(() => {
    nextTick(() => {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.addEventListener('mousemove', onGlobalMouseMove, { passive: true });
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        if (!prefersReducedMotion) {
            // Hero entrance
            const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
            tl.fromTo('.anim-fade', { opacity: 0 }, { opacity: 1, duration: 0.45, stagger: 0.03, delay: 0.05 }, 0)
              .fromTo('.anim-fade-up', { opacity: 0, y: 16 }, { opacity: 1, y: 0, duration: 0.55, stagger: 0.05 }, 0.05);
            if (document.querySelector('.anim-scale')) {
                tl.fromTo('.anim-scale', { opacity: 0, scale: 0.94 }, { opacity: 1, scale: 1, duration: 0.6, ease: 'expo.out', stagger: 0.06 }, '-=0.3');
            }
            if (document.querySelector('.anim-slide')) {
                tl.fromTo('.anim-slide', { opacity: 0, x: -12 }, { opacity: 1, x: 0, duration: 0.5, stagger: 0.04 }, '<');
            }

            // Floating decorative orbs
            gsap.to('.float-orb', {
                y: 'random(-22, 22)',
                x: 'random(-14, 14)',
                duration: 'random(8, 14)',
                ease: 'sine.inOut',
                yoyo: true,
                repeat: -1,
                stagger: { each: 0.3, from: 'random' }
            });

            // Rotating gradients
            gsap.to('.spin-slow', { rotation: 360, duration: 90, ease: 'none', repeat: -1 });
            gsap.to('.spin-reverse', { rotation: -360, duration: 110, ease: 'none', repeat: -1 });

            // Reveal on scroll
            observerInstance = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        const items = entry.target.querySelectorAll('.reveal-item');
                        gsap.fromTo(items,
                            { opacity: 0, y: 40 },
                            { opacity: 1, y: 0, duration: 0.8, stagger: 0.08, ease: 'power3.out' }
                        );
                        observerInstance.unobserve(entry.target);
                    });
                },
                { threshold: 0.12 }
            );
            document.querySelectorAll('.reveal-group').forEach((el) => observerInstance.observe(el));

            // Side reveal
            const sideObserver = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        const items = entry.target.querySelectorAll('.reveal-side');
                        gsap.fromTo(items,
                            { opacity: 0, x: 30 },
                            { opacity: 1, x: 0, duration: 0.9, stagger: 0.1, ease: 'power3.out' }
                        );
                        sideObserver.unobserve(entry.target);
                    });
                },
                { threshold: 0.15 }
            );
            document.querySelectorAll('.reveal-side-group').forEach((el) => sideObserver.observe(el));

            // Scale reveal
            const scaleObserver = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        gsap.fromTo(entry.target.querySelector('.reveal-scale'),
                            { opacity: 0, scale: 0.95, y: 30 },
                            { opacity: 1, scale: 1, y: 0, duration: 1.0, ease: 'expo.out' }
                        );
                        scaleObserver.unobserve(entry.target);
                    });
                },
                { threshold: 0.2 }
            );
            document.querySelectorAll('.scale-group').forEach((el) => scaleObserver.observe(el));

            // Section nav tracking
            navObserver = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) activeSection.value = entry.target.id;
                    });
                },
                { rootMargin: '-35% 0px -55% 0px', threshold: 0 }
            );
            navAnchors.forEach((a) => {
                const el = document.getElementById(a.id);
                if (el) navObserver.observe(el);
            });

            // Animated section dividers — draw SVG line + label fade
            dividerObserver = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        const el = entry.target;
                        const line = el.querySelector('.divider-line');
                        const dot = el.querySelector('.divider-dot');
                        const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
                        if (line) {
                            tl.fromTo(line,
                                { strokeDashoffset: 1000 },
                                { strokeDashoffset: 0, duration: 1.4 }, 0
                            );
                        }
                        if (dot) {
                            tl.fromTo(dot, { opacity: 0, scale: 0 }, { opacity: 1, scale: 1, duration: 0.5, ease: 'back.out(1.8)' }, 0.1);
                        }
                        dividerObserver.unobserve(el);
                    });
                },
                { threshold: 0.4 }
            );
            document.querySelectorAll('.section-divider').forEach((el) => dividerObserver.observe(el));
        } else {
            gsap.set('.anim-fade, .anim-fade-up, .anim-scale, .anim-slide, .reveal-item, .reveal-side, .reveal-scale', { opacity: 1, y: 0, x: 0, scale: 1 });
            gsap.set('.divider-line', { strokeDashoffset: 0 });
            gsap.set('.divider-dot', { opacity: 1, scale: 1 });
        }
    });
});

onUnmounted(() => {
    if (observerInstance) observerInstance.disconnect();
    if (statsObserver) statsObserver.disconnect();
    if (navObserver) navObserver.disconnect();
    if (dividerObserver) dividerObserver.disconnect();
    window.removeEventListener('mousemove', onGlobalMouseMove);
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <Head :title="`${orgLegalName} — Sistem Informasi Dana Bergulir Masyarakat`">
        <meta head-key="description" name="description" :content="heroDescription" />
        <meta head-key="og:title" property="og:title" :content="`${orgLegalName} — Situs Resmi`" />
        <meta head-key="og:description" property="og:description" :content="heroDescription" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:url" property="og:url" :content="$page.url" />
        <meta v-if="orgLogo" head-key="og:image" property="og:image" :content="orgLogo" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
    </Head>

    <div class="relative min-h-screen overflow-x-clip bg-slate-50 font-sans text-slate-900 antialiased selection:bg-emerald-700 selection:text-white scroll-smooth">
        <!-- Animated gradient background -->
        <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(16,185,129,0.09),_transparent_50%),radial-gradient(circle_at_bottom_left,_rgba(22,163,74,0.07),_transparent_50%),radial-gradient(circle_at_bottom_right,_rgba(16,185,129,0.07),_transparent_50%)]" />
            <div class="float-orb absolute -top-32 right-1/4 h-[24rem] w-[24rem] rounded-full bg-gradient-to-br from-emerald-300/30 to-teal-300/30 blur-3xl sm:h-[28rem] sm:w-[28rem]" />
            <div class="float-orb absolute top-1/2 -left-32 h-[20rem] w-[20rem] rounded-full bg-gradient-to-br from-emerald-300/25 to-teal-300/25 blur-3xl sm:h-[24rem] sm:w-[24rem]" />
            <div class="float-orb absolute bottom-0 right-1/3 h-[18rem] w-[18rem] rounded-full bg-gradient-to-br from-amber-200/25 to-rose-200/25 blur-3xl sm:h-[20rem] sm:w-[20rem]" />
            <div class="spin-slow absolute -top-40 -right-40 h-[28rem] w-[28rem] rounded-full opacity-40 sm:h-[36rem] sm:w-[36rem]"
                 style="background: conic-gradient(from 0deg, transparent 0deg, rgba(16,185,129,0.07) 90deg, transparent 180deg, transparent 360deg);" />
            <div class="spin-reverse absolute -bottom-40 -left-40 h-[24rem] w-[24rem] rounded-full opacity-30 sm:h-[32rem] sm:w-[32rem]"
                 style="background: conic-gradient(from 180deg, transparent 0deg, rgba(22,163,74,0.06) 90deg, transparent 220deg, transparent 360deg);" />
            <!-- Grid pattern -->
            <div class="absolute inset-0 opacity-[0.4]"
                 style="background-image: linear-gradient(rgba(15,23,42,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(15,23,42,0.04) 1px, transparent 1px); background-size: 56px 56px;" />
        </div>

        <!-- ============== NAVBAR ============== -->
        <header
            class="anim-fade fixed inset-x-0 top-0 z-50 transition-all duration-500"
            :class="[scrolled ? 'bg-white/85 backdrop-blur-xl shadow-[0_1px_0_rgba(15,23,42,0.06),0_10px_30px_-12px_rgba(15,23,42,0.12)] border-b border-slate-200/70' : 'bg-transparent border-b border-slate-900/[0.06]', 'pt-[env(safe-area-inset-top)]']"
        >
            <div class="mx-auto grid max-w-7xl grid-cols-[auto_1fr_auto] items-center gap-3 px-4 py-3 sm:gap-4 sm:px-6 sm:py-3.5 lg:px-8">
                <Link href="/" class="group flex min-w-0 items-center gap-3">
                    <div
                        class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-gradient-to-br from-emerald-700 to-green-600 text-white shadow-lg shadow-emerald-600/25 transition-all duration-500 group-hover:scale-105 group-hover:shadow-xl group-hover:shadow-emerald-600/40 sm:size-11"
                    >
                        <img v-if="orgLogo" :src="orgLogo" :alt="`Logo ${orgLegalName}`" class="size-full object-contain" />
                        <span v-else class="text-base font-black sm:text-lg">{{ orgInitial }}</span>
                    </div>
                    <div class="min-w-0 hidden sm:block">
                        <p class="truncate text-sm font-bold tracking-tight text-slate-900">{{ orgName }}</p>
                        <p v-if="orgRegion" class="truncate text-[10.5px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ orgRegion }}</p>
                    </div>
                </Link>

                <span class="hidden lg:block" aria-hidden="true" />

                <div class="flex items-center gap-2 sm:gap-3">
                    <nav class="hidden items-center gap-1 lg:flex">
                        <a
                            v-for="link in navAnchors"
                            :key="link.id"
                            :href="`#${link.id}`"
                            @click.prevent="smoothScrollTo(link.id)"
                            class="relative rounded-lg px-3 py-2 text-[13px] font-semibold transition-all duration-300"
                            :class="activeSection === link.id ? 'text-emerald-700' : 'text-slate-600 hover:text-slate-900'"
                        >
                            {{ link.label }}
                            <span
                                class="absolute inset-x-3 -bottom-0.5 h-0.5 origin-left rounded-full bg-gradient-to-r from-emerald-700 to-teal-500 transition-transform duration-500"
                                :class="activeSection === link.id ? 'scale-x-100' : 'scale-x-0'"
                            />
                        </a>
                    </nav>

                    <Link
                        href="/login"
                        class="group relative inline-flex shrink-0 items-center gap-2 overflow-hidden whitespace-nowrap rounded-xl bg-slate-900 px-3 py-2.5 text-[12px] font-bold text-white shadow-lg shadow-slate-900/25 transition-all duration-300 hover:-translate-y-0.5 hover:bg-slate-800 hover:shadow-xl hover:shadow-slate-900/40 sm:px-4 sm:text-[13px]"
                    >
                        <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/15 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" />
                        <AppIcon name="login" class="relative text-base" />
                        <span class="relative hidden min-[420px]:inline">Masuk Sistem</span>
                        <span class="relative min-[420px]:hidden">Masuk</span>
                        <AppIcon name="arrow_forward" class="relative text-sm transition-transform duration-300 group-hover:translate-x-0.5" />
                    </Link>

                    <button
                        type="button"
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-slate-700 shadow-md shadow-slate-900/10 ring-1 ring-slate-200 transition-all duration-300 hover:bg-slate-50 hover:shadow-lg hover:shadow-slate-900/15 sm:size-11 lg:hidden"
                        :aria-label="mobileNavOpen ? 'Tutup navigasi' : 'Buka navigasi'"
                        @click="mobileNavOpen = !mobileNavOpen"
                    >
                        <AppIcon :name="mobileNavOpen ? 'close' : 'menu'" class="text-xl" />
                    </button>
                </div>
            </div>

            <transition name="drawer">
                <div v-if="mobileNavOpen" class="border-t border-slate-200 bg-white/95 backdrop-blur-xl lg:hidden">
                    <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-3">
                        <a
                            v-for="link in navAnchors"
                            :key="link.id"
                            :href="`#${link.id}`"
                            @click.prevent="smoothScrollTo(link.id)"
                            class="rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 hover:text-emerald-700"
                        >{{ link.label }}</a>
                    </nav>
                </div>
            </transition>
        </header>

        <main class="relative z-10">
            <!-- ============== HERO ============== -->
            <section class="relative px-4 pt-16 pb-10 sm:px-6 sm:pt-20 sm:pb-12 lg:px-8 lg:pt-24 lg:pb-14">
                <div class="mx-auto max-w-5xl">
                    <!-- Eyebrow badge — centered -->
                    <div class="anim-fade-up mb-7 flex justify-center">
                        <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-700 shadow-sm ring-1 ring-slate-200">
                            <span class="relative grid size-1.5 place-items-center">
                                <span class="absolute inset-0 animate-ping rounded-full bg-emerald-500/70" />
                                <span class="relative size-1.5 rounded-full bg-emerald-500" />
                            </span>
                            Situs Resmi · {{ tenant?.code?.toUpperCase() || 'TENANT' }}
                        </span>
                    </div>

                    <div class="text-center">
                        <h1 class="anim-fade-up break-words text-[1.6rem] font-black leading-[1.12] tracking-[-0.025em] text-slate-900 sm:text-3xl lg:text-4xl">
                            {{ orgLegalName }}
                        </h1>
                        <p class="anim-fade-up mx-auto mt-3 max-w-2xl text-[14.5px] font-medium leading-relaxed text-slate-700 sm:text-[15px]">
                            {{ heroTagline }}
                        </p>
                        <p class="anim-fade-up mx-auto mt-3 max-w-2xl text-[13.5px] leading-relaxed text-slate-600 sm:text-[14px]">
                            {{ heroDescription }}
                        </p>

                        <div class="anim-fade-up mt-7 flex flex-wrap items-center justify-center gap-2.5 sm:mt-8 sm:gap-3">
                            <Link
                                href="/login"
                                class="group inline-flex items-center gap-2 whitespace-nowrap rounded-lg bg-gradient-to-r from-emerald-700 to-teal-500 px-4 py-2.5 text-[13px] font-bold text-white shadow-lg shadow-emerald-600/25 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-emerald-600/40"
                            >
                                <AppIcon name="login" class="text-base" />
                                Masuk Sistem
                                <AppIcon name="arrow_forward" class="text-sm transition-transform duration-300 group-hover:translate-x-1" />
                            </Link>
                            <Link
                                href="#layanan"
                                @click.prevent="smoothScrollTo('layanan')"
                                class="group inline-flex items-center gap-2 whitespace-nowrap rounded-lg bg-white px-4 py-2.5 text-[13px] font-bold text-slate-700 shadow-sm ring-1 ring-slate-200 transition-all duration-300 hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-md"
                            >
                                <AppIcon name="play_circle" class="text-base text-emerald-700" />
                                Jelajahi Layanan
                            </Link>
                        </div>

                        <!-- Quick info pills — centered -->
                        <div v-if="orgPhone || orgEmail" class="anim-fade-up mt-6 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 px-2 text-[12.5px] text-slate-600 sm:mt-7 sm:text-[13px]">
                            <a v-if="orgPhone" :href="`tel:${orgPhone}`" class="group inline-flex max-w-full items-center gap-2 transition-colors hover:text-emerald-700">
                                <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-white shadow-md shadow-slate-900/10 ring-1 ring-slate-200 transition-all group-hover:ring-emerald-200 group-hover:shadow-lg group-hover:shadow-emerald-600/20">
                                    <AppIcon name="call" class="text-sm text-emerald-700" />
                                </span>
                                <span class="truncate font-semibold">{{ orgPhone }}</span>
                            </a>
                            <a v-if="orgEmail" :href="`mailto:${orgEmail}`" class="group inline-flex max-w-full items-center gap-2 transition-colors hover:text-emerald-700">
                                <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-white shadow-md shadow-slate-900/10 ring-1 ring-slate-200 transition-all group-hover:ring-emerald-200 group-hover:shadow-lg group-hover:shadow-emerald-600/20">
                                    <AppIcon name="mail" class="text-sm text-emerald-700" />
                                </span>
                                <span class="truncate font-semibold">{{ orgEmail }}</span>
                            </a>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="scale-group mt-14 sm:mt-20 lg:mt-24">
                        <div class="reveal-scale relative">
                            <div class="grid grid-cols-2 gap-y-8 gap-x-3 sm:gap-x-6 lg:grid-cols-4 lg:gap-y-0 lg:gap-x-0">
                                <div
                                    v-for="(s, idx) in stats"
                                    :key="idx"
                                    class="group relative min-w-0 px-2 sm:px-5 lg:px-6"
                                    :class="[
                                        idx === 0 ? 'lg:pl-0' : '',
                                        idx === stats.length - 1 ? 'lg:pr-0' : '',
                                    ]"
                                >
                                    <!-- Vertical divider on desktop (between items) -->
                                    <span
                                        v-if="idx > 0"
                                        class="pointer-events-none absolute inset-y-3 left-0 hidden w-px bg-gradient-to-b from-transparent via-slate-300 to-transparent lg:block"
                                        aria-hidden="true"
                                    />
                                    <!-- Top row: icon + number -->
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-emerald-500/10 to-teal-500/10 text-emerald-700 ring-1 ring-emerald-100 transition-transform duration-500 group-hover:scale-110 sm:size-10">
                                            <AppIcon :name="s.icon" class="text-base sm:text-lg" />
                                        </span>
                                        <span class="font-mono text-[10px] font-bold tracking-[0.18em] text-slate-400">0{{ idx + 1 }}</span>
                                    </div>
                                    <!-- Label -->
                                    <p class="mt-4 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500 sm:text-[10.5px] sm:tracking-[0.22em]">{{ s.top }}</p>
                                    <!-- Value (big, gradient) -->
                                    <div class="mt-2 flex items-baseline gap-1 sm:mt-2.5">
                                        <span class="bg-gradient-to-br from-emerald-700 via-green-600 to-teal-500 bg-clip-text text-[1.9rem] font-black leading-[1.05] tracking-[-0.03em] text-transparent sm:text-[2.5rem]">{{ s.value }}</span>
                                    </div>
                                    <!-- Caption -->
                                    <p class="mt-2.5 text-[12px] font-medium leading-[1.5] text-slate-500 sm:mt-3 sm:text-[13px]">{{ s.label }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============== SECTION DIVIDER 02 → 03 ============== -->
            <div class="section-divider relative px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="relative flex items-center justify-center">
                        <svg class="block h-2 w-full overflow-visible" viewBox="0 0 1000 8" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="dividerGrad2" x1="0" x2="1" y1="0" y2="0">
                                    <stop offset="0%" stop-color="#10b981" stop-opacity="0" />
                                    <stop offset="15%" stop-color="#10b981" stop-opacity="0.55" />
                                    <stop offset="50%" stop-color="#10b981" stop-opacity="0.7" />
                                    <stop offset="85%" stop-color="#16a34a" stop-opacity="0.55" />
                                    <stop offset="100%" stop-color="#16a34a" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <line class="divider-line" x1="0" y1="4" x2="1000" y2="4" stroke="url(#dividerGrad2)" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="1000" stroke-dashoffset="1000" vector-effect="non-scaling-stroke" />
                        </svg>
                        <span class="divider-dot absolute left-1/2 top-1/2 grid size-2.5 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-500 ring-4 ring-emerald-100" />
                    </div>
                </div>
            </div>

            <!-- ============== LAYANAN ============== -->
            <section id="layanan" class="reveal-group scroll-mt-32 bg-gradient-to-b from-slate-50 via-white to-slate-50 px-4 py-10 sm:px-6 sm:py-14 lg:px-8 lg:py-16">
                <div class="mx-auto max-w-7xl">
                    <div class="grid items-end gap-5 lg:grid-cols-12 lg:gap-4">
                        <div class="lg:col-span-7">
                            <div class="anim-fade-up inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.16em] text-emerald-700 ring-1 ring-emerald-100 sm:text-[11px] sm:tracking-[0.18em]">
                                <span class="grid size-1.5 place-items-center rounded-full bg-emerald-500" />
                                01 · Layanan
                            </div>
                            <h2 class="anim-fade-up mt-4 text-[1.6rem] font-black leading-[1.12] tracking-[-0.02em] text-slate-900 sm:text-3xl sm:leading-[1.15] lg:text-[2rem]">
                                Modul kelola dana
                                <span class="block bg-gradient-to-r from-emerald-700 to-teal-600 bg-clip-text text-transparent">
                                    siap pakai sejak hari pertama.
                                </span>
                            </h2>
                        </div>
                        <p class="anim-fade-up text-[13.5px] leading-relaxed text-slate-600 sm:text-[14px] lg:col-span-5">
                            Dari verifikasi anggota hingga laporan keuangan tahunan, semua kebutuhan operasional BUMDesma tersedia dalam satu sistem yang saling terintegrasi.
                        </p>
                    </div>

                    <div class="mt-8 grid gap-4 sm:mt-10 sm:grid-cols-2 lg:mt-12 lg:grid-cols-3">
                        <div
                            v-for="(m, idx) in layananModul"
                            :key="m.num"
                            class="reveal-item group relative overflow-hidden rounded-2xl bg-white p-5 shadow-lg ring-1 ring-slate-200/70 transition-all duration-500 hover:-translate-y-1 hover:shadow-xl sm:p-6"
                            :class="`shadow-${toneStyles[m.tone].defaultTone} hover:${toneStyles[m.tone].ring} hover:${toneStyles[m.tone].accent}`"
                            :style="`box-shadow: 0 1px 0 rgba(255,255,255,0.9) inset, 0 12px 32px -12px ${toneStyles[m.tone].shadowRgb};`"
                        >
                            <!-- Subtle glow -->
                            <div
                                class="absolute -top-16 -right-16 h-32 w-32 rounded-full opacity-0 blur-2xl transition-opacity duration-500 group-hover:opacity-60"
                                :class="`bg-gradient-to-br ${toneStyles[m.tone].grad}`"
                            />

                            <div class="relative">
                                <!-- Top row: tag label (left) + period (right) -->
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <span
                                            class="grid size-9 shrink-0 place-items-center rounded-lg text-white shadow-sm transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3 sm:size-10"
                                            :class="`bg-gradient-to-br ${toneStyles[m.tone].grad}`"
                                            :style="`box-shadow: 0 8px 16px -6px ${toneStyles[m.tone].shadowRgb};`"
                                        >
                                            <AppIcon :name="m.icon" class="text-base sm:text-[17px]" />
                                        </span>
                                        <p class="truncate text-[10.5px] font-bold uppercase tracking-[0.14em]" :class="toneStyles[m.tone].text">
                                            {{ m.tag }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 text-[10.5px] font-semibold text-slate-400">· {{ m.num }}</span>
                                </div>

                                <!-- Title (big, slate-900) -->
                                <h3 class="mt-4 text-[1.25rem] font-black leading-tight tracking-[-0.015em] text-slate-900 sm:text-[1.4rem]">
                                    {{ m.title }}
                                </h3>

                                <!-- Description (muted) -->
                                <p class="mt-1.5 text-[12.5px] leading-relaxed text-slate-500 sm:text-[13px]">{{ m.desc }}</p>

                                <!-- Period (right-aligned, smaller) -->
                                <p class="mt-4 text-right text-[11px] font-medium text-slate-400">
                                    Periode <span class="font-semibold text-slate-500">{{ m.period }}</span>
                                </p>

                                <!-- Divider -->
                                <div class="my-4 h-px bg-gradient-to-r from-transparent via-slate-200 to-transparent" />

                                <!-- Bottom stats: 2 angka seperti "19 Kelompok / 29 Individu" -->
                                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1.5">
                                    <div v-for="(stat, si) in m.stats" :key="si" class="flex min-w-0 items-baseline gap-1">
                                        <span
                                            class="whitespace-nowrap text-[15px] font-black tabular-nums sm:text-[16px]"
                                            :class="stat.highlight ? toneStyles[m.tone].text : 'text-slate-900'"
                                        >{{ stat.value }}</span>
                                        <span class="truncate text-[11.5px] font-medium text-slate-500 sm:text-[12px]">{{ stat.label }}</span>
                                    </div>
                                </div>

                                <!-- Hover reveal: "Pelajari modul" -->
                                <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 opacity-0 transition-all duration-500 group-hover:opacity-100">
                                    <span class="text-[11.5px] font-semibold text-slate-500">Detail modul tersedia setelah login</span>
                                    <span class="inline-flex items-center gap-1 text-[12px] font-bold" :class="toneStyles[m.tone].text">
                                        Pelajari
                                        <AppIcon name="arrow_forward" class="text-[13px] transition-transform duration-300 group-hover:translate-x-1" />
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============== SECTION DIVIDER 03 → 04 ============== -->
            <div class="section-divider relative px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="relative flex items-center justify-center">
                        <svg class="block h-2 w-full overflow-visible" viewBox="0 0 1000 8" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="dividerGrad3" x1="0" x2="1" y1="0" y2="0">
                                    <stop offset="0%" stop-color="#f59e0b" stop-opacity="0" />
                                    <stop offset="15%" stop-color="#f59e0b" stop-opacity="0.55" />
                                    <stop offset="50%" stop-color="#ec4899" stop-opacity="0.7" />
                                    <stop offset="85%" stop-color="#10b981" stop-opacity="0.55" />
                                    <stop offset="100%" stop-color="#10b981" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <line class="divider-line" x1="0" y1="4" x2="1000" y2="4" stroke="url(#dividerGrad3)" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="1000" stroke-dashoffset="1000" vector-effect="non-scaling-stroke" />
                        </svg>
                        <span class="divider-dot absolute left-1/2 top-1/2 grid size-2.5 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-gradient-to-br from-amber-500 to-orange-500 ring-4 ring-amber-100" />
                    </div>
                </div>
            </div>

            <!-- ============== PRODUK · KELOMPOK & INDIVIDU ============== -->
            <section id="produk" class="reveal-group scroll-mt-32 px-4 py-10 sm:px-6 sm:py-14 lg:px-8 lg:py-16">
                <div class="mx-auto max-w-7xl">
                    <div class="mx-auto max-w-3xl px-2 text-center sm:px-0">
                        <div class="anim-fade-up inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.16em] text-amber-700 ring-1 ring-amber-100 sm:text-[11px] sm:tracking-[0.18em]">
                            <span class="grid size-1.5 place-items-center rounded-full bg-amber-500" />
                                02 · Produk Pinjaman
                        </div>
                        <h2 class="anim-fade-up mt-4 text-[1.6rem] font-black leading-[1.12] tracking-[-0.02em] text-slate-900 sm:text-3xl sm:leading-[1.15] lg:text-[2rem]">
                            Dua skema, satu
                            <span class="relative inline-block">
                                <span class="absolute inset-x-0 bottom-1 h-3 bg-gradient-to-r from-emerald-200 to-green-200 sm:bottom-2 sm:h-4" />
                                <span class="relative">tujuan.</span>
                            </span>
                        </h2>
                        <p class="anim-fade-up mt-4 text-[14px] leading-relaxed text-slate-600 sm:mt-5 sm:text-[15px]">
                            Pilih skema yang sesuai dengan kebutuhan dan profil risiko peminjam. Kedua jenis pinjaman tercatat otomatis dalam laporan.
                        </p>
                    </div>

                    <div class="mt-8 grid gap-5 lg:mt-10 lg:grid-cols-2">
                        <div
                            v-for="(p, idx) in produkCards"
                            :key="p.key"
                            class="reveal-item group relative overflow-hidden rounded-3xl bg-white p-5 shadow-lg shadow-emerald-600/[0.06] ring-1 ring-slate-200/70 transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-600/15 sm:p-6"
                        >
                            <!-- Top color band -->
                            <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r" :class="toneStyles[p.tone].grad" />
                            <!-- Subtle glow -->
                            <div class="absolute -top-20 -right-20 h-48 w-48 rounded-full opacity-30 blur-3xl transition-opacity duration-500 group-hover:opacity-50"
                                 :class="`bg-gradient-to-br ${toneStyles[p.tone].grad}`" />

                            <div class="relative">
                                <!-- Header -->
                                <div class="flex items-start justify-between gap-3 sm:gap-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-xl text-white shadow-md transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3 sm:size-11"
                                              :class="`bg-gradient-to-br ${toneStyles[p.tone].grad}`"
                                              :style="`box-shadow: 0 12px 24px -8px rgba(0,0,0,0.2);`">
                                            <AppIcon :name="p.icon" class="text-lg sm:text-xl" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-[10px] font-bold uppercase tracking-[0.2em]" :class="toneStyles[p.tone].text">Pinjaman {{ p.key === 'kelompok' ? 'Kelompok' : 'Individu' }}</p>
                                            <h3 class="truncate text-[15px] font-black tracking-tight text-slate-900 sm:text-base">{{ idx === 0 ? 'Solidaritas & gotong-royong' : 'Fleksibel & personal' }}</h3>
                                        </div>
                                    </div>
                                    <span class="shrink-0 whitespace-nowrap rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider"
                                          :class="`${toneStyles[p.tone].bg} ${toneStyles[p.tone].text}`">
                                        {{ idx === 0 ? 'Rekomendasi' : 'Plafon Besar' }}
                                    </span>
                                </div>

                                <p class="mt-5 text-[13.5px] leading-relaxed text-slate-600 sm:text-[14px]">
                                    {{ p.ringkasan }}
                                </p>
                                <p class="mt-3 text-[12.5px] leading-relaxed text-slate-500 sm:text-[13px]">
                                    {{ p.desc }}
                                </p>

                                <!-- Stats grid -->
                                <div class="mt-4 grid grid-cols-2 gap-2.5">
                                    <div
                                        v-for="(s, i) in p.stats"
                                        :key="i"
                                        class="group/stat relative min-w-0 overflow-hidden rounded-xl bg-slate-50 p-3 shadow-sm shadow-slate-900/[0.05] ring-1 ring-slate-200/70 transition-all duration-300 hover:bg-white hover:shadow-md hover:shadow-emerald-600/10 sm:p-3.5"
                                    >
                                        <div class="flex min-w-0 items-center gap-2">
                                            <span class="grid size-6 shrink-0 place-items-center rounded-lg text-white shadow-sm sm:size-7"
                                                  :class="`bg-gradient-to-br ${toneStyles[p.tone].grad}`">
                                                <AppIcon :name="s.icon" class="text-[10px] sm:text-xs" />
                                            </span>
                                            <span class="truncate text-[9.5px] font-bold uppercase tracking-[0.16em] text-slate-500 sm:text-[10px] sm:tracking-[0.18em]">{{ s.label }}</span>
                                        </div>
                                        <p class="mt-1.5 truncate text-[15px] font-black tracking-tight tabular-nums text-slate-900 sm:text-lg">{{ s.value }}</p>
                                    </div>
                                </div>

                                <!-- Manfaat + Syarat -->
                                <div class="mt-4 grid gap-2.5 sm:grid-cols-2">
                                    <div class="rounded-2xl bg-gradient-to-br from-slate-50 to-white p-3.5 shadow-sm shadow-slate-900/[0.04] ring-1 ring-slate-200/70 sm:p-4">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.2em]" :class="toneStyles[p.tone].text">· Manfaat</p>
                                        <ul class="mt-3 space-y-2">
                                            <li v-for="(b, i) in p.manfaat" :key="i" class="flex items-start gap-2 text-[12.5px] leading-snug text-slate-700">
                                                <span class="mt-0.5 grid size-4 shrink-0 place-items-center rounded-full text-white"
                                                      :class="`bg-gradient-to-br ${toneStyles[p.tone].grad}`">
                                                    <AppIcon name="check" class="text-[9px] font-black" />
                                                </span>
                                                <span class="min-w-0">{{ b }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="rounded-2xl bg-gradient-to-br from-slate-50 to-white p-3.5 shadow-sm shadow-slate-900/[0.04] ring-1 ring-slate-200/70 sm:p-4">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.2em]" :class="toneStyles[p.tone].text">· Syarat</p>
                                        <ul class="mt-3 space-y-2">
                                            <li v-for="(s, i) in p.syarat" :key="i" class="flex items-start gap-2 text-[12.5px] leading-snug text-slate-700">
                                                <span class="mt-0.5 font-mono text-[10px] font-black tabular-nums" :class="toneStyles[p.tone].text">0{{ i + 1 }}</span>
                                                <span class="min-w-0">{{ s }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- CTA -->
                                <div class="mt-4 flex flex-col gap-2.5 border-t border-slate-200 pt-3.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                                    <span class="text-[12px] font-medium text-slate-500">Detail tersedia setelah login</span>
                                    <Link href="/login" class="group/btn inline-flex shrink-0 items-center gap-1.5 text-[12.5px] font-bold transition-colors" :class="toneStyles[p.tone].text">
                                        Lihat di sistem
                                        <AppIcon name="arrow_forward" class="text-sm transition-transform duration-300 group-hover/btn:translate-x-1" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============== SECTION DIVIDER 04 → 05 ============== -->
            <div class="section-divider relative px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="relative flex items-center justify-center">
                        <svg class="block h-2 w-full overflow-visible" viewBox="0 0 1000 8" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="dividerGrad4" x1="0" x2="1" y1="0" y2="0">
                                    <stop offset="0%" stop-color="#16a34a" stop-opacity="0" />
                                    <stop offset="15%" stop-color="#16a34a" stop-opacity="0.55" />
                                    <stop offset="50%" stop-color="#10b981" stop-opacity="0.7" />
                                    <stop offset="85%" stop-color="#10b981" stop-opacity="0.55" />
                                    <stop offset="100%" stop-color="#10b981" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <line class="divider-line" x1="0" y1="4" x2="1000" y2="4" stroke="url(#dividerGrad4)" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="1000" stroke-dashoffset="1000" vector-effect="non-scaling-stroke" />
                        </svg>
                        <span class="divider-dot absolute left-1/2 top-1/2 grid size-2.5 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-gradient-to-br from-green-500 to-teal-500 ring-4 ring-green-100" />
                    </div>
                </div>
            </div>

            <!-- ============== ALUR ============== -->
            <section id="alur" class="reveal-group scroll-mt-32 bg-gradient-to-b from-slate-50 via-white to-slate-50 px-4 py-10 sm:px-6 sm:py-14 lg:px-8 lg:py-16">
                <div class="mx-auto max-w-7xl">
                    <div class="mx-auto max-w-3xl px-2 text-center sm:px-0">
                        <div class="anim-fade-up inline-flex items-center gap-2 rounded-full bg-green-50 px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.16em] text-green-800 ring-1 ring-green-100 sm:text-[11px] sm:tracking-[0.18em]">
                            <span class="grid size-1.5 place-items-center rounded-full bg-green-500" />
                                03 · Alur Layanan
                        </div>
                        <h2 class="anim-fade-up mt-4 text-[1.6rem] font-black leading-[1.12] tracking-[-0.02em] text-slate-900 sm:text-3xl sm:leading-[1.15] lg:text-[2rem]">
                            Empat langkah mudah,
                            <span class="block bg-gradient-to-r from-green-700 to-teal-500 bg-clip-text text-transparent">
                                terdokumentasi otomatis.
                            </span>
                        </h2>
                    </div>

                    <div class="mt-8 lg:mt-10">
                        <div class="grid gap-3.5 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4 lg:gap-3">
                            <div
                                v-for="(step, idx) in alur"
                                :key="step.num"
                                class="reveal-item group relative overflow-hidden rounded-xl bg-white p-4 shadow-lg shadow-green-600/[0.07] ring-1 ring-slate-200/70 transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:shadow-green-600/20 hover:ring-green-200 sm:p-5 lg:p-6"
                            >
                                <div class="absolute -top-12 -right-12 h-32 w-32 rounded-full bg-gradient-to-br from-green-500/10 to-teal-500/10 opacity-0 blur-2xl transition-opacity duration-500 group-hover:opacity-100" />
                                <div class="relative">
                                    <div class="flex items-center justify-between">
                                        <span class="grid size-9 place-items-center rounded-lg bg-gradient-to-br from-green-500 to-teal-500 text-white shadow-md shadow-green-600/25 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3 sm:size-10">
                                            <AppIcon :name="step.icon" class="text-base sm:text-[17px]" />
                                        </span>
                                        <span class="font-mono text-[11px] font-black tracking-wider text-slate-300">{{ step.num }}</span>
                                    </div>
                                    <h3 class="mt-4 text-[15px] font-black leading-tight text-slate-900 sm:text-base">{{ step.title }}</h3>
                                    <p class="mt-2 text-[12.5px] leading-relaxed text-slate-600 sm:text-[13px]">{{ step.desc }}</p>
                                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-[10px] font-bold uppercase tracking-[0.16em] sm:text-[10.5px] sm:tracking-[0.18em]">
                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-slate-700">{{ step.role }}</span>
                                        <span class="text-slate-400">·</span>
                                        <span class="text-green-700">{{ step.duration }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============== SECTION DIVIDER 05 → 06 ============== -->
            <div class="section-divider relative px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="relative flex items-center justify-center">
                        <svg class="block h-2 w-full overflow-visible" viewBox="0 0 1000 8" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="dividerGrad5" x1="0" x2="1" y1="0" y2="0">
                                    <stop offset="0%" stop-color="#475569" stop-opacity="0" />
                                    <stop offset="15%" stop-color="#475569" stop-opacity="0.55" />
                                    <stop offset="50%" stop-color="#1e293b" stop-opacity="0.75" />
                                    <stop offset="85%" stop-color="#0f172a" stop-opacity="0.6" />
                                    <stop offset="100%" stop-color="#0f172a" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <line class="divider-line" x1="0" y1="4" x2="1000" y2="4" stroke="url(#dividerGrad5)" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="1000" stroke-dashoffset="1000" vector-effect="non-scaling-stroke" />
                        </svg>
                        <span class="divider-dot absolute left-1/2 top-1/2 grid size-2.5 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-gradient-to-br from-slate-700 to-slate-900 ring-4 ring-slate-200" />
                    </div>
                </div>
            </div>

            <!-- ============== REGULASI ============== -->
            <section id="regulasi" class="reveal-group scroll-mt-32 px-4 py-10 sm:px-6 sm:py-14 lg:px-8 lg:py-16">
                <div class="mx-auto max-w-7xl">
                    <div class="grid items-end gap-5 lg:grid-cols-12 lg:gap-4">
                        <div class="lg:col-span-7">
                            <div class="anim-fade-up inline-flex items-center gap-2 rounded-full bg-slate-200/70 px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.16em] text-slate-700 ring-1 ring-slate-300 sm:text-[11px] sm:tracking-[0.18em]">
                                <span class="grid size-1.5 place-items-center rounded-full bg-slate-700" />
                                04 · Standar &amp; Regulasi
                            </div>
                            <h2 class="anim-fade-up mt-4 text-[1.6rem] font-black leading-[1.12] tracking-[-0.02em] text-slate-900 sm:text-3xl sm:leading-[1.15] lg:text-[2rem]">
                                Selaras dengan
                                <span class="bg-gradient-to-r from-slate-700 via-slate-900 to-black bg-clip-text text-transparent">
                                    kerangka hukum nasional.
                                </span>
                            </h2>
                        </div>
                        <p class="anim-fade-up text-[13.5px] leading-relaxed text-slate-600 sm:text-[14px] lg:col-span-5">
                            Sistem mengikuti PP No. 11/2021, standar akuntansi SAK EP/ETAP, arsitektur basis data terisolasi per entitas, dan payment gateway nasional.
                        </p>
                    </div>

                    <div class="mt-8 grid gap-4 sm:mt-10 sm:grid-cols-2 lg:mt-12 lg:grid-cols-4">
                        <div
                            v-for="(r, i) in regulasi"
                            :key="i"
                            class="reveal-item group relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4 text-white shadow-xl shadow-slate-900/40 ring-1 ring-slate-700/50 transition-all duration-500 hover:-translate-y-1 hover:shadow-2xl hover:shadow-slate-900/50 sm:p-6"
                        >
                            <div class="absolute -top-16 -right-16 h-36 w-36 rounded-full opacity-30 blur-3xl transition-opacity duration-500 group-hover:opacity-60"
                                 :class="`bg-gradient-to-br ${toneStyles[r.tone].grad}`" />
                            <div class="relative">
                                <div class="flex items-center justify-between">
                                    <span class="grid size-10 place-items-center rounded-xl text-white shadow-md transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3 sm:size-11"
                                          :class="`bg-gradient-to-br ${toneStyles[r.tone].grad}`">
                                        <AppIcon :name="r.icon" class="text-lg sm:text-xl" />
                                    </span>
                                    <span class="font-mono text-[11px] font-black tracking-wider text-slate-500">0{{ i + 1 }}</span>
                                </div>
                                <h3 class="mt-4 text-[15px] font-black leading-tight tracking-tight sm:text-base">{{ r.label }}</h3>
                                <p class="mt-1 text-[10.5px] font-bold uppercase tracking-[0.16em] sm:text-[11px] sm:tracking-[0.18em]" :class="toneStyles[r.tone].text">{{ r.sub }}</p>
                                <p class="mt-3 text-[12.5px] leading-relaxed text-slate-300">{{ r.desc }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============== BERITA (POSTS) ============== -->
            <section id="berita" class="reveal-group scroll-mt-32 bg-gradient-to-b from-slate-50 via-white to-slate-50 px-4 py-10 sm:px-6 sm:py-14 lg:px-8 lg:py-16">
                <div class="mx-auto max-w-7xl">
                    <div class="grid items-end gap-5 lg:grid-cols-12 lg:gap-4">
                        <div class="lg:col-span-7">
                            <div class="anim-fade-up inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.16em] text-emerald-700 ring-1 ring-emerald-200 sm:text-[11px] sm:tracking-[0.18em]">
                                <span class="grid size-1.5 place-items-center rounded-full bg-emerald-500" />
                                05 · Kabar &amp; Pengumuman
                            </div>
                            <h2 class="anim-fade-up mt-4 text-[1.6rem] font-black leading-[1.12] tracking-[-0.02em] text-slate-900 sm:text-3xl sm:leading-[1.15] lg:text-[2rem]">
                                Berita &amp;
                                <span class="bg-gradient-to-r from-emerald-700 via-teal-600 to-emerald-500 bg-clip-text text-transparent">
                                    pengumuman terbaru.
                                </span>
                            </h2>
                        </div>
                        <p class="anim-fade-up text-[13.5px] leading-relaxed text-slate-600 sm:text-[14px] lg:col-span-5">
                            Update harian dari sekretariat: berita kegiatan, pengumuman, dan dokumentasi perguliran dana yang dipublikasikan lewat panel admin.
                        </p>
                    </div>

                    <div v-if="recent_posts && recent_posts.length > 0" class="mt-8 grid gap-4 sm:mt-10 lg:mt-12 md:grid-cols-3">
                        <Link
                            v-for="post in recent_posts"
                            :key="post.slug"
                            :href="`/berita/${post.slug}`"
                            class="reveal-item group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-md shadow-slate-900/[0.04] ring-1 ring-slate-200 transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-900/10 hover:ring-emerald-200"
                        >
                            <div class="relative aspect-[16/9] overflow-hidden bg-gradient-to-br from-emerald-100 via-emerald-50 to-teal-50">
                                <img v-if="post.cover_image_url" :src="post.cover_image_url" :alt="post.title" class="size-full object-cover transition-transform duration-700 group-hover:scale-105" />
                                <div v-else class="grid size-full place-items-center">
                                    <AppIcon name="article" class="text-5xl text-emerald-300" />
                                </div>
                                <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-slate-900/40 to-transparent" />
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <p class="text-[10.5px] font-bold uppercase tracking-[0.16em] text-emerald-700">{{ formatPostDate(post.published_at) }}</p>
                                <h3 class="mt-2 line-clamp-2 text-[15.5px] font-black leading-tight tracking-tight text-slate-900 sm:text-base">{{ post.title }}</h3>
                                <p v-if="post.excerpt" class="mt-2 line-clamp-3 text-[12.5px] leading-relaxed text-slate-600 sm:text-[13px]">{{ post.excerpt }}</p>
                                <span class="mt-4 inline-flex items-center gap-1.5 text-[12px] font-bold text-emerald-700 transition-transform duration-300 group-hover:translate-x-0.5">
                                    Baca selengkapnya
                                    <AppIcon name="arrow_forward" class="text-sm" />
                                </span>
                            </div>
                        </Link>
                    </div>

                    <div v-else class="reveal-item mt-8 grid place-items-center rounded-2xl border border-dashed border-slate-300 bg-white/60 p-10 text-center sm:mt-10 lg:mt-12">
                        <div class="grid size-12 place-items-center rounded-xl bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                            <AppIcon name="campaign" class="text-2xl" />
                        </div>
                        <p class="mt-4 text-[14px] font-bold text-slate-900">Belum ada publikasi berita.</p>
                        <p class="mt-1 max-w-md text-[12.5px] text-slate-600">Berita akan tampil di sini setelah admin mengirim publikasi melalui menu <strong>Website → Berita</strong>.</p>
                    </div>

                    <div v-if="recent_posts && recent_posts.length > 0" class="mt-8 flex justify-center sm:mt-10">
                        <Link href="/berita" class="group inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-[13px] font-bold text-slate-700 shadow-sm ring-1 ring-slate-200 transition-all duration-300 hover:-translate-y-0.5 hover:bg-slate-50 hover:text-emerald-700 hover:shadow-md">
                            Lihat semua berita
                            <AppIcon name="arrow_forward" class="text-sm transition-transform duration-300 group-hover:translate-x-1" />
                        </Link>
                    </div>
                </div>
            </section>

            <!-- ============== HALAMAN STATIS (PAGES) ============== -->
            <!-- ============== HALAMAN STATIS (PAGES) ============== -->
            <section id="halaman" class="reveal-group scroll-mt-32 px-4 py-10 sm:px-6 sm:py-14 lg:px-8 lg:py-16">
                <div class="mx-auto max-w-7xl">
                    <div class="flex flex-wrap items-end justify-between gap-5">
                        <div class="max-w-2xl">
                            <div class="anim-fade-up inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.16em] text-amber-700 ring-1 ring-amber-200 sm:text-[11px] sm:tracking-[0.18em]">
                                <span class="grid size-1.5 place-items-center rounded-full bg-amber-500" />
                                06 · Halaman Informasi
                            </div>
                            <h2 class="anim-fade-up mt-4 text-[1.6rem] font-black leading-[1.12] tracking-[-0.02em] text-slate-900 sm:text-3xl sm:leading-[1.15] lg:text-[2rem]">
                                Halaman
                                <span class="bg-gradient-to-r from-amber-600 via-orange-600 to-amber-500 bg-clip-text text-transparent">
                                    profil &amp; layanan.
                                </span>
                            </h2>
                            <p class="anim-fade-up mt-3 text-[13.5px] leading-relaxed text-slate-600 sm:text-[14px]">
                                Halaman statis yang dikelola admin: profil lembaga, layanan, AD/ART, dan dokumen publik lain yang dapat diakses pengunjung.
                            </p>
                        </div>
                        <Link href="/halaman" class="anim-fade-up group inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-white px-3.5 py-2 text-[11.5px] font-bold uppercase tracking-[0.14em] text-amber-700 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:bg-amber-50 hover:shadow-md">
                            Lihat semua halaman
                            <AppIcon name="arrow_forward" class="text-xs transition-transform duration-300 group-hover:translate-x-0.5" />
                        </Link>
                    </div>

                    <div v-if="recent_pages && recent_pages.length > 0" class="mt-8 grid gap-3 sm:mt-10 sm:grid-cols-2 lg:mt-12 lg:grid-cols-3">
                        <Link
                            v-for="page in recent_pages"
                            :key="page.slug"
                            :href="`/p/${page.slug}`"
                            class="reveal-item group flex items-start gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition-all duration-300 hover:-translate-y-0.5 hover:bg-amber-50/50 hover:shadow-md hover:ring-amber-200 sm:p-5"
                        >
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-amber-100 to-orange-100 text-amber-700 ring-1 ring-amber-200 transition-transform duration-300 group-hover:scale-110 sm:size-11">
                                <AppIcon name="description" class="text-lg sm:text-xl" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-[14px] font-black leading-tight tracking-tight text-slate-900 sm:text-[15px]">{{ page.title }}</h3>
                                <p v-if="page.excerpt" class="mt-1 line-clamp-2 text-[11.5px] leading-relaxed text-slate-600 sm:text-[12px]">{{ page.excerpt }}</p>
                                <span class="mt-2 inline-flex items-center gap-1 text-[11.5px] font-bold text-amber-700">
                                    Buka halaman
                                    <AppIcon name="arrow_forward" class="text-xs transition-transform duration-300 group-hover:translate-x-0.5" />
                                </span>
                            </div>
                        </Link>
                    </div>

                    <!-- Empty state when admin has not published any pages yet -->
                    <div v-else class="reveal-item mt-8 flex flex-col items-center gap-3 rounded-2xl border border-dashed border-amber-200 bg-amber-50/40 p-8 text-center sm:mt-10 sm:p-10">
                        <span class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-amber-100 to-orange-100 text-amber-700 ring-1 ring-amber-200">
                            <AppIcon name="description" class="text-2xl" />
                        </span>
                        <div class="max-w-md">
                            <p class="text-[14px] font-black text-slate-900">Belum ada halaman yang dipublikasikan</p>
                            <p class="mt-1 text-[12.5px] leading-relaxed text-slate-600">
                                Halaman akan tampil di sini setelah admin mempublikasikan halaman melalui menu <strong>Website → Halaman</strong>. Contoh: profil lembaga, layanan, AD/ART.
                            </p>
                        </div>
                        <Link href="/halaman" class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-amber-600 to-orange-600 px-4 py-2 text-[11px] font-black uppercase tracking-[0.14em] text-white shadow-md transition-all hover:-translate-y-0.5 hover:shadow-lg">
                            Buka halaman indeks
                            <AppIcon name="arrow_forward" class="text-xs" />
                        </Link>
                    </div>
                </div>
            </section>

            <!-- ============== KONTAK + DEMO ============== -->
            <section id="kontak" class="reveal-group scroll-mt-32 relative overflow-hidden px-4 py-10 sm:px-6 sm:py-14 lg:px-8 lg:py-16">
                <!-- Soft gradient background that frames both panels -->
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(14,165,233,0.10),transparent_55%),radial-gradient(ellipse_at_bottom_right,rgba(16,185,129,0.10),transparent_55%)]" />
                <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-slate-300 to-transparent" />

                <div class="relative mx-auto max-w-7xl">
                    <!-- ====== HEADER ====== -->
                    <div class="anim-fade-up max-w-2xl">
                        <div class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.16em] text-slate-700 ring-1 ring-slate-200 backdrop-blur-sm sm:text-[11px] sm:tracking-[0.18em]">
                            <span class="grid size-1.5 place-items-center rounded-full bg-gradient-to-r from-sky-500 to-emerald-500" />
                            07 · Hubungi &amp; Coba Langsung
                        </div>
                        <h2 class="mt-4 text-[1.6rem] font-black leading-[1.12] tracking-[-0.02em] text-slate-900 sm:text-3xl sm:leading-[1.15] lg:text-[2.05rem]">
                            Kirim pesan atau
                            <span class="bg-gradient-to-r from-sky-700 via-blue-600 to-emerald-600 bg-clip-text text-transparent">
                                eksplorasi langsung.
                            </span>
                        </h2>
                    </div>

                    <!-- ====== DUAL PANEL ====== -->
                    <div class="relative mt-8 sm:mt-10 lg:mt-12">
                        <!-- Connector pill (desktop only) -->
                        <div class="pointer-events-none absolute left-1/2 top-1/2 z-10 hidden -translate-x-1/2 -translate-y-1/2 lg:block">
                            <div class="grid size-12 place-items-center rounded-full bg-white shadow-lg ring-1 ring-slate-200">
                                <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">atau</span>
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-2 lg:gap-8">
                            <!-- ============ KIRI: FORM PESAN ============ -->
                            <div class="reveal-item relative overflow-hidden rounded-3xl bg-white p-6 shadow-xl shadow-slate-900/[0.06] ring-1 ring-slate-200/80 sm:p-7">
                                <!-- Decorative top accent -->
                                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-sky-500 via-blue-500 to-indigo-500" />

                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-11 place-items-center rounded-xl bg-gradient-to-br from-sky-100 to-blue-100 text-sky-700 ring-1 ring-sky-200">
                                            <AppIcon name="send" class="text-lg" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-sky-700">Saluran 01</p>
                                            <h3 class="text-[15px] font-black tracking-tight text-slate-900">Kirim Pesan</h3>
                                        </div>
                                    </div>
                                    <span class="hidden rounded-full bg-emerald-50 px-2.5 py-1 text-[9.5px] font-bold uppercase tracking-[0.14em] text-emerald-700 ring-1 ring-emerald-200 sm:inline-flex">Respons &lt; 24 jam</span>
                                </div>

                                <p class="mt-3 text-[12.5px] leading-relaxed text-slate-600">
                                    Pesan akan tersimpan di menu <strong>Pesan Masuk</strong> admin sekretariat.
                                </p>

                                <!-- Info strip -->
                                <div v-if="contact.address || contact.phone || contact.email || contact.social?.facebook || contact.social?.instagram || contact.social?.youtube" class="mt-4 flex flex-wrap gap-1.5">
                                    <a v-if="contact.phone" :href="`tel:${contact.phone}`" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[10.5px] font-bold text-emerald-700 ring-1 ring-emerald-200 transition hover:bg-emerald-100">
                                        <AppIcon name="call" class="text-xs" />{{ contact.phone }}
                                    </a>
                                    <a v-if="contact.email" :href="`mailto:${contact.email}`" class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[10.5px] font-bold text-amber-700 ring-1 ring-amber-200 transition hover:bg-amber-100">
                                        <AppIcon name="mail" class="text-xs" />{{ contact.email }}
                                    </a>
                                    <span v-if="contact.address" class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-1 text-[10.5px] font-bold text-sky-700 ring-1 ring-sky-200">
                                        <AppIcon name="place" class="text-xs" />{{ contact.address }}
                                    </span>
                                    <a v-if="contact.social?.facebook" :href="contact.social.facebook" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full bg-slate-900 px-2.5 py-1 text-[10.5px] font-bold text-white transition hover:bg-sky-600" aria-label="Facebook">Facebook</a>
                                    <a v-if="contact.social?.instagram" :href="contact.social.instagram" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full bg-slate-900 px-2.5 py-1 text-[10.5px] font-bold text-white transition hover:bg-rose-600" aria-label="Instagram">Instagram</a>
                                    <a v-if="contact.social?.youtube" :href="contact.social.youtube" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full bg-slate-900 px-2.5 py-1 text-[10.5px] font-bold text-white transition hover:bg-red-600" aria-label="YouTube">YouTube</a>
                                </div>

                                <!-- Form -->
                                <div class="mt-4">
                                    <div v-if="flashSuccess" class="mb-3 rounded-xl bg-success-container px-3 py-2.5 text-[12.5px] font-medium text-on-success-container">{{ flashSuccess }}</div>
                                    <div v-if="flashError" class="mb-3 rounded-xl bg-error-container px-3 py-2.5 text-[12.5px] font-medium text-on-error-container">{{ flashError }}</div>

                                    <form class="space-y-2.5" @submit.prevent="submitMessage">
                                        <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                                            <label for="hp-website">Website</label>
                                            <input id="hp-website" v-model="messageForm.website" type="text" tabindex="-1" autocomplete="off" />
                                        </div>

                                        <div class="grid gap-2.5 sm:grid-cols-2">
                                            <div>
                                                <label class="mb-1 ml-1 block text-[10px] font-bold uppercase tracking-wider text-slate-600">Nama <span class="text-error">*</span></label>
                                                <input v-model="messageForm.name" type="text" maxlength="120" required placeholder="Nama lengkap" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[12.5px] transition focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20" />
                                                <p v-if="messageForm.errors.name" class="ml-1 mt-0.5 text-[10.5px] text-error">{{ messageForm.errors.name }}</p>
                                            </div>
                                            <div>
                                                <label class="mb-1 ml-1 block text-[10px] font-bold uppercase tracking-wider text-slate-600">Email</label>
                                                <input v-model="messageForm.email" type="email" maxlength="255" placeholder="nama@email.com" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[12.5px] transition focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20" />
                                                <p v-if="messageForm.errors.email" class="ml-1 mt-0.5 text-[10.5px] text-error">{{ messageForm.errors.email }}</p>
                                            </div>
                                        </div>

                                        <div class="grid gap-2.5 sm:grid-cols-2">
                                            <div>
                                                <label class="mb-1 ml-1 block text-[10px] font-bold uppercase tracking-wider text-slate-600">Telepon</label>
                                                <input v-model="messageForm.phone" type="text" maxlength="40" placeholder="08xx-xxxx-xxxx" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[12.5px] transition focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20" />
                                                <p v-if="messageForm.errors.phone" class="ml-1 mt-0.5 text-[10.5px] text-error">{{ messageForm.errors.phone }}</p>
                                            </div>
                                            <div>
                                                <label class="mb-1 ml-1 block text-[10px] font-bold uppercase tracking-wider text-slate-600">Subjek</label>
                                                <input v-model="messageForm.subject" type="text" maxlength="200" placeholder="Perihal pesan" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[12.5px] transition focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20" />
                                                <p v-if="messageForm.errors.subject" class="ml-1 mt-0.5 text-[10.5px] text-error">{{ messageForm.errors.subject }}</p>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="mb-1 ml-1 block text-[10px] font-bold uppercase tracking-wider text-slate-600">Pesan <span class="text-error">*</span></label>
                                            <textarea v-model="messageForm.message" rows="3" maxlength="5000" required placeholder="Tulis pesan Anda di sini..." class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-[12.5px] transition focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"></textarea>
                                            <p v-if="messageForm.errors.message" class="ml-1 mt-0.5 text-[10.5px] text-error">{{ messageForm.errors.message }}</p>
                                        </div>

                                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                                            <p class="inline-flex items-center gap-1 text-[10.5px] text-slate-500">
                                                <AppIcon name="lock" class="text-xs" />Pesan terenkripsi, hanya admin yang dapat membaca.
                                            </p>
                                            <button type="submit" :disabled="messageSending || messageForm.processing" class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-sky-700 to-blue-600 px-4 py-2.5 text-[11px] font-black uppercase tracking-[0.14em] text-white shadow-md shadow-sky-600/25 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-sky-600/40 disabled:opacity-60">
                                                <span v-if="messageSending || messageForm.processing" class="size-3.5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                                                <AppIcon v-else name="send" class="text-sm" />
                                                Kirim Pesan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- ============ KANAN: AKUN DEMO CTA ============ -->
                            <div class="reveal-item relative flex flex-col overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-950 p-6 text-white shadow-xl shadow-emerald-900/30 ring-1 ring-white/10 sm:p-7">
                                <!-- Glow -->
                                <div class="absolute -top-24 -right-24 h-64 w-64 rounded-full bg-emerald-500/30 blur-3xl" />
                                <div class="absolute -bottom-24 -left-24 h-64 w-64 rounded-full bg-sky-500/20 blur-3xl" />
                                <div class="absolute inset-0 opacity-[0.18]"
                                     style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.15) 1px, transparent 0); background-size: 28px 28px;" />

                                <div class="relative flex flex-1 flex-col">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <span class="grid size-11 place-items-center rounded-xl bg-white/10 text-white ring-1 ring-white/20 backdrop-blur-md">
                                                <AppIcon name="rocket_launch" class="text-lg" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-300">Saluran 02</p>
                                                <h3 class="text-[15px] font-black tracking-tight text-white">Akun Demo</h3>
                                            </div>
                                        </div>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2.5 py-1 text-[9.5px] font-bold uppercase tracking-[0.14em] text-emerald-300 ring-1 ring-emerald-400/30">
                                            <span class="relative grid size-1.5 place-items-center">
                                                <span class="absolute inset-0 animate-ping rounded-full bg-emerald-400/70" />
                                                <span class="relative size-1.5 rounded-full bg-emerald-400" />
                                            </span>
                                            Sandbox
                                        </span>
                                    </div>

                                    <p class="mt-3 text-[12.5px] leading-relaxed text-emerald-100/90">
                                        Coba setiap modul dengan akun demo publik — semua eksperimen terjadi di sandbox tanpa menyentuh data produksi.
                                    </p>

                                    <!-- Quick stats -->
                                    <div class="mt-4 grid grid-cols-3 gap-2">
                                        <div class="rounded-xl bg-white/[0.06] p-3 ring-1 ring-white/10 backdrop-blur-sm">
                                            <p class="text-[9.5px] font-bold uppercase tracking-[0.16em] text-emerald-300">Modul</p>
                                            <p class="mt-1 text-[18px] font-black leading-none tracking-tight">4</p>
                                            <p class="mt-0.5 text-[10px] text-emerald-100/70">inti</p>
                                        </div>
                                        <div class="rounded-xl bg-white/[0.06] p-3 ring-1 ring-white/10 backdrop-blur-sm">
                                            <p class="text-[9.5px] font-bold uppercase tracking-[0.16em] text-emerald-300">Laporan</p>
                                            <p class="mt-1 text-[18px] font-black leading-none tracking-tight">30+</p>
                                            <p class="mt-0.5 text-[10px] text-emerald-100/70">PDF siap cetak</p>
                                        </div>
                                        <div class="rounded-xl bg-white/[0.06] p-3 ring-1 ring-white/10 backdrop-blur-sm">
                                            <p class="text-[9.5px] font-bold uppercase tracking-[0.16em] text-emerald-300">Role</p>
                                            <p class="mt-1 text-[18px] font-black leading-none tracking-tight">5</p>
                                            <p class="mt-0.5 text-[10px] text-emerald-100/70">sistem default</p>
                                        </div>
                                    </div>

                                    <!-- Feature checklist -->
                                    <ul class="mt-4 space-y-1.5 text-[12px] text-emerald-50/85">
                                        <li class="flex items-start gap-2"><span class="mt-0.5 grid size-4 shrink-0 place-items-center rounded-full bg-emerald-500/20 ring-1 ring-emerald-400/40"><AppIcon name="check" class="text-[10px] text-emerald-300" /></span>Simulasi kredit, angsuran, kolektibilitas</li>
                                        <li class="flex items-start gap-2"><span class="mt-0.5 grid size-4 shrink-0 place-items-center rounded-full bg-emerald-500/20 ring-1 ring-emerald-400/40"><AppIcon name="check" class="text-[10px] text-emerald-300" /></span>Jurnal otomatis &amp; tutup buku akhir periode</li>
                                        <li class="flex items-start gap-2"><span class="mt-0.5 grid size-4 shrink-0 place-items-center rounded-full bg-emerald-500/20 ring-1 ring-emerald-400/40"><AppIcon name="check" class="text-[10px] text-emerald-300" /></span>Multi-role: admin, kasir, ketua, pengawas</li>
                                    </ul>

                                    <div class="mt-5 flex flex-1 flex-col justify-end gap-2.5">
                                        <Link v-if="orgPhone" :href="`tel:${orgPhone}`" class="group inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-white/20 bg-white/[0.04] px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.14em] text-white backdrop-blur-md transition-all duration-300 hover:-translate-y-0.5 hover:border-white/40 hover:bg-white/10">
                                            <AppIcon name="call" class="text-base" />
                                            Telepon Sekretariat
                                        </Link>
                                        <Link href="/berita" class="group inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-white/20 bg-white/[0.04] px-5 py-3 text-[11.5px] font-bold uppercase tracking-[0.14em] text-emerald-100 backdrop-blur-md transition-all duration-300 hover:-translate-y-0.5 hover:border-white/40 hover:bg-white/10 hover:text-white">
                                            <AppIcon name="campaign" class="text-base" />
                                            Kabar Terbaru
                                            <AppIcon name="arrow_forward" class="text-sm transition-transform duration-300 group-hover:translate-x-1" />
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- ============== FOOTER ============== -->
        <footer class="relative z-10 mt-10 border-t border-slate-200 bg-white/60 backdrop-blur-md sm:mt-12 pb-[max(0px,env(safe-area-inset-bottom))]">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between lg:gap-8">
                    <!-- Brand ringkas -->
                    <div class="flex min-w-0 items-start gap-3 lg:max-w-sm lg:shrink-0">
                        <Link href="/" class="group flex shrink-0 items-center gap-2.5">
                            <span class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-lg bg-gradient-to-br from-emerald-700 to-teal-500 text-white shadow-md shadow-emerald-600/30 transition-transform duration-500 group-hover:scale-105 group-hover:rotate-3">
                                <img v-if="orgLogo" :src="orgLogo" :alt="`Logo ${orgLegalName}`" class="size-full object-contain" />
                                <span v-else class="text-sm font-black">{{ orgInitial }}</span>
                            </span>
                            <div class="min-w-0 text-left">
                                <span class="block truncate text-[13.5px] font-black tracking-tight text-slate-900">{{ orgLegalName }}</span>
                                <span v-if="orgRegion" class="block truncate text-[9.5px] font-bold uppercase tracking-[0.18em] text-slate-500">{{ orgRegion }}</span>
                            </div>
                        </Link>
                    </div>

                    <!-- Tautan + Sekretariat inline -->
                    <div class="flex min-w-0 flex-1 flex-col gap-3">
                        <!-- Baris 1: Tautan horizontal (wrap) -->
                        <div class="flex flex-wrap items-center gap-x-1 gap-y-1.5 text-[11.5px] sm:text-[12px]">
                            <span class="mr-1 text-[9.5px] font-black uppercase tracking-[0.2em] text-slate-400">Tautan</span>
                            <a v-for="l in navAnchors" :key="l.id" :href="`#${l.id}`" @click.prevent="smoothScrollTo(l.id)" class="rounded-full px-2 py-0.5 font-semibold text-slate-600 transition-colors hover:bg-emerald-50 hover:text-emerald-700">
                                {{ l.label }}
                            </a>
                            <span class="mx-0.5 text-slate-300">·</span>
                            <Link href="/berita" class="rounded-full px-2 py-0.5 font-semibold text-slate-600 transition-colors hover:bg-emerald-50 hover:text-emerald-700">Berita</Link>
                            <Link href="/halaman" class="rounded-full px-2 py-0.5 font-semibold text-slate-600 transition-colors hover:bg-emerald-50 hover:text-emerald-700">Halaman</Link>
                            <Link href="/kontak" class="rounded-full px-2 py-0.5 font-semibold text-slate-600 transition-colors hover:bg-emerald-50 hover:text-emerald-700">Kontak</Link>
                            <Link href="/login" class="ml-auto inline-flex items-center gap-1 rounded-full bg-slate-900 px-2.5 py-1 text-[10.5px] font-black uppercase tracking-[0.14em] text-white transition-all hover:-translate-y-0.5 hover:bg-slate-800">
                                <AppIcon name="login" class="text-xs" />Masuk
                            </Link>
                        </div>

                        <!-- Baris 2: Sekretariat chip horizontal -->
                        <div v-if="orgAddress || orgPhone || orgEmail" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-600 sm:text-[11.5px]">
                            <span class="text-[9.5px] font-black uppercase tracking-[0.2em] text-slate-400">Sekretariat</span>
                            <span v-if="orgPhone" class="inline-flex items-center gap-1">
                                <AppIcon name="call" class="text-xs text-emerald-600" />
                                <a :href="`tel:${orgPhone}`" class="font-semibold hover:text-emerald-700">{{ orgPhone }}</a>
                            </span>
                            <span v-if="orgPhone && (orgEmail || orgAddress)" class="text-slate-300">·</span>
                            <span v-if="orgEmail" class="inline-flex items-center gap-1">
                                <AppIcon name="mail" class="text-xs text-emerald-600" />
                                <a :href="`mailto:${orgEmail}`" class="break-all font-semibold hover:text-emerald-700">{{ orgEmail }}</a>
                            </span>
                            <span v-if="orgEmail && orgAddress" class="text-slate-300">·</span>
                            <span v-if="orgAddress" class="inline-flex min-w-0 items-center gap-1">
                                <AppIcon name="place" class="shrink-0 text-xs text-emerald-600" />
                                <span class="truncate font-semibold">{{ orgAddress }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Bottom bar -->
                <div class="mt-5 flex flex-col items-center justify-between gap-1.5 border-t border-slate-200 pt-3 text-center sm:flex-row sm:gap-3 sm:text-left">
                    <p class="text-[11px] text-slate-500 sm:text-[11.5px]">
                        &copy; {{ new Date().getFullYear() }} {{ orgLegalName }}.
                        <span v-if="props.settings?.footer_note"> · {{ props.settings.footer_note }}</span>
                        <span v-else> · Dikelola dengan SIUPK Next.</span>
                    </p>
                    <p class="text-[9.5px] font-bold uppercase tracking-[0.2em] text-slate-400">
                        Powered by SIUPK Next
                    </p>
                </div>
            </div>
        </footer>

        <!-- Scroll-to-top floating button -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            leave-active-class="transition-all duration-200 ease-in"
            enter-from-class="translate-y-3 scale-90 opacity-0"
            enter-to-class="translate-y-0 scale-100 opacity-100"
            leave-from-class="translate-y-0 scale-100 opacity-100"
            leave-to-class="translate-y-3 scale-90 opacity-0"
        >
            <button
                v-show="showScrollTop"
                type="button"
                @click="scrollToTop"
                class="fixed bottom-5 right-5 z-50 inline-flex size-12 items-center justify-center rounded-full bg-emerald-700 text-white shadow-md ring-1 ring-emerald-700/20 transition hover:-translate-y-0.5 hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 sm:bottom-6 sm:right-6"
                aria-label="Kembali ke atas halaman"
                title="Kembali ke atas"
            >
                <AppIcon name="arrow_upward" class="text-2xl" />
            </button>
        </Transition>
    </div>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 280ms cubic-bezier(0.22, 1, 0.36, 1), transform 280ms cubic-bezier(0.22, 1, 0.36, 1);
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}

@media (prefers-reduced-motion: reduce) {
    .anim-fade,
    .anim-fade-up,
    .anim-scale,
    .anim-slide,
    .reveal-item,
    .reveal-side,
    .reveal-scale,
    .float-orb,
    .spin-slow,
    .spin-reverse {
        animation: none !important;
        opacity: 1 !important;
        transform: none !important;
    }
    .drawer-enter-active,
    .drawer-leave-active {
        transition: none;
    }
}
</style>




















































