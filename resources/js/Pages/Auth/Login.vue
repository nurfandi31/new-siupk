<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import AppCheckbox from '../../Components/AppCheckbox.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppIconButton from '../../Components/AppIconButton.vue';
import AppInput from '../../Components/AppInput.vue';

const showPassword = ref(false);
const form = useForm({ identifier: '', password: '', remember: false });
const page = usePage();
const formContainerRef = ref(null);

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
        onError: () => {
            if (!formContainerRef.value) return;
            formContainerRef.value.animate(
                [
                    { transform: 'translateX(-6px)' },
                    { transform: 'translateX(6px)' },
                    { transform: 'translateX(-4px)' },
                    { transform: 'translateX(4px)' },
                    { transform: 'translateX(0)' },
                ],
                { duration: 240, easing: 'ease-in-out' }
            );
        },
    });
}

function togglePasswordVisibility() {
    showPassword.value = !showPassword.value;
}

const trustItems = [
    { icon: 'verified_user', label: 'Regulasi PP No. 11/2021' },
    { icon: 'lock', label: 'Enkripsi & Database Sharding' },
];
</script>

<template>
    <Head title="Masuk Ke Portal - siupk Next" />

    <main class="login-shell flex flex-col bg-surface font-sans text-on-surface lg:flex-row">
        <!-- ============================================================
             LEFT BRANDING PANEL
             - Mobile: compact horizontal strip (~72px)
             - Desktop (md+): full-height sidebar with bg image & headline
        ============================================================ -->
        <aside class="login-brand relative w-full shrink-0 overflow-hidden md:fixed md:inset-y-0 md:left-0 md:flex md:w-[55%] md:flex-col md:justify-between lg:w-[52%] xl:w-[58%]" aria-label="Informasi siupk">
            <div class="login-brand-bg" aria-hidden="true" />
            <div class="login-brand-overlay" aria-hidden="true" />

            <!-- Top: Logo brand + tombol Beranda -->
            <header class="login-brand-header relative z-10 flex items-center justify-between gap-3 text-on-primary">
                <Link href="/" class="flex min-w-0 items-center gap-2.5 transition hover:opacity-90">
                    <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-surface-container-lowest text-primary shadow-sm md:size-10">
                        <AppIcon name="account_balance" class="text-lg md:text-xl" />
                    </div>
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-sm font-bold tracking-tight md:text-base">
                            siupk <span class="text-secondary-container">Next</span>
                        </p>
                        <p class="hidden truncate text-[10px] font-medium uppercase tracking-[0.18em] text-primary-fixed-dim md:block">
                            BUMDesma LKD Platform
                        </p>
                    </div>
                </Link>

                <Link href="/" class="login-back group inline-flex shrink-0 items-center gap-1 rounded-full border border-on-primary/25 bg-on-primary/5 px-2.5 py-1 text-[11px] font-medium text-on-primary backdrop-blur-sm transition hover:bg-on-primary/15 md:px-3 md:py-1.5">
                    <AppIcon name="arrow_back" class="text-base transition-transform group-hover:-translate-x-0.5" />
                    <span>Beranda</span>
                </Link>
            </header>

            <!-- Middle: Headline pitch (desktop only) -->
            <div class="login-headline-wrap relative z-10 mx-auto hidden w-full max-w-xl text-on-primary md:block">
                <div class="login-pill mb-4 inline-flex items-center gap-1.5 rounded-full border border-on-primary/20 bg-on-primary/10 px-3 py-1 text-[11px] font-medium backdrop-blur-sm">
                    <span class="relative flex size-1.5">
                        <span class="absolute inset-0 animate-ping rounded-full bg-secondary-container opacity-75" />
                        <span class="relative inline-flex size-1.5 rounded-full bg-secondary-container" />
                    </span>
                    Sistem Informasi Unit Pengelola Kegiatan
                </div>

                <h2 class="login-headline text-xl font-bold leading-tight tracking-tight lg:text-2xl xl:text-[1.75rem]">
                    Solusi Tata Kelola Keuangan
                    <span class="block text-secondary-container">BUMDesma &amp; LKD</span>
                    Terintegrasi.
                </h2>

                <p class="login-desc mt-3 max-w-md text-sm leading-relaxed text-primary-fixed-dim">
                    Pinjaman bergulir, pembukuan standar SAK EP, laporan konsolidasi, dan integrasi WhatsApp Gateway dalam satu platform.
                </p>
            </div>

            <!-- Bottom: Trust badges (pojok bawah, 2 item justify-between) -->
            <footer class="login-brand-footer relative z-10 hidden text-on-primary md:block">
                <ul class="login-trust flex items-center justify-between gap-4 border-t border-on-primary/15 pt-4 text-xs">
                    <li v-for="item in trustItems" :key="item.label" class="flex items-center gap-2 text-primary-fixed-dim">
                        <span class="grid size-6 place-items-center rounded-md bg-on-primary/10 text-secondary-container">
                            <AppIcon :name="item.icon" class="text-sm" />
                        </span>
                        {{ item.label }}
                    </li>
                </ul>
            </footer>
        </aside>

        <!-- ============================================================
             RIGHT FORM PANEL
        ============================================================ -->
        <section class="login-form-panel flex min-h-0 flex-1 flex-col bg-surface-container-lowest md:ml-[55%] lg:ml-[52%] xl:ml-[58%]">
            <!-- Top bar (mobile only — desktop brand panel handles it) -->
            <div class="flex shrink-0 items-center justify-between border-b border-outline-variant/40 px-4 py-2 md:hidden">
                <Link href="/" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary">
                    <AppIcon name="arrow_back" class="text-base" />
                    Beranda
                </Link>
                <span class="text-[11px] font-medium uppercase tracking-[0.18em] text-outline">
                    Autentikasi
                </span>
            </div>

            <div class="login-form-scroll flex min-h-0 flex-1 items-center justify-center px-4 py-6 sm:px-6 md:px-10 md:py-10 lg:px-14 xl:px-20">
                <div ref="formContainerRef" class="w-full max-w-sm space-y-5 sm:space-y-6">
                    <!-- Header -->
                    <header class="space-y-2 text-center sm:text-left">
                        <span class="inline-block rounded-full bg-primary-container/40 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-primary shadow-[inset_0_0_0_1px_color-mix(in_srgb,var(--color-primary)_15%,transparent)] backdrop-blur-sm">
                            Portal Autentikasi Pengurus &amp; Admin
                        </span>
                        <h1 class="text-2xl font-extrabold tracking-tight text-on-surface sm:text-3xl">
                            Masuk ke Akun Anda
                        </h1>
                        <p class="text-sm text-on-surface-variant">
                            Masukkan kredensial pengguna terdaftar BUMDesma Anda.
                        </p>
                    </header>

                    <!-- Flash messages -->
                    <div class="space-y-2">
                        <div v-if="page.props.flash?.error" role="alert" class="flex items-start gap-2 rounded-lg border border-error/30 bg-error-container/40 p-2.5 text-sm text-error">
                            <AppIcon name="error" class="text-lg shrink-0 mt-0.5" />
                            <span>{{ page.props.flash.error }}</span>
                        </div>
                        <div v-if="page.props.flash?.warning" role="status" class="flex items-start gap-2 rounded-lg border border-tertiary/30 bg-tertiary-fixed/40 p-2.5 text-sm text-on-surface">
                            <AppIcon name="warning" class="text-lg shrink-0 mt-0.5" />
                            <span>{{ page.props.flash.warning }}</span>
                        </div>
                        <div v-if="page.props.flash?.success" role="status" class="flex items-start gap-2 rounded-lg border border-secondary/30 bg-secondary-container/30 p-2.5 text-sm text-secondary">
                            <AppIcon name="check_circle" class="text-lg shrink-0 mt-0.5" />
                            <span>{{ page.props.flash.success }}</span>
                        </div>
                        <div v-if="page.props.flash?.info" role="status" class="flex items-start gap-2 rounded-lg border border-primary/30 bg-primary-container/30 p-2.5 text-sm text-primary">
                            <AppIcon name="info" class="text-lg shrink-0 mt-0.5" />
                            <span>{{ page.props.flash.info }}</span>
                        </div>
                        <div v-if="form.errors.identifier || form.errors.password || form.errors.error" role="alert" class="flex items-start gap-2 rounded-lg border border-error/30 bg-error-container/40 p-2.5 text-sm text-error">
                            <AppIcon name="lock_reset" class="text-lg shrink-0 mt-0.5" />
                            <span>{{ form.errors.identifier || form.errors.password || form.errors.error || 'Kredensial tidak valid.' }}</span>
                        </div>
                    </div>

                    <!-- Form -->
                    <form class="space-y-3.5 sm:space-y-4" @submit.prevent="submit">
                        <AppInput
                            v-model="form.identifier"
                            label="Username / Email"
                            icon="person"
                            autocomplete="username"
                            placeholder="Username atau email"
                            required
                            autofocus
                            :error="form.errors.identifier"
                        />

                        <AppInput
                            v-model="form.password"
                            label="Password"
                            icon="lock"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            placeholder="••••••••"
                            required
                            :error="form.errors.password"
                        >
                            <template #trailing>
                                <AppIconButton
                                    :name="showPassword ? 'visibility_off' : 'visibility'"
                                    size="sm"
                                    tone="neutral"
                                    rounded="lg"
                                    :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    @click="togglePasswordVisibility"
                                />
                            </template>
                        </AppInput>

                        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-sm">
                            <AppCheckbox v-model="form.remember" variant="inline" label="Ingat sesi saya" />
                            <Link :href="route('password.request')" class="font-semibold text-primary hover:underline">
                                Lupa password?
                            </Link>
                        </div>

                        <AppButton
                            type="submit"
                            variant="success"
                            size="large"
                            class="w-full font-semibold"
                            :loading="form.processing"
                            icon="login"
                        >
                            <span>{{ form.processing ? 'Memverifikasi...' : 'Masuk' }}</span>
                        </AppButton>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <footer class="shrink-0 border-t border-outline-variant/40 px-4 py-3 text-center text-[11px] text-outline sm:px-6">
                &copy; {{ new Date().getFullYear() }} siupk Next &mdash; Sistem Informasi Unit Pengelola Kegiatan
            </footer>
        </section>
    </main>
