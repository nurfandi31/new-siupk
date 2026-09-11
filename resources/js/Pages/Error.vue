<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '../Components/AppIcon.vue';
import AppButton from '../Components/AppButton.vue';

const props = defineProps({
    status: {
        type: [Number, String],
        default: 500,
    },
    message: {
        type: String,
        default: '',
    },
});

const errorDetails = computed(() => {
    const code = Number(props.status) || 500;
    const map = {
        400: {
            title: 'Permintaan Tidak Valid',
            category: 'Format & Parameter',
            icon: 'code_off',
            desc: props.message || 'Server tidak dapat memproses permintaan karena format data atau parameter yang dikirim tidak sesuai spesifikasi.',
            tips: [
                'Pastikan data formulir diisi dengan benar.',
                'Hindari memodifikasi URL atau parameter secara manual.',
                'Muat ulang halaman dan ulangi proses input data.',
            ],
        },
        401: {
            title: 'Sesi Belum Terautentikasi',
            category: 'Autentikasi Pengguna',
            icon: 'vpn_key',
            desc: props.message || 'Akses ke modul ini membutuhkan login. Silakan masuk terlebih dahulu untuk melanjutkan pekerjaan Anda.',
            tips: [
                'Masuk menggunakan akun dan kata sandi Anda.',
                'Pastikan tidak menggunakan mode penyamaran jika sesi sering hilang.',
                'Hubungi administrator BUMDesma jika lupa akses.',
            ],
        },
        402: {
            title: 'Langganan Tenant Diperlukan',
            category: 'Langganan & Tagihan',
            icon: 'credit_card',
            desc: props.message || 'Masa aktif langganan instansi BUMDesma Anda memerlukan aktivasi paket untuk melanjutkan fitur ini.',
            tips: [
                'Periksa status tagihan aktif di menu Billing.',
                'Lakukan perpanjangan melalui opsi pembayaran yang tersedia.',
                'Hubungi Admin siupk Next jika sudah melakukan pembayaran.',
            ],
        },
        403: {
            title: 'Akses Dibatasi',
            category: 'Otorisasi & Wewenang',
            icon: 'shield_lock',
            desc: props.message || 'Anda tidak memiliki wewenang atau hak akses (permission) yang memadai untuk membuka halaman atau data ini.',
            tips: [
                'Pastikan Anda login dengan akun yang memiliki hak akses.',
                'Hubungi administrator Tenancy Anda untuk mengajukan izin.',
                'Jika baru diberikan wewenang baru, coba keluar lalu login kembali.',
            ],
        },
        404: {
            title: 'Halaman Tidak Ditemukan',
            category: 'Navigasi & URL',
            icon: 'explore_off',
            desc: props.message || 'Maaf, halaman atau data keuangan yang Anda cari tidak ditemukan. Tautan mungkin salah atau telah dipindahkan.',
            tips: [
                'Periksa kembali penulisan URL di bilah alamat.',
                'Gunakan menu navigasi untuk mencari dokumen terkait.',
                'Tautan lama mungkin sudah diarsipkan oleh sistem.',
            ],
        },
        419: {
            title: 'Sesi Formulir Kedaluwarsa',
            category: 'Keamanan Sesi',
            icon: 'timer_off',
            desc: props.message || 'Sesi keamanan formulir Anda telah kedaluwarsa demi melindungi kerahasiaan data keuangan BUMDesma.',
            tips: [
                'Muat ulang halaman untuk memperbarui token keamanan.',
                'Simpan draf secara berkala saat bekerja.',
                'Masuk kembali jika sesi login telah berakhir.',
            ],
        },
        429: {
            title: 'Terlalu Banyak Permintaan',
            category: 'Batas Frekuensi',
            icon: 'speed',
            desc: props.message || 'Sistem mendeteksi frekuensi permintaan yang terlalu tinggi. Harap tunggu beberapa saat demi kestabilan server.',
            tips: [
                'Tunggu 30–60 detik sebelum mencoba kembali.',
                'Hindari menekan tombol Simpan berulang kali dengan cepat.',
                'Pastikan tidak ada sinkronisasi otomatis ganda di browser.',
            ],
        },
        500: {
            title: 'Terjadi Kesalahan Server',
            category: 'Kendala Teknis Server',
            icon: 'dns',
            desc: props.message || 'Terjadi gangguan teknis internal pada server. Tenang, seluruh data transaksi Anda tetap aman di basis data.',
            tips: [
                'Coba muat ulang halaman dalam beberapa saat.',
                'Hubungi tim IT jika kendala berlanjut.',
                'Kembali ke dashboard untuk melanjutkan pekerjaan lain.',
            ],
        },
        503: {
            title: 'Sistem Dalam Pemeliharaan',
            category: 'Pemeliharaan Sistem',
            icon: 'engineering',
            desc: props.message || 'Kami sedang melakukan peningkatan performa sistem rutin. Layanan siupk Next akan segera aktif kembali.',
            tips: [
                'Pemeliharaan berkala biasanya berlangsung beberapa menit.',
                'Seluruh data keuangan tetap aman.',
                'Muat ulang halaman secara berkala.',
            ],
        },
    };

    return map[code] || map[500];
});

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = '/';
    }
}

