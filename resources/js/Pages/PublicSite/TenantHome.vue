<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    organization: { type: Object, required: true },
    tenant: { type: Object, required: true },
    settings: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head :title="`${organization.name} — Sistem Informasi Dana Bergulir Masyarakat`">
        <meta head-key="description" name="description" :content="settings.hero_description ?? settings.about_short ?? `Situs resmi ${organization.name} — portal informasi dan pengelolaan dana bergulir masyarakat.`" />
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
        <!-- Top bar: organization identity -->
        <header class="sticky top-0 z-40 border-b border-outline-variant/60 bg-surface-container-lowest/95 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <!-- Logo: tenant's own, fallback to monogram -->
                    <div class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-primary-container shadow-md">
                        <img
                            v-if="organization.logo_url"
                            :src="organization.logo_url"
                            :alt="`Logo ${organization.name}`"
                            class="size-full object-contain"
                        >
                        <span v-else class="text-lg font-extrabold text-on-primary-container">{{ organization.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold leading-tight text-on-surface">{{ organization.name }}</p>
                        <p v-if="organization.regency_name || organization.district_name" class="truncate text-xs text-on-surface-variant">
                            {{ [organization.district_name, organization.regency_name].filter(Boolean).join(', ') }}
                        </p>
                    </div>
                </div>

                <nav class="flex items-center gap-2">
                    <Link href="/berita" class="inline-flex min-h-10 items-center gap-2 rounded-full bg-primary-container px-4 text-sm font-semibold text-on-primary-container">
                        <AppIcon name="article" />
                        Berita
                    </Link>
                    <Link href="/kontak" class="inline-flex min-h-10 items-center gap-2 rounded-full bg-primary-container px-4 text-sm font-semibold text-on-primary-container">
                        <AppIcon name="mail" />
                        Kontak
                    </Link>
                    <Link href="/login" class="inline-flex min-h-10 items-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-on-primary shadow-md transition hover:bg-primary-deep">
                        <AppIcon name="login" />
                        Masuk Sistem
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Hero -->
        <main class="flex-1">
            <section class="relative overflow-hidden bg-gradient-to-b from-primary-container via-primary-deep to-primary-deep text-on-primary">
                <div class="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full bg-on-primary-container/10 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-32 -left-16 size-80 rounded-full bg-secondary-container/10 blur-3xl" />

                <div class="relative mx-auto flex max-w-7xl flex-col items-center gap-6 px-4 py-20 text-center sm:px-6 md:py-28 lg:px-8">
                    <span class="inline-flex items-center gap-2 rounded-full bg-on-primary/10 px-4 py-1.5 text-xs font-bold tracking-widest uppercase text-on-primary-container">
                        <AppIcon name="verified" />
                        {{ settings.hero_tagline ?? 'Situs Resmi' }}
                    </span>

                    <h1 class="max-w-3xl text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl md:text-5xl">
                        {{ organization.legal_name }}
                    </h1>

                    <p class="max-w-2xl text-base leading-relaxed text-on-primary-container sm:text-lg">
                        {{ settings.hero_description ?? `Portal informasi resmi ${organization.name} — pengelolaan dana bergulir masyarakat yang transparan, akuntabel, dan berorientasi pada kesejahteraan warga.` }}
                    </p>

                    <img
                        v-if="settings.hero_image_url"
                        :src="settings.hero_image_url"
                        :alt="`Tampilan ${organization.name}`"
                        class="mt-2 max-h-80 w-auto max-w-2xl rounded-2xl object-cover shadow-lg"
                    >

                    <div class="mt-2 flex flex-wrap items-center justify-center gap-3">
                        <Link href="/login" class="inline-flex min-h-12 items-center gap-2 rounded-full bg-on-primary px-7 text-sm font-bold text-primary-deep shadow-lg transition hover:bg-primary-fixed">
                            <AppIcon name="apartment" />
                            Portal Pengelolaan Keuangan
                        </Link>
                        <a
                            v-if="organization.phone"
                            :href="`tel:${organization.phone}`"
                            class="inline-flex min-h-12 items-center gap-2 rounded-full border border-outline-variant/40 px-7 text-sm font-semibold text-on-primary transition hover:bg-on-primary/10"
                        >
                            <AppIcon name="call" />
                            Hubungi Kami
                        </a>
                    </div>
                </div>
            </section>

            <!-- Contact / information cards -->
            <section class="mx-auto -mt-10 max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-3">
                    <div v-if="settings.about_short" class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-6 shadow-md md:col-span-3">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-container text-on-primary-container">
                                <AppIcon name="info" />
                            </span>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Tentang Kami</h2>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-on-surface">{{ settings.about_short }}</p>
                    </div>
                    <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-6 shadow-md">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-container text-on-primary-container">
                                <AppIcon name="place" />
                            </span>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Alamat</h2>
                        </div>
                        <p v-if="organization.address" class="mt-3 text-sm leading-relaxed text-on-surface">{{ organization.address }}</p>
                        <p v-else class="mt-3 text-sm italic text-on-surface-variant">Alamat belum dipublikasikan.</p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-6 shadow-md">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-container text-on-primary-container">
                                <AppIcon name="call" />
                            </span>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Kontak</h2>
                        </div>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li v-if="organization.phone" class="flex items-center gap-2 text-on-surface">
                                <AppIcon name="call" />
                                {{ organization.phone }}
                            </li>
                            <li v-if="organization.email" class="flex items-center gap-2 text-on-surface">
                                <AppIcon name="mail" />
                                {{ organization.email }}
                            </li>
                            <li v-if="organization.website" class="flex items-center gap-2 text-on-surface">
                                <AppIcon name="language" />
                                {{ organization.website }}
                            </li>
                            <li v-if="!organization.phone && !organization.email && !organization.website" class="text-sm italic text-on-surface-variant">
                                Kanal kontak belum dipublikasikan.
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-6 shadow-md">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-container text-on-primary-container">
                                <AppIcon name="history" />
                            </span>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Berdiri</h2>
                        </div>
                        <p v-if="organization.operational_start_year" class="mt-3 text-sm text-on-surface">
                            Sejak {{ organization.operational_start_year }} melayani pengelolaan dana bergulir masyarakat.
                        </p>
                        <p v-else class="mt-3 text-sm italic text-on-surface-variant">Informasi tahun berdiri belum tersedia.</p>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-outline-variant/60 bg-surface-container-lowest py-8">
            <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <p class="text-sm font-semibold text-on-surface">
                    © {{ new Date().getFullYear() }} {{ organization.legal_name }}
                </p>
                <p v-if="settings.footer_note" class="mt-1.5 text-xs text-on-surface-variant">
                    {{ settings.footer_note }}
                </p>
                <p v-else class="mt-1.5 text-xs text-on-surface-variant">
                    Dikelola dengan
                    <a href="/" class="font-semibold text-primary hover:underline">siupk Next</a>
                    — Sistem Informasi Dana Bergulir Masyarakat
                </p>
                <div v-if="settings.social?.facebook || settings.social?.instagram || settings.social?.youtube" class="mt-3 flex justify-center gap-2">
                    <a v-if="settings.social?.facebook" :href="settings.social.facebook" target="_blank" rel="noopener" class="grid size-9 place-items-center rounded-full bg-primary-container text-on-primary-container transition hover:bg-primary hover:text-on-primary" aria-label="Facebook">f</a>
                    <a v-if="settings.social?.instagram" :href="settings.social.instagram" target="_blank" rel="noopener" class="grid size-9 place-items-center rounded-full bg-primary-container text-on-primary-container transition hover:bg-primary hover:text-on-primary" aria-label="Instagram">◎</a>
                    <a v-if="settings.social?.youtube" :href="settings.social.youtube" target="_blank" rel="noopener" class="grid size-9 place-items-center rounded-full bg-primary-container text-on-primary-container transition hover:bg-primary hover:text-on-primary" aria-label="YouTube">▶</a>
                </div>
            </div>
        </footer>
    </div>
</template>