</template>

<style scoped>
/* ================================================================
   Shell — mobile: column with natural height, desktop: row locked
   ================================================================ */
.login-shell {
    min-height: 100vh;
    min-height: 100svh;
}
@media (min-width: 768px) {
    .login-shell {
        height: 100vh;
        height: 100svh;
        overflow: hidden;
    }
}

/* ================================================================
   Brand panel
   ================================================================ */
.login-brand {
    min-height: 72px;
}
@media (min-width: 768px) {
    .login-brand {
        min-height: 100vh;
        min-height: 100svh;
    }
}

/* Header padding — compact, consistent */
.login-brand-header {
    padding: 0.75rem 1rem;
}
@media (min-width: 768px) {
    .login-brand-header {
        padding: 1.25rem 1.5rem;
    }
}
@media (min-width: 1024px) {
    .login-brand-header {
        padding: 1.5rem 2rem;
    }
}

/* Headline middle padding */
.login-headline-wrap {
    padding: 0 1.5rem 1.25rem;
}
@media (min-width: 1024px) {
    .login-headline-wrap {
        padding: 0 2rem;
        margin-top: auto;
        margin-bottom: auto;
    }
}

/* Footer (trust badges) padding */
.login-brand-footer {
    padding: 0 1.5rem 1.25rem;
}
@media (min-width: 1024px) {
    .login-brand-footer {
        padding: 0 2rem 2rem;
    }
}