function reloadPage() {
    window.location.reload();
}
</script>

<template>
    <Head :title="`${status} - ${errorDetails.title}`" />

    <div class="min-h-screen flex flex-col justify-between bg-surface text-on-surface relative overflow-hidden font-sans">
        <!-- Ambient Glow -->
        <div class="fixed -top-24 -left-24 size-96 rounded-full bg-primary/5 pointer-events-none blur-3xl" />
        <div class="fixed -bottom-24 -right-24 size-96 rounded-full bg-secondary/5 pointer-events-none blur-3xl" />

        <!-- Header -->
        <header class="relative z-10 max-w-7xl w-full mx-auto px-6 py-5 flex items-center justify-between">
            <Link href="/" class="flex items-center gap-3">
                <div class="size-11 rounded-xl bg-gradient-to-br from-primary to-primary-container text-white grid place-items-center shadow-md">
                    <AppIcon name="account_balance" class="text-2xl" />
                </div>
                <div>
                    <p class="text-lg font-extrabold leading-tight text-primary">siupk <span class="text-secondary">Next</span></p>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-secondary">BUMDesma LKD Financial System</p>
                </div>
            </Link>

            <div class="inline-flex items-center gap-2 bg-black/5 dark:bg-white/5 border border-outline/20 px-3.5 py-1.5 rounded-full text-xs font-semibold text-on-surface-variant">
                <span class="size-2 rounded-full bg-amber-500 shadow-sm animate-pulse" />
                <span>HTTP {{ status }}</span>
            </div>
        </header>

        <!-- Main Card -->
        <main class="relative z-10 max-w-5xl w-full mx-auto p-6 flex-1 flex items-center justify-center">
            <div class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-3xl shadow-xl overflow-hidden grid grid-cols-1 md:grid-cols-12">
                <!-- Left Visual Panel -->
                <div class="md:col-span-5 bg-gradient-to-br from-primary/5 to-secondary/5 border-b md:border-b-0 md:border-r border-outline-variant/40 p-8 flex flex-col items-center justify-center text-center relative overflow-hidden">
                    <span class="absolute text-[120px] font-black text-primary/5 select-none top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
                        {{ status }}
                    </span>

                    <div class="relative z-10 size-28 rounded-3xl bg-surface-container-lowest border border-outline-variant/60 shadow-lg grid place-items-center text-primary mb-6 animate-bounce">
                        <AppIcon :name="errorDetails.icon" class="text-5xl text-primary" />
                    </div>

                    <div class="relative z-10 inline-flex items-center gap-2 bg-white dark:bg-slate-800 border border-outline-variant/60 px-4 py-1.5 rounded-full text-sm font-bold text-primary shadow-sm">
                        <AppIcon name="error_outline" class="text-base text-secondary" />
                        <span>Status {{ status }}</span>
                    </div>
                </div>

                <!-- Right Content Panel -->
                <div class="md:col-span-7 p-8 md:p-10 flex flex-col justify-center">
                    <div class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-secondary mb-2">
                        <AppIcon :name="errorDetails.icon" class="text-sm" />
                        <span>{{ errorDetails.category }}</span>
                    </div>

                    <h1 class="text-2xl md:text-3xl font-extrabold text-on-surface tracking-tight mb-3">
                        {{ errorDetails.title }}
                    </h1>

                    <p class="text-sm md:text-base text-on-surface-variant leading-relaxed mb-6">
                        {{ errorDetails.desc }}
                    </p>

                    <!-- Tips Checklist -->
                    <div class="bg-surface-container-low border border-outline-variant/40 rounded-2xl p-4 mb-6">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface mb-2.5 flex items-center gap-1.5">
                            <AppIcon name="lightbulb" class="text-sm text-secondary" />
                            Langkah yang disarankan
                        </p>
                        <ul class="space-y-2">
                            <li v-for="(tip, idx) in errorDetails.tips" :key="idx" class="flex items-start gap-2 text-xs md:text-sm text-on-surface-variant">
                                <AppIcon name="check_circle" class="text-sm text-secondary shrink-0 mt-0.5" />
                                <span>{{ tip }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-wrap items-center gap-3">
                        <Link href="/dashboard">
                            <AppButton variant="primary" icon="dashboard">
                                Ke Dashboard
                            </AppButton>
                        </Link>
                        <AppButton variant="secondary" icon="arrow_back" @click="goBack">
                            Halaman Sebelumnya
                        </AppButton>
                        <AppButton variant="ghost" icon="refresh" @click="reloadPage">
                            Refresh
                        </AppButton>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="relative z-10 text-center py-5 text-xs text-on-surface-variant border-t border-outline-variant/30">
            <p>&copy; {{ new Date().getFullYear() }} <strong>siupk Next</strong> &bull; Tata Kelola Dana Bergulir BUMDesma LKD</p>
        </footer>
    </div>
</template>
