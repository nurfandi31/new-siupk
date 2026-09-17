<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import AppCheckbox from '../../Components/AppCheckbox.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppIconButton from '../../Components/AppIconButton.vue';
import AppInput from '../../Components/AppInput.vue';

const showPassword = ref(false);
const form = useForm({ identifier: '', password: '', remember: false });
const page = usePage();
const formContainerRef = ref(null);
const localFlash = ref({ ...(page.props.flash ?? {}) });
const FLASH_AUTO_DISMISS_MS = 4500;
let flashTimer = null;

function clearFlashTimer() {
    if (flashTimer) {
        clearTimeout(flashTimer);
        flashTimer = null;
    }
}

watch(
    () => page.props.flash,
    (flash) => {
        localFlash.value = { ...(flash ?? {}) };
        clearFlashTimer();
        if (flash && Object.values(flash).some((v) => v != null && v !== '')) {
            flashTimer = setTimeout(() => {
                localFlash.value = {};
            }, FLASH_AUTO_DISMISS_MS);
        }
    },
    { deep: true, immediate: true },
);

onBeforeUnmount(clearFlashTimer);

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
        <aside class="login-brand relative w-full shrink-0 overflow-hidden md:fixed md:inset-y-0 md:left-0 md:flex md:w-[55%] md:flex-col md:justify-between md:min-w-0 lg:w-[52%] xl:w-[58%]" aria-label="Informasi siupk">
            <div class="login-brand-bg" aria-hidden="true" />
            <div class="login-brand-overlay" aria-hidden="true" />

            <!-- Top: Logo brand + tombol Beranda -->
            <header class="login-brand-header relative z-10 flex items-center justify-between gap-3 text-on-primary">
                <Link href="/" class="flex min-w-0 items-center gap-3 transition hover:opacity-90">
                    <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-surface-container-lowest text-primary shadow-sm md:size-11">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" aria-hidden="true">
                            <path d="M12 3 2 12h3v8h5v-6h4v6h5v-8h3L12 3z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-base font-bold tracking-tight md:text-lg">
                            siupk <span class="text-secondary-container">Next</span>
                        </p>
                        <p class="hidden truncate text-[11px] font-medium uppercase tracking-[0.18em] text-primary-fixed-dim md:block">
                            BUMDesma LKD Platform
                        </p>
                    </div>
                </Link>

                <Link href="/" class="login-back group inline-flex shrink-0 items-center gap-1.5 rounded-full border border-on-primary/25 bg-on-primary/5 px-3 py-1.5 text-xs font-medium text-on-primary backdrop-blur-sm transition hover:bg-on-primary/15 md:px-4 md:text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4 shrink-0 transition-transform group-hover:-translate-x-0.5" aria-hidden="true">
                        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                    </svg>
                    <span>beranda</span>
                </Link>
            </header>

            <!-- Bottom: Trust badges (pojok bawah, 2 item justify-between) -->
            <footer class="login-brand-footer relative z-10 hidden text-on-primary md:block">
                <ul class="login-trust flex items-center justify-between gap-4 border-t border-on-primary/15 pt-4 text-sm">
                    <li v-for="item in trustItems" :key="item.label" class="flex items-center gap-2.5 text-primary-fixed-dim">
                        <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-on-primary/10 text-secondary-container">
                            <AppIcon :name="item.icon" class="text-base leading-none" />
                        </span>
                        {{ item.label }}
                    </li>
                </ul>
            </footer>
        </aside>

        <!-- ============================================================
             RIGHT FORM PANEL
        ============================================================ -->
        <section class="login-form-panel flex min-h-0 w-full flex-1 flex-col bg-surface-container-lowest md:ml-[55%] md:w-auto lg:ml-[52%] xl:ml-[58%]">
            <!-- Top bar (mobile only — desktop brand panel handles it) -->
            <div class="flex shrink-0 items-center justify-between border-b border-outline-variant/40 px-4 py-3 md:hidden">
                <Link href="/" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4 shrink-0" aria-hidden="true">
                        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                    </svg>
                    beranda
                </Link>
                <span class="text-[11px] font-medium uppercase tracking-[0.18em] text-outline">
                    Autentikasi
                </span>
            </div>

            <div class="login-form-scroll flex min-h-0 flex-1 items-center justify-center px-4 py-6 sm:px-6 md:px-10 md:py-10 lg:px-14 xl:px-20">
                <div ref="formContainerRef" class="login-form-card w-full max-w-sm space-y-5 sm:space-y-6">
                    <!-- Header -->
                    <header class="login-form-header space-y-2 text-center sm:text-left">
                        <span class="login-form-pill inline-flex items-center gap-1.5 rounded-full bg-primary-container/40 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-primary shadow-[inset_0_0_0_1px_color-mix(in_srgb,var(--color-primary)_15%,transparent)] backdrop-blur-sm">
                            <span class="login-pill-dot relative flex size-1.5">
                                <span class="login-pill-dot-ping absolute inset-0 rounded-full bg-primary opacity-75" />
                                <span class="relative inline-flex size-1.5 rounded-full bg-primary" />
                            </span>
                            Portal Autentikasi Pengurus &amp; Admin
                        </span>
                        <h1 class="login-form-title text-2xl font-extrabold tracking-tight text-on-surface sm:text-3xl">
                            Masuk ke Akun Anda
                        </h1>
                        <p class="login-form-subtitle text-sm text-on-surface-variant">
                            Masukkan kredensial pengguna terdaftar BUMDesma Anda.
                        </p>
                    </header>

                    <!-- Flash messages -->
                    <div class="login-form-flash space-y-2">
                        <div v-if="localFlash.error" role="alert" class="flex items-start gap-2.5 rounded-lg border border-error/30 bg-error-container/40 p-3 text-sm text-error">
                            <AppIcon name="error" class="text-xl shrink-0 mt-0.5 leading-none" />
                            <span class="leading-snug">{{ localFlash.error }}</span>
                        </div>
                        <div v-if="localFlash.warning" role="status" class="flex items-start gap-2.5 rounded-lg border border-tertiary/30 bg-tertiary-fixed/40 p-3 text-sm text-on-surface">
                            <AppIcon name="warning" class="text-xl shrink-0 mt-0.5 leading-none" />
                            <span class="leading-snug">{{ localFlash.warning }}</span>
                        </div>
                        <div v-if="localFlash.success" role="status" class="flex items-start gap-2.5 rounded-lg border border-secondary/30 bg-secondary-container/30 p-3 text-sm text-secondary">
                            <AppIcon name="check_circle" class="text-xl shrink-0 mt-0.5 leading-none" />
                            <span class="leading-snug">{{ localFlash.success }}</span>
                        </div>
                        <div v-if="localFlash.info" role="status" class="flex items-start gap-2.5 rounded-lg border border-primary/30 bg-primary-container/30 p-3 text-sm text-primary">
                            <AppIcon name="info" class="text-xl shrink-0 mt-0.5 leading-none" />
                            <span class="leading-snug">{{ localFlash.info }}</span>
                        </div>
                        <div v-if="form.errors.identifier || form.errors.password || form.errors.error" role="alert" class="flex items-start gap-2.5 rounded-lg border border-error/30 bg-error-container/40 p-3 text-sm text-error">
                            <AppIcon name="lock_reset" class="text-xl shrink-0 mt-0.5 leading-none" />
                            <span class="leading-snug">{{ form.errors.identifier || form.errors.password || form.errors.error || 'Kredensial tidak valid.' }}</span>
                        </div>
                    </div>

                    <!-- Form -->
                    <form class="space-y-3.5 sm:space-y-4" @submit.prevent="submit">
                        <div class="login-form-field">
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
                        </div>

                        <div class="login-form-field">
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
                        </div>

                        <div class="login-form-options flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-sm">
                            <AppCheckbox v-model="form.remember" variant="inline" label="Ingat sesi saya" />
                            <Link :href="route('password.request')" class="font-semibold text-primary hover:underline">
                                Lupa password?
                            </Link>
                        </div>

                        <div class="login-form-submit" style="margin-top: 1.5rem;">
                            <AppButton
                                type="submit"
                                variant="success"
                                size="large"
                                class="login-submit-btn w-full font-semibold shadow-lg shadow-secondary/30 hover:shadow-xl hover:shadow-secondary/40 hover:-translate-y-0.5"
                                :loading="form.processing"
                                icon="login"
                            >
                                <span>{{ form.processing ? 'Memverifikasi...' : 'masuk ke dashboard' }}</span>
                            </AppButton>
                        </div>
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
    background-image: url('/assets/img/login.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    image-rendering: -webkit-optimize-contrast;
    image-rendering: high-quality;
    /* Warm wood-tone match reference image */
    filter: brightness(1.02) contrast(1.18) saturate(1.35) sepia(0.14) hue-rotate(-10deg);
}