/* ================================================================
   Form panel
   ================================================================ */
.login-form-scroll {
    overflow-y: auto;
}
@media (min-width: 768px) {
    .login-form-scroll {
        overflow-y: visible;
    }
}

/* ================================================================
   Brand panel visuals
   ================================================================ */
.login-brand-bg {
    position: absolute;
    inset: 0;
    background-color: var(--color-primary-container, #0a7d57);
    background-image: url('/assets/img/login.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

.login-brand-overlay {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse at top right, color-mix(in srgb, var(--color-secondary-container) 20%, transparent) 0%, transparent 55%),
        radial-gradient(ellipse at bottom left, color-mix(in srgb, var(--color-primary-deep) 55%, transparent) 0%, transparent 60%),
        linear-gradient(
            165deg,
            color-mix(in srgb, var(--color-primary) 65%, transparent) 0%,
            color-mix(in srgb, var(--color-primary-container) 75%, transparent) 50%,
            color-mix(in srgb, var(--color-primary-deep) 85%, transparent) 100%
        );
}

/* ================================================================
   Entrance animation (desktop only)
   ================================================================ */
@media (min-width: 768px) {
    .login-pill,
    .login-headline,
    .login-desc,
    .login-trust {
        opacity: 0;
        transform: translateY(6px);
        animation: login-fade-in 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    .login-pill { animation-delay: 0.1s; }
    .login-headline { animation-delay: 0.2s; }
    .login-desc { animation-delay: 0.3s; }
    .login-trust { animation-delay: 0.4s; }

    @keyframes login-fade-in {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
}

@media (prefers-reduced-motion: reduce) {
    .login-pill,
    .login-headline,
    .login-desc,
    .login-trust {
        opacity: 1;
        transform: none;
        animation: none;
    }
}
</style>
