<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import gsap from 'gsap';
import AppButton from '../../Components/AppButton.vue';
import AppCheckbox from '../../Components/AppCheckbox.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppIconButton from '../../Components/AppIconButton.vue';
import AppInput from '../../Components/AppInput.vue';

const showPassword = ref(false);
const form = useForm({ identifier: '', password: '', remember: false });
const formContainerRef = ref(null);
const leftPanelRef = ref(null);
const eyeButtonRef = ref(null);

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
        onError: () => {
            if (formContainerRef.value) {
                gsap.fromTo(
                    formContainerRef.value,
                    { x: -12 },
                    {
                        x: 12,
                        duration: 0.07,
                        repeat: 5,
                        yoyo: true,
                        ease: 'sine.inOut',
                        onComplete: () => {
                            gsap.set(formContainerRef.value, { x: 0 });
                        },
                    }
                );
            }
        },
    });
}

function togglePasswordVisibility() {
    showPassword.value = !showPassword.value;
    if (eyeButtonRef.value) {
        gsap.fromTo(
            eyeButtonRef.value,
            { scale: 0.8, rotate: -15 },
            { scale: 1, rotate: 0, duration: 0.3, ease: 'back.out(2)' }
        );
    }
}

// Interactive Parallax on Left Branding Panel
function onPanelMouseMove(e) {
    if (!leftPanelRef.value) return;
    const rect = leftPanelRef.value.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const deltaX = (x - centerX) / centerX;
    const deltaY = (y - centerY) / centerY;

    gsap.to('.chart-container', {
        x: deltaX * 12,
        y: deltaY * 12,
        duration: 0.5,
        ease: 'power2.out',
    });
    gsap.to('.ambient-circle-1', {
        x: deltaX * 25,
        y: deltaY * 25,
        duration: 0.6,
        ease: 'power2.out',
    });
    gsap.to('.ambient-circle-2', {
        x: -deltaX * 20,
        y: -deltaY * 20,
        duration: 0.6,
        ease: 'power2.out',
    });
}

function onPanelMouseLeave() {
    gsap.to('.chart-container, .ambient-circle-1, .ambient-circle-2', {
        x: 0,
        y: 0,
        duration: 0.8,
        ease: 'power3.out',
    });
}

onMounted(() => {
    nextTick(() => {
        const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

        // Ambient background circles floating loop
        gsap.to('.ambient-circle-1', {
            y: -20,
            x: 10,
            duration: 6,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
        });
        gsap.to('.ambient-circle-2', {
            y: 25,
            x: -15,
            duration: 7,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
            delay: 1,
        });

        // Left branding showcase entrance
        tl.fromTo(
            '.brand-header',
            { opacity: 0, y: -20 },
            { opacity: 1, y: 0, duration: 0.6 }
        )
        .fromTo(
            '.chart-bar',
            { scaleY: 0, opacity: 0 },
            {
                scaleY: 1,
                opacity: 1,
                transformOrigin: 'bottom center',
                duration: 0.85,
                stagger: 0.12,
                ease: 'back.out(1.6)',
            },
            '-=0.3'
        )
        .fromTo(
            '.brand-text',
            { opacity: 0, y: 25 },
            { opacity: 1, y: 0, duration: 0.7, stagger: 0.12 },
            '-=0.5'
        )
        .fromTo(
            '.brand-footer',
            { opacity: 0 },
            { opacity: 1, duration: 0.5 },
            '-=0.3'
        );

        // Continuous Living Chart Breathing Pulse
        gsap.to('.chart-bar-1', {
            scaleY: 1.06,
            transformOrigin: 'bottom center',
            duration: 3.2,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
        });
        gsap.to('.chart-bar-2', {
            scaleY: 1.08,
            transformOrigin: 'bottom center',
            duration: 2.8,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
            delay: 0.4,
        });
        gsap.to('.chart-bar-3', {
            scaleY: 1.05,
            transformOrigin: 'bottom center',
            duration: 3.6,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
            delay: 0.8,
        });

        // Right form entrance
        gsap.fromTo(
            '.form-anim-item',
            { opacity: 0, y: 24 },
            {
                opacity: 1,
                y: 0,
                duration: 0.65,
                stagger: 0.08,
                ease: 'power2.out',
                delay: 0.1,
            }
        );
    });
});
</script>