@media (min-width: 768px) and (-webkit-min-device-pixel-ratio: 2), (min-width: 768px) and (min-resolution: 192dpi) {
    .login-brand-bg {
        background-image: url('/assets/img/login.png');
    }
}

.login-brand-overlay {
    display: none;
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

/* ================================================================
   Flash auto-dismiss — fade + collapse when removed from DOM
   ================================================================ */
.login-form-flash {
    transition: opacity 280ms ease, transform 280ms ease;
}

/* ================================================================
   Form panel — simple entrance animation (staggered fade + slide)
   ================================================================ */
.login-form-header,
.login-form-flash,
.login-form-field,
.login-form-options,
.login-form-submit {
    opacity: 0;
    transform: translateY(8px);
    animation: login-form-rise 0.45s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.login-form-header { animation-delay: 0.05s; }
.login-form-flash { animation-delay: 0.15s; }
.login-form-field:nth-of-type(1) { animation-delay: 0.20s; }
.login-form-field:nth-of-type(2) { animation-delay: 0.30s; }
.login-form-options { animation-delay: 0.40s; }
.login-form-submit { animation-delay: 0.48s; }

@keyframes login-form-rise {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Soft focus pulse on input fields */
.login-form-field:focus-within {
    transition: transform 0.2s ease-out;
    transform: translateY(-1px);
}

/* Blinking dot inside the auth pill */
.login-pill-dot-ping {
    animation: login-pill-blink 1.6s cubic-bezier(0, 0, 0.2, 1) infinite;
}
@keyframes login-pill-blink {
    0% {
        transform: scale(1);
        opacity: 0.75;
    }
    70% {
        transform: scale(2.2);
        opacity: 0;
    }
    100% {
        transform: scale(2.2);
        opacity: 0;
    }
}

/* Submit button — smooth shadow + lift hover */
.login-submit-btn {
    transition-property: box-shadow, transform, filter;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
    transition-duration: 220ms;
}
.login-submit-btn:hover:not(:disabled) {
    transform: translateY(-2px);
}

@media (prefers-reduced-motion: reduce) {
    .login-form-header,
    .login-form-flash,
    .login-form-field,
    .login-form-options,
    .login-form-submit {
        opacity: 1;
        transform: none;
        animation: none;
    }
    .login-form-field:focus-within {
        transform: none;
    }
    .login-pill-dot-ping {
        animation: none;
    }
    .login-submit-btn,
    .login-submit-btn:hover:not(:disabled) {
        transform: none;
    }
}
</style>