<template>
    <Head title="Masuk Ke Portal - siupk Next" />

    <main class="flex min-h-screen overflow-hidden bg-surface font-sans text-on-surface">
        <!-- Left Branding Showcase Panel -->
        <section
            ref="leftPanelRef"
            class="login-panel relative hidden w-[52%] flex-col justify-between overflow-hidden p-12 lg:flex select-none"
            aria-label="Informasi siupk"
            @mousemove="onPanelMouseMove"
            @mouseleave="onPanelMouseLeave"
        >
            <div class="ambient-circle-1 absolute -left-28 top-1/3 size-96 rounded-full border border-on-primary/15 bg-on-primary/[0.03] blur-md pointer-events-none" />
            <div class="ambient-circle-2 absolute -left-10 top-1/3 size-96 rounded-full border border-on-primary/10 bg-secondary-container/[0.06] blur-md pointer-events-none" />

            <!-- Brand Header -->
            <div class="brand-header relative z-10 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-3 transition hover:opacity-90 group">
                    <div class="grid size-12 place-items-center rounded-2xl bg-surface-container-lowest text-primary-container shadow-md transition-transform group-hover:scale-105 duration-300">
                        <AppIcon name="account_balance" class="text-3xl" />
                    </div>
                    <div>
                        <p class="text-xl font-extrabold tracking-tight text-on-primary">siupk <span class="text-secondary-container">Next</span></p>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-fixed-dim">BUMDesma LKD Financial System</p>
                    </div>
                </Link>

                <Link href="/">
                    <AppButton variant="ghost" size="compact" class="!text-on-primary hover:!bg-on-primary/10 font-bold" icon="arrow_back">
                        Kembali ke Beranda
                    </AppButton>
                </Link>
            </div>

            <!-- Central Content Pitch -->
            <div class="relative z-10 my-auto mx-auto max-w-xl text-center space-y-6">
                <!-- Living Financial Activity Chart Graphic -->
                <div class="chart-container mx-auto grid max-w-xs grid-cols-3 items-end gap-4 relative will-change-transform" aria-hidden="true">
                    <div class="chart-bar chart-bar-1 h-24 rounded-t-2xl bg-on-primary/20 backdrop-blur-sm shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 inset-x-0 h-1 bg-secondary-container/60" />
                    </div>
                    <div class="chart-bar chart-bar-2 h-40 rounded-t-2xl bg-secondary-container/40 backdrop-blur-sm shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 inset-x-0 h-1.5 bg-secondary-container" />
                    </div>
                    <div class="chart-bar chart-bar-3 h-32 rounded-t-2xl bg-on-primary/30 backdrop-blur-sm shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 inset-x-0 h-1 bg-secondary-container/60" />
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="brand-text inline-flex items-center gap-2 rounded-full bg-on-primary/10 backdrop-blur-sm px-3.5 py-1 text-xs font-bold text-on-primary border border-on-primary/15 shadow-xs">
                        <span class="size-2 rounded-full bg-secondary-container animate-ping" />
                        <span>Platform Keuangan SAK EP & DBM</span>
                    </div>

                    <h2 class="brand-text text-3xl font-black tracking-tight text-on-primary lg:text-4xl leading-snug">
                        Tata Kelola Keuangan BUMDesma yang Akuntabel
                    </h2>

                    <p class="brand-text text-sm leading-relaxed text-primary-fixed-dim">
                        Sistem manajemen pinjaman bergulir, otomatisasi pembukuan jurnal akuntansi, dan integrasi pengawasan Dinas PMD Kabupaten dalam satu pintu.
                    </p>
                </div>
            </div>

            <!-- Brand Footer Badges -->
            <footer class="brand-footer relative z-10 flex items-center justify-between border-t border-on-primary/10 pt-6 text-xs text-primary-fixed-dim">
                <span class="font-semibold flex items-center gap-1.5">
                    <AppIcon name="verified_user" class="text-secondary-container text-base" /> Sesuai Regulasi PP No. 11/2021
                </span>
                <span class="font-semibold flex items-center gap-1.5">
                    <AppIcon name="lock" class="text-secondary-container text-base" /> Enkripsi & Database Sharding
                </span>
            </footer>
        </section>

        <!-- Right Form Panel -->
        <section class="flex flex-1 flex-col justify-between overflow-y-auto p-6 sm:p-12 lg:p-16 bg-surface-container-lowest">
            <!-- Mobile Top Header -->
            <div class="flex items-center justify-between lg:hidden mb-8">
                <Link href="/" class="flex items-center gap-2">
                    <div class="grid size-10 place-items-center rounded-xl bg-primary-container text-on-primary shadow-sm">
                        <AppIcon name="account_balance" class="text-2xl" />
                    </div>
                    <span class="text-lg font-black text-primary">siupk Next</span>
                </Link>

                <Link href="/">
                    <AppButton variant="ghost" size="compact" icon="arrow_back">
                        Beranda
                    </AppButton>
                </Link>
            </div>

            <!-- Form Container -->
            <div ref="formContainerRef" class="my-auto mx-auto w-full max-w-md space-y-8 will-change-transform">
                <header class="form-anim-item space-y-2">
                    <span class="inline-flex items-center rounded-full bg-primary-fixed px-3 py-1 text-xs font-bold text-primary shadow-xs">
                        Portal Autentikasi Pengurus & Admin
                    </span>
                    <h1 class="text-3xl font-black tracking-tight text-primary">Masuk ke Akun Anda</h1>
                    <p class="text-sm text-on-surface-variant">Masukkan kredensial pengguna terdaftar BUMDesma Anda.</p>
                </header>

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="form-anim-item">
                        <AppInput
                            v-model="form.identifier"
                            label="Username atau Email"
                            icon="person"
                            autocomplete="username"
                            placeholder="Masukkan username atau email anda"
                            required
                            autofocus
                            :error="form.errors.identifier"
                        />
                    </div>

                    <div class="form-anim-item">
                        <AppInput
                            v-model="form.password"
                            label="Kata Sandi (Password)"
                            icon="lock"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            placeholder="••••••••"
                            required
                            :error="form.errors.password"
                        >
                            <template #trailing>
                                <div ref="eyeButtonRef">
                                    <AppIconButton
                                        :name="showPassword ? 'visibility_off' : 'visibility'"
                                        size="sm"
                                        tone="neutral"
                                        rounded="lg"
                                        :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                        @click="togglePasswordVisibility"
                                    />
                                </div>
                            </template>
                        </AppInput>
                    </div>

                    <div class="form-anim-item flex items-center justify-between text-sm">
                        <AppCheckbox v-model="form.remember" variant="inline" label="Ingat sesi saya" />
                    </div>

                    <div class="form-anim-item">
                        <AppButton
                            type="submit"
                            variant="success"
                            size="large"
                            class="w-full font-bold shadow-md hover:shadow-xl transition-all duration-300 hover:-translate-y-0.5"
                            :loading="form.processing"
                            icon="login"
                        >
                            {{ form.processing ? 'Memverifikasi Kredensial...' : 'Masuk ke Dashboard' }}
                        </AppButton>
                    </div>

                    <div class="form-anim-item text-center">
                        <Link :href="route('password.request')" class="text-sm font-bold text-primary hover:underline">
                            Lupa password?
                        </Link>
                    </div>
                </form>

                <!-- Help Info Card -->
                <div class="form-anim-item rounded-2xl border border-outline-variant/70 bg-surface-container-low p-4 text-xs text-on-surface-variant leading-relaxed flex items-start gap-3 shadow-xs">
                    <div class="grid size-7 place-items-center rounded-lg bg-primary-fixed text-primary shrink-0 mt-0.5">
                        <AppIcon name="info" class="text-base" />
                    </div>
                    <div>
                        <p class="font-bold text-primary">Butuh bantuan akses?</p>
                        <p class="mt-0.5">Lupa kata sandi atau akun terblokir? Silakan hubungi Administrator Utama BUMDesma atau Dinas PMD setempat.</p>
                    </div>
                </div>
            </div>

            <!-- Footer Copyright -->
            <footer class="mt-8 text-center text-xs font-medium text-outline">
                &copy; 2026 BUMDesma LKD. Seluruh hak cipta dilindungi.
            </footer>
        </section>
    </main>
</template>

<style scoped>
.login-panel {
    background: linear-gradient(145deg, var(--color-primary, #1e3a8a) 0%, var(--color-primary-container, #172554) 100%);
}
</style>
